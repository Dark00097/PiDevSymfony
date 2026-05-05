<?php

namespace App\Service;

final class CreditAssistantService
{
    public function __construct(
        private readonly SimulationService $simulationService,
        private readonly ScoringService $scoringService,
        private readonly RiskService $riskService,
        private readonly ProjectionService $projectionService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildAssistantPayload(array $context): array
    {
        $selectedCredit = is_array($context['selected_credit'] ?? null) ? $context['selected_credit'] : null;
        $selectedGarantie = is_array($context['selected_garantie'] ?? null) ? $context['selected_garantie'] : null;
        $draft = is_array($context['draft'] ?? null) ? $context['draft'] : [];

        $credit = $this->resolveCreditContext(
            $selectedCredit,
            $draft,
            (string) ($context['message'] ?? ''),
            is_array($context['credits'] ?? null) ? $context['credits'] : []
        );
        $intent = $this->resolveIntent((string) ($context['intent'] ?? ''), (string) ($context['message'] ?? ''));

        $amount = max(1000.0, $this->toFloat($credit['montantDemande'] ?? 0));
        $duration = max(6, (int) ($credit['duree'] ?? 36));
        $rate = max(1.0, $this->toFloat($credit['tauxInteret'] ?? 8.0));
        $salary = max(1.0, $this->toFloat($credit['salaire'] ?? 2500.0));

        $simulation = $this->simulationService->simulate($amount, $duration, $rate);
        $credit['mensualite'] = $simulation['monthly_payment'];

        $scoring = $this->scoringService->evaluate($credit, $selectedGarantie);
        $risk = $this->riskService->evaluate((float) $scoring['debt_ratio'], (float) $scoring['score']);
        $projection = $this->projectionService->evaluate(
            $credit,
            (int) ($context['horizonMonths'] ?? 12),
            (float) ($context['salaryChange'] ?? 0.0),
            (float) ($context['earlyRepayment'] ?? 0.0)
        );

        $offers = $this->buildOffers($amount, $duration, $rate, $salary, (float) $scoring['score'], (float) $scoring['debt_ratio']);
        $bestOffer = $offers !== [] ? $offers[0] : null;

        $eligibility = $this->buildEligibility((float) $scoring['score'], (float) $scoring['debt_ratio'], (string) ($risk['status'] ?? 'medium'), $selectedGarantie, $amount);
        $guidance = $this->buildGuidance($eligibility);
        $decision = $this->resolveDecision((string) ($risk['status'] ?? 'medium'), $eligibility['eligible']);

        $answer = $this->buildAnswerText($intent, $simulation, $bestOffer, $projection, $eligibility, $decision);
        $title = $this->buildTitle($intent);

        $constraints = [];
        foreach ($eligibility['conditions'] as $item) {
            $constraints[] = [
                'label' => (string) ($item['label'] ?? ''),
                'status' => (string) ($item['status'] ?? 'warn'),
                'detail' => (string) ($item['detail'] ?? ''),
            ];
        }

        $metrics = [
            ['label' => 'Montant simule', 'value' => $this->fmtMoney($simulation['amount'])],
            ['label' => 'Mensualite', 'value' => $this->fmtMoney($simulation['monthly_payment'])],
            ['label' => 'Taux endettement', 'value' => $this->fmtPercent($scoring['debt_ratio'])],
            ['label' => 'Score credit', 'value' => number_format((float) $scoring['score'], 1, '.', ' ').' /100'],
            ['label' => 'Risque', 'value' => (string) ($risk['level'] ?? 'Moyen')],
            ['label' => 'Eligibilite', 'value' => $eligibility['eligible'] ? 'Eligible' : 'A renforcer'],
        ];

        $recommendations = [
            $guidance['next_action'],
            $guidance['optimization'],
            $guidance['documents'],
        ];

        if ($bestOffer !== null) {
            $recommendations[] = 'Offre conseillee: '.$bestOffer['bank'].' ('.$bestOffer['rate'].').';
        }

        return [
            'intent' => $intent,
            'title' => $title,
            'answer' => $answer,
            'score' => (float) ($scoring['score'] ?? 0.0),
            'risk_level' => (string) ($risk['level'] ?? 'Moyen'),
            'decision' => $decision['label'],
            'metrics' => $metrics,
            'recommendations' => array_values(array_filter($recommendations, static fn ($item) => trim((string) $item) !== '')),
            'offers' => $offers,
            'best_offer' => $bestOffer,
            'constraints' => $constraints,
            'projection' => $projection,
            'simulation_block' => [
                'amount' => $simulation['amount'],
                'duration_months' => $simulation['duration_months'],
                'annual_rate' => $simulation['annual_rate'],
                'monthly_payment' => $simulation['monthly_payment'],
                'total_cost' => $simulation['total_cost'],
                'interest_cost' => $simulation['interest_cost'],
            ],
            'comparison_block' => [
                'offers' => $offers,
                'best_offer' => $bestOffer,
            ],
            'projection_block' => $projection,
            'recommendation_block' => $decision,
            'eligibility_block' => $eligibility,
            'guidance_block' => $guidance,
        ];
    }

    /**
     * @param array<string, mixed>|null $selectedCredit
     * @param array<string, mixed> $draft
     * @param array<int, array<string, mixed>> $credits
     * @return array<string, mixed>
     */
    private function resolveCreditContext(?array $selectedCredit, array $draft, string $message, array $credits): array
    {
        if (is_array($selectedCredit) && $selectedCredit !== []) {
            return $selectedCredit;
        }

        if ($this->toFloat($draft['montantDemande'] ?? 0) > 0 && (int) ($draft['duree'] ?? 0) > 0) {
            return $draft;
        }

        $parsed = $this->extractSimulationHints($message);
        if ($parsed['amount'] > 0 && $parsed['duration'] > 0) {
            return [
                'montantDemande' => $parsed['amount'],
                'duree' => $parsed['duration'],
                'tauxInteret' => $parsed['rate'] > 0 ? $parsed['rate'] : 8.0,
                'salaire' => 2500,
                'typeCredit' => 'Simulation',
            ];
        }

        return $credits[0] ?? [
            'montantDemande' => 20000,
            'duree' => 36,
            'tauxInteret' => 8.0,
            'salaire' => 2500,
            'typeCredit' => 'Credit personnel',
        ];
    }

    private function resolveIntent(string $intent, string $message): string
    {
        $source = $this->normalize($intent.' '.$message);
        if (str_contains($source, 'compar') || str_contains($source, 'offre')) {
            return 'comparison';
        }
        if (str_contains($source, 'project') || str_contains($source, 'future')) {
            return 'projection';
        }
        if (str_contains($source, 'eligib')) {
            return 'eligibility';
        }
        if (str_contains($source, 'recommand') || str_contains($source, 'decision')) {
            return 'recommendation';
        }
        if (str_contains($source, 'guid') || str_contains($source, 'etape')) {
            return 'guidance';
        }
        if (str_contains($source, 'risque') || str_contains($source, 'score')) {
            return 'risk';
        }
        if (str_contains($source, 'simul')) {
            return 'simulation';
        }

        return 'summary';
    }

    /**
     * @return array{amount: float, duration: int, rate: float}
     */
    private function extractSimulationHints(string $message): array
    {
        $amount = 0.0;
        $duration = 0;
        $rate = 0.0;
        $normalized = str_replace([',', "\u{00A0}"], ['.', ' '], $message);

        if (preg_match('/(\d[\d\s]{2,})\s*(dt|tnd|dinar)?/iu', $normalized, $match) === 1) {
            $amount = (float) str_replace(' ', '', preg_replace('/[^\d]/', '', (string) $match[1]));
        }
        if (preg_match('/(\d{1,3})\s*(mois|month)/iu', $normalized, $match) === 1) {
            $duration = (int) $match[1];
        }
        if (preg_match('/(\d{1,2}(?:\.\d{1,2})?)\s*%/u', $normalized, $match) === 1) {
            $rate = (float) $match[1];
        }

        return [
            'amount' => $amount,
            'duration' => $duration,
            'rate' => $rate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOffers(float $amount, int $duration, float $baseRate, float $salary, float $score, float $debtRatio): array
    {
        $catalog = [
            ['bank' => 'Nexora Bank', 'rate_delta' => -0.6, 'fee' => 120.0, 'feature' => 'Traitement prioritaire 24h', 'condition' => 'Domiciliation salaire recommandee'],
            ['bank' => 'BIAT', 'rate_delta' => 0.2, 'fee' => 160.0, 'feature' => 'Penalite anticipee reduite', 'condition' => 'Anciennete >= 12 mois'],
            ['bank' => 'Amen Bank', 'rate_delta' => 0.4, 'fee' => 140.0, 'feature' => 'Mensualites modulables', 'condition' => 'Apport minimal 10%'],
            ['bank' => 'BH Bank', 'rate_delta' => 0.8, 'fee' => 110.0, 'feature' => 'Assurance incluse', 'condition' => 'Garantie renforcee si > 40 000 DT'],
        ];

        $riskPenalty = $score < 55 ? 0.9 : ($score < 70 ? 0.45 : 0.0);
        $offers = [];
        foreach ($catalog as $item) {
            $rate = max(1.0, $baseRate + (float) $item['rate_delta'] + $riskPenalty);
            $simulation = $this->simulationService->simulate($amount, $duration, $rate, 0.35, (float) $item['fee']);
            $monthly = (float) ($simulation['monthly_payment'] ?? 0.0);
            $ratio = $salary > 0 ? ($monthly / $salary) * 100 : 100.0;

            $status = 'recommended';
            $statusLabel = 'Recommandee';
            $reason = 'Bon compromis cout / risque.';
            if ($ratio > 45 || $debtRatio > 50) {
                $status = 'blocked';
                $statusLabel = 'Non recommandee';
                $reason = 'Charge mensuelle trop elevee pour le revenu declare.';
            } elseif ($ratio > 35 || $debtRatio > 40) {
                $status = 'watch';
                $statusLabel = 'A etudier';
                $reason = 'Offre possible mais necessite un ajustement (duree/apport).';
            }

            $offers[] = [
                'bank' => (string) $item['bank'],
                'rate' => $this->fmtPercent($simulation['annual_rate']),
                'teg' => $this->fmtPercent($simulation['teg']),
                'monthly' => $this->fmtMoney($simulation['monthly_payment']),
                'total_cost' => $this->fmtMoney($simulation['total_cost']),
                'interest' => $this->fmtMoney($simulation['interest_cost']),
                'status' => $statusLabel,
                'status_key' => $status,
                'reason' => $reason,
                'feature' => (string) $item['feature'],
                'condition' => (string) $item['condition'],
                '_sort_total' => (float) ($simulation['total_cost'] ?? 0.0),
            ];
        }

        usort($offers, static fn (array $a, array $b): int => ($a['_sort_total'] <=> $b['_sort_total']));
        foreach ($offers as $idx => &$offer) {
            $offer['is_best'] = $idx === 0;
            unset($offer['_sort_total']);
        }

        return $offers;
    }

    /**
     * @param array<string, mixed>|null $garantie
     * @return array<string, mixed>
     */
    private function buildEligibility(float $score, float $debtRatio, string $riskStatus, ?array $garantie, float $amount): array
    {
        $retained = is_array($garantie) ? $this->toFloat($garantie['valeurRetenue'] ?? 0) : 0.0;
        $coverage = $amount > 0 ? ($retained / $amount) * 100 : 0.0;

        $conditions = [
            [
                'label' => 'Score minimum',
                'status' => $score >= 60 ? 'ok' : 'block',
                'detail' => $score >= 60 ? 'Score suffisant pour l etude.' : 'Score insuffisant pour une validation directe.',
            ],
            [
                'label' => 'Taux d endettement',
                'status' => $debtRatio <= 40 ? 'ok' : ($debtRatio <= 50 ? 'warn' : 'block'),
                'detail' => 'Ratio actuel: '.number_format($debtRatio, 1, '.', ' ').' %.',
            ],
            [
                'label' => 'Niveau de risque',
                'status' => $riskStatus === 'low' ? 'ok' : ($riskStatus === 'medium' ? 'warn' : 'block'),
                'detail' => $riskStatus === 'low' ? 'Risque maitrise.' : ($riskStatus === 'medium' ? 'Risque modere.' : 'Risque eleve.'),
            ],
            [
                'label' => 'Couverture garantie',
                'status' => $coverage >= 35 ? 'ok' : ($coverage > 0 ? 'warn' : 'warn'),
                'detail' => $coverage > 0
                    ? 'Couverture estimee: '.number_format($coverage, 1, '.', ' ').' % du montant.'
                    : 'Aucune garantie retenue, dossier possible mais plus exigeant.',
            ],
        ];

        $hasBlocking = false;
        foreach ($conditions as $condition) {
            if (($condition['status'] ?? '') === 'block') {
                $hasBlocking = true;
                break;
            }
        }

        return [
            'eligible' => !$hasBlocking,
            'coverage_ratio' => round($coverage, 1),
            'conditions' => $conditions,
            'explanation' => !$hasBlocking
                ? 'Le dossier est globalement eligible avec un bon niveau de recevabilite.'
                : 'Le dossier presente des points bloquants. Une optimisation est necessaire avant soumission finale.',
        ];
    }

    /**
     * @param array<string, mixed> $eligibility
     * @return array<string, string>
     */
    private function buildGuidance(array $eligibility): array
    {
        if ((bool) ($eligibility['eligible'] ?? false)) {
            return [
                'next_action' => 'Soumettre la demande avec les pieces justificatives a jour.',
                'optimization' => 'Comparer les offres recommandees pour minimiser le cout total.',
                'documents' => 'Verifier CIN, fiches de paie et justificatifs de domicile.',
            ];
        }

        return [
            'next_action' => 'Reduire le montant ou augmenter la duree pour alleger la mensualite.',
            'optimization' => 'Ajouter un apport ou une garantie complementaire pour renforcer le dossier.',
            'documents' => 'Mettre a jour les justificatifs de revenus avant une nouvelle simulation.',
        ];
    }

    /**
     * @return array{label: string, status: string}
     */
    private function resolveDecision(string $riskStatus, bool $eligible): array
    {
        if ($eligible && $riskStatus === 'low') {
            return ['label' => 'Acceptee', 'status' => 'ok'];
        }
        if ($eligible) {
            return ['label' => 'Conditionnelle', 'status' => 'warn'];
        }

        return ['label' => 'A renforcer', 'status' => 'block'];
    }

    /**
     * @param array<string, mixed> $simulation
     * @param array<string, mixed>|null $bestOffer
     * @param array<string, mixed> $projection
     * @param array<string, mixed> $eligibility
     * @param array<string, string> $decision
     */
    private function buildAnswerText(
        string $intent,
        array $simulation,
        ?array $bestOffer,
        array $projection,
        array $eligibility,
        array $decision
    ): string {
        return match ($intent) {
            'simulation' => sprintf(
                'Simulation: mensualite estimee a %s sur %d mois, pour un cout total de %s.',
                $this->fmtMoney($simulation['monthly_payment'] ?? 0),
                (int) ($simulation['duration_months'] ?? 0),
                $this->fmtMoney($simulation['total_cost'] ?? 0)
            ),
            'comparison' => $bestOffer !== null
                ? sprintf(
                    'Comparaison terminee: meilleure offre actuelle %s (%s, mensualite %s).',
                    (string) $bestOffer['bank'],
                    (string) $bestOffer['rate'],
                    (string) $bestOffer['monthly']
                )
                : 'Aucune offre disponible pour comparaison.',
            'projection' => sprintf(
                'Projection sur %d mois: reste estime %s, conclusion %s.',
                (int) ($projection['horizon_months'] ?? 0),
                $this->fmtMoney($projection['remaining_future'] ?? 0),
                (string) ($projection['conclusion_label'] ?? 'Stable')
            ),
            'eligibility' => (string) ($eligibility['explanation'] ?? ''),
            'guidance' => 'Voici les etapes recommandees pour finaliser votre parcours credit.',
            'risk' => sprintf(
                'Analyse risque: decision %s avec un score de %.1f/100.',
                (string) ($decision['label'] ?? 'Conditionnelle'),
                (float) ($projection['score'] ?? 0)
            ),
            default => sprintf(
                'Recommandation %s. %s',
                (string) ($decision['label'] ?? 'Conditionnelle'),
                (string) ($eligibility['explanation'] ?? '')
            ),
        };
    }

    private function buildTitle(string $intent): string
    {
        return match ($intent) {
            'simulation' => 'Simulation credit',
            'comparison' => 'Comparaison des offres',
            'projection' => 'Projection financiere',
            'eligibility' => 'Analyse d eligibilite',
            'guidance' => 'Guide utilisateur',
            'risk' => 'Analyse risque',
            default => 'Recommandation personnalisee',
        };
    }

    private function fmtMoney(mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return number_format($amount, 2, '.', ' ').' DT';
    }

    private function fmtPercent(mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return number_format($amount, 2, '.', ' ').'%';
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'ë'], 'e', $value);
        $value = str_replace(['à', 'â', 'ä'], 'a', $value);
        $value = str_replace(['ù', 'û', 'ü'], 'u', $value);
        $value = str_replace(['ô', 'ö'], 'o', $value);
        $value = str_replace(['î', 'ï'], 'i', $value);
        $value = str_replace(['ç'], 'c', $value);
        return $value;
    }
}
