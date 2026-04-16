<?php

namespace App\Service;

use App\Entity\Credit;

/**
 * Service d'évaluation du risque crédit.
 *
 * Fournit deux points d'entrée :
 *  - evaluate()  → calcul brut du score de risque (100 pts max)
 *  - analyze()   → enveloppe evaluate() avec raisons humaines, debt_ratio,
 *                   et intégration optionnelle du scoring.
 */
final class RiskService
{
    public function __construct(
        private readonly CreditAnalysisFactory $creditAnalysisFactory,
        private readonly GarantieService $garantieService,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point d'entrée principal utilisé par le contrôleur
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Analyse complète du risque avec raisons et données croisées du scoring.
     *
     * @param array<string, mixed> $scoreData  résultat de ScoringService::calculateScore()
     * @return array<string, mixed>
     */
    public function analyze(Credit $credit, array $scoreData = []): array
    {
        $evaluation = $this->evaluate($credit);

        // ── Extraction du debt ratio brut pour compatibilité template
        $salary    = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'salaire'));
        $mensualite = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));
        $debtRatio = $salary > 0 ? $mensualite / $salary : 1.0;

        // ── Génération des raisons humaines
        $reasons = $this->buildReasons($credit, $evaluation, $scoreData);

        return array_merge($evaluation, [
            'debt_ratio' => round($debtRatio, 4),
            'reasons'    => $reasons,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calcul brut pondéré (conservé pour rétro-compatibilité)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Évalue le niveau de risque d'un crédit selon plusieurs critères pondérés.
     *
     * @return array{
     *   score: float,
     *   label: string,
     *   color: string,
     *   badge_class: string,
     *   progress_class: string,
     *   detail: array<string, mixed>
     * }
     */
    public function evaluate(Credit $credit): array
    {
        $salary        = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'salaire'));
        $mensualite    = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));
        $contractType  = strtoupper($this->creditAnalysisFactory->getString($credit, 'typecontrat'));
        $seniority     = max(0, $this->creditAnalysisFactory->getInt($credit, 'ancienneteannees'));
        $coverage      = $this->garantieService->analyzeCoverage($credit);
        $duration      = max(1, $this->creditAnalysisFactory->getInt($credit, 'duree'));

        // ── 1. Ratio d'endettement (0–40 pts de risque)
        $debtRatio  = $salary > 0 ? $mensualite / $salary : 1.0;
        $debtRisk   = match (true) {
            $debtRatio <= 0.25 => 5.0,
            $debtRatio <= 0.33 => 15.0,
            $debtRatio <= 0.40 => 28.0,
            $debtRatio <= 0.50 => 35.0,
            default            => 40.0,
        };

        // ── 2. Stabilité contractuelle (0–20 pts de risque)
        $contractRisk = match (true) {
            in_array($contractType, ['CDI', 'FONCTIONNAIRE'], true) => 3.0,
            in_array($contractType, ['CDD'], true)                  => 10.0,
            in_array($contractType, ['FREELANCE', 'PROFESSION LIBERALE'], true) => 14.0,
            $contractType !== ''                                     => 17.0,
            default                                                  => 20.0,
        };

        // ── 3. Ancienneté (0–20 pts de risque)
        $seniorityRisk = match (true) {
            $seniority >= 5  => 3.0,
            $seniority >= 3  => 8.0,
            $seniority >= 1  => 14.0,
            default          => 20.0,
        };

        // ── 4. Couverture garanties (0–20 pts de risque)
        $coverageRisk = match (true) {
            $coverage['coverage_ratio'] >= 1.0 => 2.0,
            $coverage['coverage_ratio'] >= 0.7 => 8.0,
            $coverage['coverage_ratio'] >= 0.4 => 14.0,
            $coverage['coverage_ratio'] > 0    => 18.0,
            default                            => 20.0,
        };

        // ── 5. Durée excessive (0–10 pts de risque)
        $durationRisk = match (true) {
            $duration <= 24  => 1.0,
            $duration <= 48  => 3.0,
            $duration <= 84  => 6.0,
            default          => 10.0,
        };

        $totalRisk = $debtRisk + $contractRisk + $seniorityRisk + $coverageRisk + $durationRisk;
        $score     = round(min(100.0, max(0.0, $totalRisk)), 1);

        [$label, $color, $badgeClass, $progressClass] = match (true) {
            $score <= 30  => ['Faible',  '#28a745', 'text-bg-success', 'bg-success'],
            $score <= 60  => ['Moyen',   '#ffc107', 'text-bg-warning', 'bg-warning'],
            default       => ['Élevé',   '#dc3545', 'text-bg-danger',  'bg-danger'],
        };

        return [
            'score'          => $score,
            'label'          => $label,
            'color'          => $color,
            'badge_class'    => $badgeClass,
            'progress_class' => $progressClass,
            'detail'         => [
                'debt_risk'      => $debtRisk,
                'contract_risk'  => $contractRisk,
                'seniority_risk' => $seniorityRisk,
                'coverage_risk'  => $coverageRisk,
                'duration_risk'  => $durationRisk,
                'debt_ratio_pct' => round($debtRatio * 100, 1),
            ],
        ];
    }

    /**
     * Indique si le crédit est classé à risque élevé.
     */
    public function isHighRisk(Credit $credit): bool
    {
        return $this->evaluate($credit)['label'] === 'Élevé';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Génération des raisons humaines
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Construit la liste des observations qui justifient le niveau de risque.
     *
     * @return list<string>
     */
    private function buildReasons(Credit $credit, array $evaluation, array $scoreData): array
    {
        $reasons  = [];
        $detail   = $evaluation['detail'] ?? [];
        $debtPct  = (float) ($detail['debt_ratio_pct'] ?? 0);

        $salary       = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'salaire'));
        $contractType = strtoupper($this->creditAnalysisFactory->getString($credit, 'typecontrat'));
        $seniority    = max(0, $this->creditAnalysisFactory->getInt($credit, 'ancienneteannees'));
        $duration     = max(1, $this->creditAnalysisFactory->getInt($credit, 'duree'));
        $coverage     = $this->garantieService->analyzeCoverage($credit);

        // ── Ratio d'endettement
        if ($debtPct > 50) {
            $reasons[] = sprintf(
                'Le ratio d\'endettement est critique (%.1f%%) — largement au-dessus du seuil recommandé de 33%%.',
                $debtPct
            );
        } elseif ($debtPct > 40) {
            $reasons[] = sprintf(
                'Le ratio d\'endettement est élevé (%.1f%%) — dépasse le seuil de confort de 33%%.',
                $debtPct
            );
        } elseif ($debtPct > 33) {
            $reasons[] = sprintf(
                'Le ratio d\'endettement est légèrement tendu (%.1f%%), au-dessus du seuil idéal de 33%%.',
                $debtPct
            );
        } else {
            $reasons[] = sprintf(
                'Le ratio d\'endettement est maîtrisé (%.1f%%), restant sous le seuil de 33%%.',
                $debtPct
            );
        }

        // ── Salaire
        if ($salary <= 0) {
            $reasons[] = 'Aucun salaire renseigné — impossible d\'évaluer la capacité de remboursement.';
        }

        // ── Contrat
        if (in_array($contractType, ['CDI', 'FONCTIONNAIRE'], true)) {
            $reasons[] = sprintf('Contrat stable (%s) — facteur positif pour la solvabilité.', $contractType);
        } elseif ($contractType === 'CDD') {
            $reasons[] = 'Contrat CDD — la stabilité d\'emploi est incertaine à moyen terme.';
        } elseif (in_array($contractType, ['FREELANCE', 'PROFESSION LIBERALE'], true)) {
            $reasons[] = 'Statut indépendant — les revenus peuvent être irréguliers.';
        } else {
            $reasons[] = 'Type de contrat non standard — facteur de risque supplémentaire.';
        }

        // ── Ancienneté
        if ($seniority < 1) {
            $reasons[] = 'Ancienneté inférieure à 1 an — profil encore récent sur le marché du travail.';
        } elseif ($seniority < 3) {
            $reasons[] = sprintf('Ancienneté de %d an(s) — expérience professionnelle en construction.', $seniority);
        }

        // ── Garanties
        $covRatio = (float) ($coverage['coverage_ratio'] ?? 0);
        if ($covRatio >= 1.0) {
            $reasons[] = sprintf(
                'Les garanties couvrent 100%% du montant demandé (ratio %.0f%%).',
                $covRatio * 100
            );
        } elseif ($covRatio >= 0.6) {
            $reasons[] = sprintf(
                'Couverture partielle des garanties (%.0f%%) — un complément pourrait renforcer le dossier.',
                $covRatio * 100
            );
        } elseif ($covRatio > 0) {
            $reasons[] = sprintf(
                'Couverture garantie insuffisante (%.0f%%) — risque en cas de défaut.',
                $covRatio * 100
            );
        } else {
            $reasons[] = 'Aucune garantie associée — risque non collatéralisé.';
        }

        // ── Durée
        if ($duration > 84) {
            $reasons[] = sprintf(
                'Durée de crédit longue (%d mois) — augmente l\'exposition au risque de taux et de défaut.',
                $duration
            );
        }

        // ── Score de crédit (si disponible)
        $creditScore = (int) ($scoreData['score'] ?? 0);
        if ($creditScore > 0 && $creditScore < 60) {
            $reasons[] = sprintf(
                'Le score de crédit est faible (%d/100) — le dossier présente des fragilités cumulées.',
                $creditScore
            );
        }

        return $reasons;
    }
}