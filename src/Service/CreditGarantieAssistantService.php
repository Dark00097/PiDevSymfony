<?php

namespace App\Service;

final class CreditGarantieAssistantService
{
    public function __construct(
        private readonly ScoringService $scoringService,
        private readonly RiskService $riskService,
        private readonly GarantieService $garantieService,
        private readonly DocumentService $documentService,
        private readonly ProjectionService $projectionService,
    ) {
    }

    /**
     * @param array<string, mixed>|null $credit
     * @param array<string, mixed>|null $garantie
     * @return array<string, mixed>
     */
    public function buildResponse(string $intent, ?array $credit, ?array $garantie, array $context = []): array
    {
        $normalizedIntent = $this->normalizeIntent($intent, (string) ($context['message'] ?? ''));
        $creditRow = is_array($credit) ? $credit : [];
        $garantieRow = is_array($garantie) ? $garantie : [];

        $scoring = $this->scoringService->evaluate($creditRow, $garantieRow !== [] ? $garantieRow : null);
        $risk = $this->riskService->evaluate((float) $scoring['debt_ratio'], (float) $scoring['score']);
        $coverage = $this->garantieService->analyzeCoverageFromRows($creditRow, $garantieRow !== [] ? $garantieRow : null);
        $document = $this->documentService->evaluate($garantieRow !== [] ? $garantieRow : null);

        return match ($normalizedIntent) {
            'summary' => $this->buildSummaryPayload($creditRow, $garantieRow, $scoring),
            'risk' => $this->buildRiskPayload($scoring, $risk),
            'garantie_check' => $this->buildGarantiePayload($coverage),
            'documents' => $this->buildDocumentPayload($document),
            'projection' => $this->buildProjectionPayload($creditRow, $context),
            'decision' => $this->buildDecisionPayload($scoring, $risk, $coverage, $document),
            'message_client' => $this->buildClientMessagePayload($scoring, $risk, $coverage, $document),
            default => $this->buildSummaryPayload($creditRow, $garantieRow, $scoring),
        };
    }

    private function normalizeIntent(string $intent, string $message): string
    {
        $source = $this->normalize($intent.' '.$message);

        if (str_contains($source, 'resume')) {
            return 'summary';
        }
        if (str_contains($source, 'analyse') && str_contains($source, 'risque')) {
            return 'risk';
        }
        if (str_contains($source, 'verifier') && str_contains($source, 'garant')) {
            return 'garantie_check';
        }
        if (str_contains($source, 'verifier') && str_contains($source, 'document')) {
            return 'documents';
        }
        if (str_contains($source, 'projection') || str_contains($source, 'future')) {
            return 'projection';
        }
        if (str_contains($source, 'decision')) {
            return 'decision';
        }
        if (str_contains($source, 'message')) {
            return 'message_client';
        }
        if (str_contains($source, 'score') || str_contains($source, 'risque')) {
            return 'risk';
        }

        return 'summary';
    }

    /**
     * @param array<string, mixed> $credit
     * @param array<string, mixed> $garantie
     * @param array<string, mixed> $scoring
     * @return array<string, mixed>
     */
    private function buildSummaryPayload(array $credit, array $garantie, array $scoring): array
    {
        return [
            'intent' => 'summary',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Resume dossier',
            'answer' => 'Voici la synthese du dossier credit et des garanties associees.',
            'metrics' => [
                ['label' => 'Montant credit', 'value' => $this->fmtMoney($credit['montantDemande'] ?? 0)],
                ['label' => 'Duree', 'value' => ((int) ($credit['duree'] ?? 0)).' mois'],
                ['label' => 'Mensualite', 'value' => $this->fmtMoney($scoring['monthly_payment'] ?? 0)],
                ['label' => 'Garantie associee', 'value' => $garantie !== [] ? ('#'.(int) ($garantie['idGarantie'] ?? 0)) : 'Aucune'],
                ['label' => 'Statut', 'value' => (string) ($credit['statut'] ?? 'En attente')],
            ],
            'recommendations' => [
                'Verifiez que les informations du dossier sont a jour avant decision.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $scoring
     * @param array<string, mixed> $risk
     * @return array<string, mixed>
     */
    private function buildRiskPayload(array $scoring, array $risk): array
    {
        return [
            'intent' => 'risk',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Analyse risque',
            'answer' => (string) ($risk['message'] ?? 'Analyse de risque indisponible.'),
            'score' => (float) ($scoring['score'] ?? 0),
            'risk_level' => (string) ($risk['level'] ?? 'Moyen'),
            'metrics' => [
                ['label' => 'Taux endettement', 'value' => number_format((float) ($scoring['debt_ratio'] ?? 0), 1, '.', ' ').' %'],
                ['label' => 'Score credit', 'value' => number_format((float) ($scoring['score'] ?? 0), 1, '.', ' ').' /100'],
                ['label' => 'Niveau de risque', 'value' => (string) ($risk['level'] ?? 'Moyen')],
            ],
            'recommendations' => $this->buildAutoRecommendations((float) ($scoring['debt_ratio'] ?? 0), (float) ($scoring['score'] ?? 0), false),
        ];
    }

    /**
     * @param array<string, mixed> $coverage
     * @return array<string, mixed>
     */
    private function buildGarantiePayload(array $coverage): array
    {
        return [
            'intent' => 'garantie_check',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Verification garantie',
            'answer' => (string) ($coverage['message'] ?? ''),
            'metrics' => [
                ['label' => 'Valeur estimee', 'value' => $this->fmtMoney($coverage['estimated_total'] ?? 0)],
                ['label' => 'Valeur retenue', 'value' => $this->fmtMoney($coverage['retained_total'] ?? 0)],
                ['label' => 'Couverture', 'value' => number_format((float) ($coverage['coverage_ratio'] ?? 0), 1, '.', ' ').' %'],
                ['label' => 'Statut', 'value' => (string) ($coverage['label'] ?? 'Insuffisante')],
            ],
            'recommendations' => [
                (string) ($coverage['covered'] ?? false)
                    ? 'Garantie suffisante pour ce dossier.'
                    : 'Ajoutez une garantie complementaire pour renforcer la couverture.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function buildDocumentPayload(array $document): array
    {
        return [
            'intent' => 'documents',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Verification documents',
            'answer' => (string) ($document['message'] ?? ''),
            'metrics' => [
                ['label' => 'Presence', 'value' => ($document['has_document'] ?? false) ? 'Oui' : 'Non'],
                ['label' => 'Qualite', 'value' => ($document['is_blurry'] ?? false) ? 'Floue' : 'Nette'],
                ['label' => 'Controle', 'value' => (string) ($document['label'] ?? 'En attente')],
            ],
            'recommendations' => ($document['status'] ?? '') === 'valide'
                ? ['Document conforme.']
                : ['Fournir un document officiel net. Les captures ecran sont interdites.'],
        ];
    }

    /**
     * @param array<string, mixed> $credit
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildProjectionPayload(array $credit, array $context): array
    {
        $horizon = (int) ($context['horizonMonths'] ?? 12);
        $salaryChange = (float) ($context['salaryChange'] ?? 0);
        $earlyRepayment = (float) ($context['earlyRepayment'] ?? 0);
        $projection = $this->projectionService->evaluate($credit, $horizon, $salaryChange, $earlyRepayment);

        return [
            'intent' => 'projection',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Projection future',
            'answer' => sprintf(
                'Apres %d mois, reste a payer estime: %s. Risque attendu: %s.',
                (int) ($projection['horizon_months'] ?? 0),
                $this->fmtMoney($projection['remaining_future'] ?? 0),
                (string) ($projection['risk_level'] ?? 'Moyen')
            ),
            'projection' => $projection,
            'metrics' => [
                ['label' => 'Reste a payer futur', 'value' => $this->fmtMoney($projection['remaining_future'] ?? 0)],
                ['label' => 'Mensualite', 'value' => $this->fmtMoney($projection['monthly_payment'] ?? 0)],
                ['label' => 'Taux endettement', 'value' => number_format((float) ($projection['debt_ratio'] ?? 0), 1, '.', ' ').' %'],
                ['label' => 'Score credit', 'value' => number_format((float) ($projection['score'] ?? 0), 1, '.', ' ').' /100'],
                ['label' => 'Conclusion', 'value' => (string) ($projection['conclusion_label'] ?? 'Stable')],
            ],
            'recommendations' => [
                (string) ($projection['conclusion_text'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $scoring
     * @param array<string, mixed> $risk
     * @param array<string, mixed> $coverage
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function buildDecisionPayload(array $scoring, array $risk, array $coverage, array $document): array
    {
        $decision = 'Accepte avec conditions';
        if (($risk['status'] ?? '') === 'low' && ($coverage['covered'] ?? false) && ($document['status'] ?? '') === 'valide') {
            $decision = 'Accepte';
        } elseif (($risk['status'] ?? '') === 'high' || ($document['status'] ?? '') === 'suspect') {
            $decision = 'Refuse';
        }

        $answer = match ($decision) {
            'Accepte' => 'Decision recommandee: accepte. Le dossier est solide et bien couvert.',
            'Refuse' => 'Decision recommandee: refuse. Le risque ou la conformite documentaire est insuffisante.',
            default => 'Decision recommandee: accepte avec conditions. Un renforcement du dossier est necessaire.',
        };

        $recommendations = $this->buildAutoRecommendations(
            (float) ($scoring['debt_ratio'] ?? 0),
            (float) ($scoring['score'] ?? 0),
            !($coverage['covered'] ?? false)
        );

        return [
            'intent' => 'decision',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Decision recommandee',
            'answer' => $answer,
            'decision' => $decision,
            'risk_level' => (string) ($risk['level'] ?? 'Moyen'),
            'score' => (float) ($scoring['score'] ?? 0),
            'metrics' => [
                ['label' => 'Decision', 'value' => $decision],
                ['label' => 'Risque', 'value' => (string) ($risk['level'] ?? 'Moyen')],
                ['label' => 'Score', 'value' => number_format((float) ($scoring['score'] ?? 0), 1, '.', ' ').' /100'],
                ['label' => 'Documents', 'value' => (string) ($document['label'] ?? 'En attente')],
            ],
            'constraints' => [
                ['label' => 'Risque dossier', 'status' => ($risk['status'] ?? '') === 'low' ? 'ok' : (($risk['status'] ?? '') === 'medium' ? 'warn' : 'block'), 'detail' => (string) ($risk['message'] ?? '')],
                ['label' => 'Couverture garantie', 'status' => ($coverage['covered'] ?? false) ? 'ok' : 'warn', 'detail' => (string) ($coverage['message'] ?? '')],
                ['label' => 'Conformite document', 'status' => ($document['status'] ?? '') === 'valide' ? 'ok' : (($document['status'] ?? '') === 'flou' ? 'warn' : 'block'), 'detail' => (string) ($document['message'] ?? '')],
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param array<string, mixed> $scoring
     * @param array<string, mixed> $risk
     * @param array<string, mixed> $coverage
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function buildClientMessagePayload(array $scoring, array $risk, array $coverage, array $document): array
    {
        $decisionPayload = $this->buildDecisionPayload($scoring, $risk, $coverage, $document);
        $decision = (string) ($decisionPayload['decision'] ?? 'Accepte avec conditions');

        $clientMessage = match ($decision) {
            'Accepte' => 'Votre dossier est accepte. Nous vous remercions pour la qualite des informations transmises.',
            'Refuse' => 'Votre dossier ne peut pas etre accepte actuellement. Vous pouvez soumettre une nouvelle demande apres mise a jour des justificatifs.',
            default => 'Votre dossier est pre-accepte sous conditions: merci d ajouter un document net et/ou une garantie complementaire.',
        };

        return [
            'intent' => 'message_client',
            'assistant_provider' => 'RuleEngine',
            'title' => 'Message client',
            'answer' => $clientMessage,
            'decision' => $decision,
            'risk_level' => (string) ($risk['level'] ?? 'Moyen'),
            'score' => (float) ($scoring['score'] ?? 0),
            'metrics' => [
                ['label' => 'Decision', 'value' => $decision],
                ['label' => 'Canal', 'value' => 'Email / Notification'],
                ['label' => 'Ton', 'value' => 'Professionnel et clair'],
            ],
            'recommendations' => [
                'Relire le message avant envoi.',
                'Ajouter une action suivante claire pour le client.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildAutoRecommendations(float $debtRatio, float $score, bool $missingCoverage): array
    {
        $items = [];
        if ($debtRatio > 40.0) {
            $items[] = 'Reduire le montant demande pour baisser le taux d endettement.';
        }
        if ($missingCoverage) {
            $items[] = 'Ajouter une garantie supplementaire pour renforcer la couverture.';
        }
        if ($score < 65.0) {
            $items[] = 'Envisager un remboursement partiel anticipe pour ameliorer le score.';
        }
        if ($items === []) {
            $items[] = 'Le dossier est coherent, maintenir les informations a jour.';
        }

        return $items;
    }

    private function fmtMoney(mixed $value): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;

        return number_format($amount, 2, '.', ' ').' DT';
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

