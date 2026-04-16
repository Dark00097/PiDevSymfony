<?php

namespace App\Service;

use App\Entity\Credit;

/**
 * Service de scoring crédit : scoring déterministe (règles métier) + scoring IA (Gemini).
 *
 * Le scoring IA est optionnel et activé uniquement lorsque GEMINI_API_KEY est configuré.
 * En l'absence de clé, seul le scoring règles est utilisé.
 */
final class ScoringService
{
    public function __construct(
        private readonly CreditAnalysisFactory $creditAnalysisFactory,
        private readonly GarantieService $garantieService,
        private readonly GeminiService $geminiService,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scoring déterministe (règles métier)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function calculateScore(Credit $credit): array
    {
        $salary = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'salaire'));
        $monthlyPayment = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));
        $contractType = strtoupper($this->creditAnalysisFactory->getString($credit, 'typecontrat'));
        $seniority = max(0, $this->creditAnalysisFactory->getInt($credit, 'ancienneteannees'));
        $autoFunding = max(0.0, $this->creditAnalysisFactory->getNullableFloat($credit, 'autofinancement') ?? 0.0);
        $requestedAmount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantdemande'));

        $debtRatio = $salary > 0 ? $monthlyPayment / $salary : 0.0;
        $autoFundingRatio = $requestedAmount > 0 ? $autoFunding / $requestedAmount : 0.0;
        $coverage = $this->garantieService->analyzeCoverage($credit);

        $ratioPoints = match (true) {
            $salary <= 0 => 0,
            $debtRatio <= 0.33 => 30,
            $debtRatio <= 0.40 => 22,
            $debtRatio <= 0.50 => 12,
            default => 0,
        };

        $contractPoints = match (true) {
            in_array($contractType, ['CDI', 'FONCTIONNAIRE'], true) => 20,
            in_array($contractType, ['CDD', 'FREELANCE', 'PROFESSION LIBERALE'], true) => 14,
            $contractType !== '' => 8,
            default => 0,
        };

        $seniorityPoints = match (true) {
            $seniority >= 5 => 20,
            $seniority >= 3 => 15,
            $seniority >= 1 => 10,
            default => 4,
        };

        $guaranteePoints = match (true) {
            $coverage['coverage_ratio'] >= 1.0 => 20,
            $coverage['coverage_ratio'] >= 0.7 => 15,
            $coverage['coverage_ratio'] >= 0.4 => 8,
            $coverage['coverage_ratio'] > 0 => 4,
            default => 0,
        };

        $autofinancingPoints = match (true) {
            $autoFundingRatio >= 0.20 => 10,
            $autoFundingRatio >= 0.10 => 7,
            $autoFundingRatio >= 0.05 => 4,
            default => 0,
        };

        $score = (int) round($ratioPoints + $contractPoints + $seniorityPoints + $guaranteePoints + $autofinancingPoints);
        $decision = $this->resolveDecision($score);

        return [
            'score' => $score,
            'color' => $decision['color'],
            'decision' => $decision['label'],
            'badge_class' => $decision['badge_class'],
            'progress_class' => $decision['progress_class'],
            'components' => [
                'ratio_mensualite_salaire' => [
                    'label' => 'Ratio mensualite / salaire',
                    'points' => $ratioPoints,
                    'max' => 30,
                    'value' => round($debtRatio * 100, 1).'%',
                ],
                'type_contrat' => [
                    'label' => 'Type de contrat',
                    'points' => $contractPoints,
                    'max' => 20,
                    'value' => $contractType !== '' ? $contractType : 'Non renseigne',
                ],
                'anciennete' => [
                    'label' => 'Anciennete',
                    'points' => $seniorityPoints,
                    'max' => 20,
                    'value' => $seniority.' ans',
                ],
                'garanties' => [
                    'label' => 'Garanties',
                    'points' => $guaranteePoints,
                    'max' => 20,
                    'value' => round($coverage['coverage_ratio'] * 100, 1).'%',
                ],
                'autofinancement' => [
                    'label' => 'Autofinancement',
                    'points' => $autofinancingPoints,
                    'max' => 10,
                    'value' => round($autoFundingRatio * 100, 1).'%',
                ],
            ],
            'metrics' => [
                'debt_ratio' => $debtRatio,
                'autofinancement_ratio' => $autoFundingRatio,
                'garantie_coverage_ratio' => (float) $coverage['coverage_ratio'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scoring IA (Gemini)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule un score IA via Gemini en envoyant les données du dossier de crédit.
     *
     * Retourne un tableau contenant :
     *  - ai_available  : bool   — indique si Gemini est configuré
     *  - ai_score      : int    — score IA (0–100), null si non disponible
     *  - ai_decision   : string — décision IA
     *  - ai_color      : string — code couleur de la décision IA
     *  - ai_badge_class: string — classe Bootstrap du badge IA
     *  - ai_confidence : int    — confiance IA (0–100)
     *  - ai_justification: string — justification textuelle de l'IA
     *  - ai_provider   : string — 'Gemini' ou 'Indisponible'
     *
     * @return array<string, mixed>
     */
    public function calculateAiScore(Credit $credit): array
    {
        $default = [
            'ai_available'     => false,
            'ai_score'         => null,
            'ai_decision'      => 'Non disponible',
            'ai_color'         => '#6c757d',
            'ai_badge_class'   => 'text-bg-secondary',
            'ai_confidence'    => 0,
            'ai_justification' => 'Le scoring IA nécessite une clé API Gemini configurée (GEMINI_API_KEY).',
            'ai_provider'      => 'Indisponible',
        ];

        if (!$this->geminiService->isConfigured()) {
            return $default;
        }

        // ── Collecte des données du dossier
        $salary          = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'salaire'));
        $monthlyPayment  = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'mensualite'));
        $contractType    = $this->creditAnalysisFactory->getString($credit, 'typecontrat');
        $seniority       = max(0, $this->creditAnalysisFactory->getInt($credit, 'ancienneteannees'));
        $requestedAmount = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantdemande'));
        $approvedAmount  = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'montantaccorde'));
        $autoFunding     = max(0.0, $this->creditAnalysisFactory->getNullableFloat($credit, 'autofinancement') ?? 0.0);
        $duration        = max(1, $this->creditAnalysisFactory->getInt($credit, 'duree'));
        $rate            = max(0.0, $this->creditAnalysisFactory->getFloat($credit, 'tauxinteret'));
        $creditType      = $this->creditAnalysisFactory->getString($credit, 'typecredit');
        $coverage        = $this->garantieService->analyzeCoverage($credit);
        $debtRatio       = $salary > 0 ? round($monthlyPayment / $salary * 100, 1) : 0;
        $autoRatio       = $requestedAmount > 0 ? round($autoFunding / $requestedAmount * 100, 1) : 0;

        // ── Construction du prompt structuré
        $prompt = $this->buildAiScoringPrompt([
            'type_credit'      => $creditType ?: 'Non précisé',
            'montant_demande'  => $requestedAmount,
            'montant_accorde'  => $approvedAmount,
            'autofinancement'  => $autoFunding,
            'duree_mois'       => $duration,
            'taux_annuel'      => $rate,
            'mensualite'       => $monthlyPayment,
            'salaire'          => $salary,
            'type_contrat'     => $contractType ?: 'Non précisé',
            'anciennete_ans'   => $seniority,
            'ratio_endettement'=> $debtRatio,
            'ratio_autofinancement' => $autoRatio,
            'couverture_garantie'   => round(($coverage['coverage_ratio'] ?? 0) * 100, 1),
            'garantie_estimee'      => $coverage['estimated_total'] ?? 0,
            'garantie_retenue'      => $coverage['retained_total'] ?? 0,
        ]);

        // ── Appel Gemini
        $result = $this->geminiService->generateCreditScoringAdvice($prompt);
        $responseText = $result['text'] ?? '';

        if ($responseText === '') {
            return $default;
        }

        // ── Parsing de la réponse IA
        return $this->parseAiResponse($responseText);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Construit le prompt envoyé à Gemini pour le scoring IA.
     *
     * @param array<string, mixed> $data
     */
    private function buildAiScoringPrompt(array $data): string
    {
        return sprintf(
            <<<'PROMPT'
Tu es un expert en analyse de risque de crédit bancaire. Analyse le dossier suivant et fournis un scoring.

DOSSIER DE CRÉDIT :
- Type de crédit : %s
- Montant demandé : %.2f DT
- Montant accordé : %.2f DT
- Autofinancement : %.2f DT (ratio : %.1f%%)
- Durée : %d mois
- Taux annuel : %.2f%%
- Mensualité : %.2f DT
- Salaire mensuel : %.2f DT
- Type de contrat : %s
- Ancienneté : %d ans
- Ratio d'endettement : %.1f%%
- Couverture garantie : %.1f%%
- Garantie estimée : %.2f DT
- Garantie retenue : %.2f DT

CONSIGNES :
1. Attribue un score de 0 à 100 (100 = excellent, 0 = très risqué)
2. Donne une décision : "Accepté", "À étudier" ou "Refusé"
3. Indique ton niveau de confiance de 0 à 100
4. Fournis une justification concise (max 3 phrases)

RÉPONDS UNIQUEMENT en JSON valide avec cette structure exacte :
{"score": 75, "decision": "À étudier", "confidence": 80, "justification": "Le dossier présente..."}
PROMPT,
            $data['type_credit'],
            $data['montant_demande'],
            $data['montant_accorde'],
            $data['autofinancement'],
            $data['ratio_autofinancement'],
            $data['duree_mois'],
            $data['taux_annuel'],
            $data['mensualite'],
            $data['salaire'],
            $data['type_contrat'],
            $data['anciennete_ans'],
            $data['ratio_endettement'],
            $data['couverture_garantie'],
            $data['garantie_estimee'],
            $data['garantie_retenue']
        );
    }

    /**
     * Parse la réponse textuelle de Gemini et extrait le score IA.
     *
     * @return array<string, mixed>
     */
    private function parseAiResponse(string $responseText): array
    {
        $default = [
            'ai_available'     => true,
            'ai_score'         => null,
            'ai_decision'      => 'Erreur de parsing',
            'ai_color'         => '#6c757d',
            'ai_badge_class'   => 'text-bg-secondary',
            'ai_confidence'    => 0,
            'ai_justification' => 'La réponse de l\'IA n\'a pas pu être interprétée.',
            'ai_provider'      => 'Gemini',
        ];

        // ── Tenter d'extraire le JSON de la réponse
        $jsonText = $responseText;

        // Gemini peut envelopper le JSON dans un bloc ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.+?\})\s*```/s', $responseText, $matches)) {
            $jsonText = $matches[1];
        } elseif (preg_match('/(\{[^{}]*"score"[^{}]*\})/s', $responseText, $matches)) {
            $jsonText = $matches[1];
        }

        $parsed = json_decode($jsonText, true);
        if (!is_array($parsed) || !isset($parsed['score'])) {
            // ── Fallback : essayer d'extraire le score par regex
            if (preg_match('/"score"\s*:\s*(\d+)/', $responseText, $scoreMatch)) {
                $parsed = ['score' => (int) $scoreMatch[1]];

                if (preg_match('/"decision"\s*:\s*"([^"]+)"/', $responseText, $decMatch)) {
                    $parsed['decision'] = $decMatch[1];
                }
                if (preg_match('/"confidence"\s*:\s*(\d+)/', $responseText, $confMatch)) {
                    $parsed['confidence'] = (int) $confMatch[1];
                }
                if (preg_match('/"justification"\s*:\s*"([^"]+)"/', $responseText, $justMatch)) {
                    $parsed['justification'] = $justMatch[1];
                }
            } else {
                $default['ai_justification'] = 'Réponse IA : ' . mb_substr($responseText, 0, 300);
                return $default;
            }
        }

        $aiScore = max(0, min(100, (int) ($parsed['score'] ?? 0)));
        $aiDecision = $this->resolveDecision($aiScore);
        $rawDecision = trim((string) ($parsed['decision'] ?? ''));
        $confidence = max(0, min(100, (int) ($parsed['confidence'] ?? 70)));
        $justification = trim((string) ($parsed['justification'] ?? 'Aucune justification fournie.'));

        return [
            'ai_available'     => true,
            'ai_score'         => $aiScore,
            'ai_decision'      => $rawDecision !== '' ? $rawDecision : $aiDecision['label'],
            'ai_color'         => $aiDecision['color'],
            'ai_badge_class'   => $aiDecision['badge_class'],
            'ai_confidence'    => $confidence,
            'ai_justification' => $justification,
            'ai_provider'      => 'Gemini',
        ];
    }

    /**
     * @return array{label:string, color:string, badge_class:string, progress_class:string}
     */
    private function resolveDecision(int $score): array
    {
        return match (true) {
            $score >= 80 => [
                'label' => 'Accepte',
                'color' => '#28a745',
                'badge_class' => 'text-bg-success',
                'progress_class' => 'bg-success',
            ],
            $score >= 60 => [
                'label' => 'A etudier',
                'color' => '#ffc107',
                'badge_class' => 'text-bg-warning',
                'progress_class' => 'bg-warning',
            ],
            default => [
                'label' => 'Refuse',
                'color' => '#dc3545',
                'badge_class' => 'text-bg-danger',
                'progress_class' => 'bg-danger',
            ],
        };
    }
}
