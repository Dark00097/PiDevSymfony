<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiService
{
    private ?string $lastGeminiError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function generateUserManagementAdvice(array $context): array
    {
        $prompt = $this->buildPrompt($context);
        $responseText = $this->requestGemini($prompt);

        if ($responseText !== null) {
            return [
                'provider' => 'OpenRouter',
                'text' => $responseText,
            ];
        }

        return [
            'provider' => 'Fallback',
            'text' => $this->fallbackAdvice($context),
        ];
    }

    /**
     * Analyse les réclamations et retourne un rapport IA structuré.
     * Retourne null si l'API est indisponible.
     */
    public function analyseReclamations(string $prompt): ?string
    {
        return $this->requestGemini($prompt);
    }

    /**
     * Améliore le texte d'une réclamation via l'IA.
     * Retourne un tableau ['improved' => string, 'sentiment' => string, 'severity' => string].
     */
    public function improveReclamationDescription(string $description, array $context = []): string
    {
        $type      = trim((string) ($context['type']      ?? ''));
        $categorie = trim((string) ($context['categorie'] ?? ''));
        $montant   = trim((string) ($context['montant']   ?? ''));

        $prompt = "Tu es un assistant bancaire. Améliore le texte de réclamation suivant pour le rendre plus clair, professionnel et précis. "
            . "Réponds UNIQUEMENT avec le texte amélioré, sans introduction ni explication.\n\n"
            . "Contexte : type={$type}, catégorie={$categorie}, montant={$montant} TND\n\n"
            . "Texte original : {$description}";

        $result = $this->requestGemini($prompt);

        return $result !== null && trim($result) !== '' ? trim($result) : $description;
    }

    /**
     * Genere un scoring IA pour un dossier de credit via OpenRouter.
     *
     * @return array{provider: string, text: string}
     */
    public function generateCreditScoringAdvice(string $prompt): array
    {
        $responseText = $this->requestGeminiForCredit($prompt);

        if ($responseText !== null) {
            return [
                'provider' => 'OpenRouter',
                'text' => $responseText,
            ];
        }

        return [
            'provider' => 'Fallback',
            'text' => '',
        ];
    }

    /**
     * Analyse a credit request and returns approval/failure probabilities, score and risk.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *   provider: string,
     *   approval_probability: float,
     *   failure_probability: float,
     *   score: float,
     *   risk_level: string,
     *   suggested_status: string,
     *   explanation: string
     * }
     */
    public function analyzeCreditEligibility(array $payload): array
    {
        $fallback = $this->buildFallbackEligibility($payload);

        $prompt = $this->buildCreditEligibilityPrompt($payload, $fallback);
        $responseText = $this->requestGeminiForCredit($prompt);
        if ($responseText === null) {
            if ($this->lastGeminiError !== null && $this->isQuotaError($this->lastGeminiError)) {
                $fallback['provider'] = 'Fallback (quota exceeded)';
                $fallback['explanation'] .= ' OpenRouter indisponible: quota API depassee.';
            }
            return $fallback;
        }

        $decoded = $this->extractJsonObject($responseText);
        if ($decoded === null) {
            return $fallback;
        }

        $normalized = $this->normalizeEligibilityResult($decoded, (string) $fallback['explanation']);
        if ($normalized === null) {
            return $fallback;
        }

        $normalized['provider'] = 'OpenRouter';

        return $normalized;
    }

    /**
     * Builds a personalized profile coach insight for the IA & Activite tab.
     *
     * @param array<string, mixed> $context
     * @return array{
     *   provider: string,
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * }
     */
    public function generateProfileCoach(array $context): array
    {
        $fallback = $this->buildFallbackProfileCoach($context);

        $prompt = $this->buildProfileCoachPrompt($context, $fallback);
        $responseText = $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.35,
                'maxOutputTokens' => 420,
                'responseMimeType' => 'application/json',
            ],
            20
        );

        if ($responseText === null) {
            if ($this->lastGeminiError !== null && $this->isQuotaError($this->lastGeminiError)) {
                $fallback['provider'] = 'Fallback (quota exceeded)';
            }

            return $fallback;
        }

        $decoded = $this->extractJsonObject($responseText);
        if ($decoded === null) {
            return $fallback;
        }

        $normalized = $this->normalizeProfileCoachResult($decoded, $fallback);
        $normalized['provider'] = 'OpenRouter';

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{provider: string, text: string}
     */
    public function generateGuaranteeDescription(array $payload): array
    {
        $fallback = $this->buildFallbackGuaranteeDescription($payload);
        $prompt = $this->buildGuaranteeDescriptionPrompt($payload);

        $responseText = $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.45,
                'maxOutputTokens' => 180,
            ],
            15
        );

        if ($responseText === null) {
            return [
                'provider' => 'Fallback',
                'text' => $fallback,
            ];
        }

        $normalized = trim(preg_replace('/\s+/', ' ', strip_tags($responseText)) ?? '');
        if ($normalized === '') {
            return [
                'provider' => 'Fallback',
                'text' => $fallback,
            ];
        }

        return [
            'provider' => 'OpenRouter',
            'text' => mb_substr($normalized, 0, 240, 'UTF-8'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{provider: string, text: string}
     */
    public function generateGuaranteeDescriptionOpenRouter(array $payload): array
    {
        $prompt = $this->buildGuaranteeDescriptionPrompt($payload);

        $responseText = $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.45,
                'maxOutputTokens' => 180,
            ],
            15
        );

        if ($responseText === null) {
            throw new \RuntimeException($this->lastGeminiError !== null && trim($this->lastGeminiError) !== ''
                ? 'OpenRouter indisponible: '.$this->lastGeminiError
                : 'OpenRouter indisponible pour la generation.');
        }

        $normalized = trim(preg_replace('/\s+/', ' ', strip_tags($responseText)) ?? '');
        if ($normalized === '') {
            throw new \RuntimeException('OpenRouter a renvoye une reponse vide pour la generation.');
        }

        return [
            'provider' => 'OpenRouter',
            'text' => mb_substr($normalized, 0, 240, 'UTF-8'),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   provider: string,
     *   intent: string,
     *   title: string,
     *   answer: string,
     *   decision: string,
     *   score: float,
     *   risk_level: string,
     *   metrics: array<int, array{label: string, value: string}>,
     *   recommendations: array<int, string>,
     *   offers: array<int, array{
     *     bank: string,
     *     rate: string,
     *     monthly: string,
     *     total_cost: string,
     *     interest: string,
     *     status: string,
     *     reason: string
     *   }>,
     *   best_offer: array<string, string>,
     *   constraints: array<int, array{label: string, status: string, detail: string}>
     * }
     */
    public function generateCreditAssistant(array $context): array
    {
        $fallback = $this->buildFallbackCreditAssistant($context);

        $prompt = $this->buildCreditAssistantPrompt($context, $fallback);
        $responseText = $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.35,
                'maxOutputTokens' => 700,
                'responseMimeType' => 'application/json',
            ],
            25
        );

        if ($responseText === null) {
            if ($this->lastGeminiError !== null && $this->isQuotaError($this->lastGeminiError)) {
                $fallback['provider'] = 'Fallback (quota exceeded)';
            }

            return $fallback;
        }

        $decoded = $this->extractJsonObject($responseText);
        if ($decoded === null) {
            return $fallback;
        }

        $normalized = $this->normalizeCreditAssistantResult($decoded, $fallback);
        $normalized['provider'] = 'OpenRouter';

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   provider: string,
     *   approval_probability: float,
     *   failure_probability: float,
     *   score: float,
     *   risk_level: string,
     *   suggested_status: string,
     *   explanation: string
     * }
     */
    private function buildFallbackEligibility(array $payload): array
    {
        $amount = max(0.0, (float) ($payload['montantDemande'] ?? 0));
        $autoFunding = max(0.0, (float) ($payload['autofinancement'] ?? 0));
        $duration = max(1, (int) ($payload['duree'] ?? 1));
        $rate = max(0.0, (float) ($payload['tauxInteret'] ?? 0));
        $salary = max(0.0, (float) ($payload['salaire'] ?? 0));
        $seniority = max(0, min(60, (int) ($payload['ancienneteAnnees'] ?? 0)));
        $contract = $this->normalizeText((string) ($payload['typeContrat'] ?? ''));
        $monthly = (float) ($payload['mensualite'] ?? 0);

        $principal = max(0.0, $amount - $autoFunding);
        if ($monthly <= 0.0 && $principal > 0.0) {
            $monthlyRate = $rate / 100 / 12;
            $monthly = $monthlyRate <= 0
                ? $principal / $duration
                : ($principal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$duration));
        }

        $debtRatio = $salary > 0 ? ($monthly / $salary) : 1.0;
        $fundingRatio = $amount > 0 ? min(1.0, $autoFunding / $amount) : 0.0;
        $exposure = $salary > 0 ? ($principal / $salary) : 999.0;

        $score = 50.0;
        $score += match (true) {
            str_contains($contract, 'fonctionnaire') => 12.0,
            str_contains($contract, 'cdi') => 11.0,
            str_contains($contract, 'profession liberale') => 7.0,
            str_contains($contract, 'cdd') => 4.0,
            default => 2.0,
        };
        $score += min(15.0, $seniority * 1.5);
        $score += $fundingRatio * 18.0;
        $score += match (true) {
            $debtRatio <= 0.30 => 15.0,
            $debtRatio <= 0.40 => 9.0,
            $debtRatio <= 0.50 => 2.0,
            $debtRatio <= 0.60 => -10.0,
            default => -22.0,
        };
        $score += match (true) {
            $salary >= 5000 => 8.0,
            $salary >= 3000 => 5.0,
            $salary >= 1500 => 2.0,
            default => -6.0,
        };
        $score += match (true) {
            $exposure > 30 => -10.0,
            $exposure > 18 => -5.0,
            $exposure < 8 => 3.0,
            default => 0.0,
        };

        $score = round($this->clamp($score, 1.0, 99.0), 1);
        $approval = round($this->clamp(
            $score
            + ($debtRatio <= 0.33 ? 5.0 : 0.0)
            + ($debtRatio > 0.55 ? -8.0 : 0.0)
            + (str_contains($contract, 'cdi') || str_contains($contract, 'fonctionnaire') ? 3.0 : 0.0),
            1.0,
            99.0
        ), 1);
        $failure = round($this->clamp(100.0 - $approval, 1.0, 99.0), 1);

        $riskLevel = match (true) {
            $score >= 75.0 && $debtRatio <= 0.40 => 'Faible',
            $score >= 55.0 && $debtRatio <= 0.55 => 'Moyen',
            default => 'Eleve',
        };
        $status = match (true) {
            $approval >= 70.0 => 'Accepte',
            $approval >= 45.0 => 'En attente',
            default => 'Refuse',
        };

        return [
            'provider' => 'Fallback',
            'approval_probability' => $approval,
            'failure_probability' => $failure,
            'score' => $score,
            'risk_level' => $riskLevel,
            'suggested_status' => $status,
            'explanation' => sprintf(
                'Ratio endettement %.1f%%, autofinancement %.1f%%, score estime %.1f/100.',
                $debtRatio * 100,
                $fundingRatio * 100,
                $score
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildFallbackGuaranteeDescription(array $payload): string
    {
        $type = trim((string) ($payload['typeGarantie'] ?? 'garantie'));
        $owner = trim((string) ($payload['nomGarant'] ?? 'le garant'));
        $address = trim((string) ($payload['adresseBien'] ?? 'adresse non precisee'));
        $estimated = max(0.0, (float) ($payload['valeurEstimee'] ?? 0));
        $retained = max(0.0, (float) ($payload['valeurRetenue'] ?? 0));

        $description = sprintf(
            'Garantie de type %s appartenant a %s, localisee a %s, evaluee a %.2f DT avec une valeur retenue de %.2f DT pour etude bancaire.',
            $type !== '' ? $type : 'garantie',
            $owner !== '' ? $owner : 'le garant',
            $address !== '' ? $address : 'adresse non precisee',
            $estimated,
            $retained
        );

        return mb_substr($description, 0, 240, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildGuaranteeDescriptionPrompt(array $payload): string
    {
        return sprintf(
            "Tu es un assistant bancaire francophone. Redige une description professionnelle et concise pour un dossier de garantie bancaire.\n".
            "Contraintes:\n".
            "- une seule phrase\n".
            "- ton professionnel\n".
            "- maximum 240 caracteres\n".
            "- sans markdown, sans liste, sans guillemets\n".
            "Donnees:\n".
            "Type de garantie: %s\n".
            "Valeur estimee: %.2f DT\n".
            "Valeur retenue: %.2f DT\n".
            "Date d'evaluation: %s\n".
            "Nom du garant: %s\n".
            "Adresse du bien: %s\n".
            "Reponse attendue: une description finale directement exploitable dans le formulaire.",
            trim((string) ($payload['typeGarantie'] ?? 'Garantie')),
            max(0.0, (float) ($payload['valeurEstimee'] ?? 0)),
            max(0.0, (float) ($payload['valeurRetenue'] ?? 0)),
            trim((string) ($payload['dateEvaluation'] ?? 'Non precisee')),
            trim((string) ($payload['nomGarant'] ?? 'Garant non precise')),
            trim((string) ($payload['adresseBien'] ?? 'Adresse non precisee'))
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   provider: string,
     *   intent: string,
     *   title: string,
     *   answer: string,
     *   decision: string,
     *   score: float,
     *   risk_level: string,
     *   metrics: array<int, array{label: string, value: string}>,
     *   recommendations: array<int, string>,
     *   offers: array<int, array{
     *     bank: string,
     *     rate: string,
     *     monthly: string,
     *     total_cost: string,
     *     interest: string,
     *     status: string,
     *     reason: string
     *   }>,
     *   best_offer: array<string, string>,
     *   constraints: array<int, array{label: string, status: string, detail: string}>
     * }
     */
    private function buildFallbackCreditAssistant(array $context): array
    {
        $intent = $this->resolveCreditAssistantIntent($context);
        $candidate = $this->resolveCreditAssistantCandidate($context);
        $portfolio = is_array($context['portfolio'] ?? null) ? $context['portfolio'] : [];
        $selectedGuarantee = is_array($candidate['garantie'] ?? null) ? $candidate['garantie'] : [];
        $eligibility = $this->buildFallbackEligibility($candidate);

        $amount = max(0.0, (float) ($candidate['montantDemande'] ?? 0));
        $autoFunding = max(0.0, (float) ($candidate['autofinancement'] ?? 0));
        $duration = max(0, (int) ($candidate['duree'] ?? 0));
        $rate = max(0.0, (float) ($candidate['tauxInteret'] ?? 0));
        $salary = max(0.0, (float) ($candidate['salaire'] ?? 0));
        $monthly = max(0.0, (float) ($candidate['mensualite'] ?? 0));
        $retained = max(0.0, (float) ($selectedGuarantee['valeurRetenue'] ?? 0));
        $estimated = max(0.0, (float) ($selectedGuarantee['valeurEstimee'] ?? 0));
        $coverage = $amount > 0 ? round(($retained / $amount) * 100, 1) : 0.0;
        $retainedRatio = $estimated > 0 ? round(($retained / $estimated) * 100, 1) : 0.0;
        $monthlyEstimate = $monthly > 0 ? $monthly : $this->estimateMonthlyPayment($amount, $autoFunding, $duration, $rate);
        $offers = $this->buildCreditAssistantOffers($candidate, $eligibility, $portfolio);
        $bestOffer = $this->resolveBestCreditAssistantOffer($offers);
        $constraints = $this->buildCreditAssistantConstraints($candidate, $portfolio, $monthlyEstimate, $coverage);
        $decision = $this->resolveCreditAssistantDecision((string) ($eligibility['suggested_status'] ?? 'En attente'), $constraints);

        $title = 'Assistant credit';
        $answer = 'Je peux vous aider a analyser un dossier de credit ou votre portefeuille.';
        $metrics = [];
        $recommendations = [];

        switch ($intent) {
            case 'simulation':
                $title = 'Simulation de credit';
                if ($amount <= 0 || $duration <= 0) {
                    $answer = 'Renseignez au minimum le montant et la duree du credit ou choisissez un credit cible pour lancer une simulation fiable et afficher le comparatif des vraies banques.';
                    $recommendations = [
                        'Choisissez un credit cible dans le chatbot pour reutiliser automatiquement un dossier existant.',
                        'Saisissez le montant et la duree pour comparer NEXORA Bank, BIAT, Amen Bank, Attijari bank et UIB.',
                    ];
                } else {
                    $monthly = $monthlyEstimate;
                    $principal = max(0.0, $amount - $autoFunding);
                    $totalRepayment = $monthly * $duration;
                    $interestCost = max(0.0, $totalRepayment - $principal);
                    $answer = sprintf(
                        'Pour un montant de %.2f DT sur %d mois, la mensualite estimee est de %.2f DT et les interets autour de %.2f DT. La meilleure offre identifiee est %s.',
                        $amount,
                        $duration,
                        $monthly,
                        $interestCost,
                        (string) ($bestOffer['bank'] ?? 'NEXORA Bank')
                    );
                    $metrics = [
                        ['label' => 'Montant demande', 'value' => number_format($amount, 2, '.', ' ').' DT'],
                        ['label' => 'Autofinancement', 'value' => number_format($autoFunding, 2, '.', ' ').' DT'],
                        ['label' => 'Mensualite estimee', 'value' => number_format($monthly, 2, '.', ' ').' DT'],
                        ['label' => 'Interets estimes', 'value' => number_format($interestCost, 2, '.', ' ').' DT'],
                    ];
                    $recommendations = [
                        'Gardez une mensualite idealement sous 35% de votre revenu mensuel.',
                        'Augmenter l autofinancement reduit le cout global et le risque.',
                        (string) ($bestOffer['reason'] ?? 'Comparez plusieurs durees avant de valider la demande.'),
                    ];
                }
                break;

            case 'score':
                $title = 'Score de credit';
                $answer = sprintf(
                    'Le dossier ressort avec un score estime de %.1f/100 et un risque %s. %s',
                    (float) ($eligibility['score'] ?? 0),
                    (string) ($eligibility['risk_level'] ?? 'Moyen'),
                    (string) ($eligibility['explanation'] ?? '')
                );
                $metrics = [
                    ['label' => 'Score', 'value' => number_format((float) ($eligibility['score'] ?? 0), 1, '.', ' ').' /100'],
                    ['label' => 'Prob. acceptation', 'value' => number_format((float) ($eligibility['approval_probability'] ?? 0), 1, '.', ' ').' %'],
                    ['label' => 'Prob. refus', 'value' => number_format((float) ($eligibility['failure_probability'] ?? 0), 1, '.', ' ').' %'],
                    ['label' => 'Risque', 'value' => (string) ($eligibility['risk_level'] ?? 'Moyen')],
                ];
                $recommendations = [
                    'Renforcez la stabilite du dossier avec des revenus justificatifs recents.',
                    'Limitez le ratio d endettement avant de soumettre la demande.',
                    'Ajoutez une garantie retenue suffisante pour ameliorer le scoring.',
                    (string) ($bestOffer['reason'] ?? 'Conservez l offre au cout global le plus faible.'),
                ];
                break;

            case 'garantie':
                $title = 'Analyse des garanties';
                if ($selectedGuarantee === []) {
                    $answer = sprintf(
                        'Vous avez %d garantie(s) en portefeuille. Selectionnez ou associez une garantie a un credit pour obtenir une analyse plus precise.',
                        (int) ($portfolio['garanties_total'] ?? 0)
                    );
                    $metrics = [
                        ['label' => 'Garanties total', 'value' => (string) ((int) ($portfolio['garanties_total'] ?? 0))],
                        ['label' => 'Credits couverts', 'value' => (string) ((int) ($portfolio['credits_total'] ?? 0))],
                    ];
                    $recommendations = [
                        'Associez les garanties les plus solides aux credits les plus eleves.',
                        'Mettez a jour les valeurs retenues si les estimations ont change.',
                    ];
                } else {
                    $answer = sprintf(
                        'La garantie %s couvre %.1f%% du montant du credit avec une retention de %.1f%% de sa valeur estimee.',
                        trim((string) ($selectedGuarantee['typeGarantie'] ?? 'selectionnee')) !== '' ? (string) $selectedGuarantee['typeGarantie'] : 'selectionnee',
                        $coverage,
                        $retainedRatio
                    );
                    $metrics = [
                        ['label' => 'Valeur estimee', 'value' => number_format($estimated, 2, '.', ' ').' DT'],
                        ['label' => 'Valeur retenue', 'value' => number_format($retained, 2, '.', ' ').' DT'],
                        ['label' => 'Couverture credit', 'value' => number_format($coverage, 1, '.', ' ').' %'],
                        ['label' => 'Retention', 'value' => number_format($retainedRatio, 1, '.', ' ').' %'],
                    ];
                    $recommendations = [
                        $coverage >= 60.0 ? 'La couverture est rassurante pour le dossier.' : 'Essayez d augmenter la couverture retenue de la garantie.',
                        'Verifiez que les justificatifs et la date d evaluation sont a jour.',
                        'Croisez la garantie avec le niveau de risque du credit associe.',
                    ];
                }
                break;

            case 'decision':
                $title = 'Decision automatique';
                $answer = sprintf(
                    'Decision proposee: %s. Le score estime est de %.1f/100 avec un risque %s.',
                    $decision,
                    (float) ($eligibility['score'] ?? 0),
                    (string) ($eligibility['risk_level'] ?? 'Moyen')
                );
                $metrics = [
                    ['label' => 'Decision', 'value' => $decision],
                    ['label' => 'Score', 'value' => number_format((float) ($eligibility['score'] ?? 0), 1, '.', ' ').' /100'],
                    ['label' => 'Risque', 'value' => (string) ($eligibility['risk_level'] ?? 'Moyen')],
                    ['label' => 'Mensualite', 'value' => number_format($monthly, 2, '.', ' ').' DT'],
                ];
                $recommendations = [
                    $decision === 'Accepte' ? 'Le dossier peut passer en validation finale.' : 'Ajoutez des pieces ou garanties avant une decision definitive.',
                    $salary > 0 && $monthly > 0 && ($monthly / max(1.0, $salary)) > 0.4 ? 'Le ratio mensualite / salaire reste a surveiller.' : 'Le ratio de charge reste compatible avec une etude favorable.',
                    'Documentez la justification de decision dans le dossier client.',
                    (string) ($bestOffer['reason'] ?? 'La meilleure offre reste celle au cout global le plus bas.'),
                ];
                break;

            case 'recommendation':
            default:
                $title = 'Recommandations personnalisees';
                $answer = sprintf(
                    'Vous avez %d credit(s), %d garantie(s) et un risque moyen de %.1f/100. Voici les priorites conseillees.',
                    (int) ($portfolio['credits_total'] ?? 0),
                    (int) ($portfolio['garanties_total'] ?? 0),
                    (float) ($portfolio['average_risk'] ?? 0)
                );
                $metrics = [
                    ['label' => 'Credits actifs', 'value' => (string) ((int) ($portfolio['credits_total'] ?? 0))],
                    ['label' => 'Garanties', 'value' => (string) ((int) ($portfolio['garanties_total'] ?? 0))],
                    ['label' => 'Risque moyen', 'value' => number_format((float) ($portfolio['average_risk'] ?? 0), 1, '.', ' ').' /100'],
                    ['label' => 'Mensualites totales', 'value' => number_format((float) ($portfolio['total_monthly'] ?? 0), 2, '.', ' ').' DT'],
                ];
                $recommendations = [
                    'Priorisez les credits en attente avec la meilleure couverture de garantie.',
                    'Reduisez les mensualites cumulees si le portefeuille devient trop charge.',
                    'Mettez a jour les garanties anciennes avant une nouvelle demande.',
                    (string) ($bestOffer['reason'] ?? 'Arbitrez vos dossiers selon le meilleur couple mensualite / cout total.'),
                ];
                break;
        }

        return [
            'provider' => 'Fallback',
            'intent' => $intent,
            'title' => $title,
            'answer' => $answer,
            'decision' => $decision,
            'score' => round((float) ($eligibility['score'] ?? 0), 1),
            'risk_level' => (string) ($eligibility['risk_level'] ?? 'Moyen'),
            'metrics' => array_values(array_slice($metrics, 0, 4)),
            'recommendations' => array_values(array_slice(array_filter($recommendations, static fn ($item): bool => trim((string) $item) !== ''), 0, 4)),
            'offers' => $offers,
            'best_offer' => $bestOffer,
            'constraints' => $constraints,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *   intent: string,
     *   title: string,
     *   answer: string,
     *   decision: string,
     *   score: float,
     *   risk_level: string,
     *   metrics: array<int, array{label: string, value: string}>,
     *   recommendations: array<int, string>,
     *   offers: array<int, array{
     *     bank: string,
     *     rate: string,
     *     monthly: string,
     *     total_cost: string,
     *     interest: string,
     *     status: string,
     *     reason: string
     *   }>,
     *   best_offer: array<string, string>,
     *   constraints: array<int, array{label: string, status: string, detail: string}>
     * } $fallback
     */
    private function buildCreditAssistantPrompt(array $context, array $fallback): string
    {
        return sprintf(
            "Tu es un chatbot bancaire intelligent integre a une application de credits. Reponds STRICTEMENT en JSON pur, sans markdown.\n".
            "Schema JSON attendu:\n".
            "{\n".
            "  \"intent\": \"simulation|score|garantie|decision|recommendation\",\n".
            "  \"title\": \"titre court\",\n".
            "  \"answer\": \"reponse utile en francais, 2 a 5 phrases\",\n".
            "  \"decision\": \"Accepte|Refuse|A etudier\",\n".
            "  \"score\": number,\n".
            "  \"risk_level\": \"Faible|Moyen|Eleve\",\n".
            "  \"metrics\": [{\"label\": \"...\", \"value\": \"...\"}],\n".
            "  \"recommendations\": [\"...\", \"...\", \"...\"],\n".
            "  \"offers\": [{\"bank\": \"...\", \"rate\": \"...\", \"teg\": \"...\", \"monthly\": \"...\", \"total_cost\": \"...\", \"interest\": \"...\", \"status\": \"...\", \"reason\": \"...\", \"feature\": \"...\", \"condition\": \"...\"}],\n".
            "  \"best_offer\": {\"bank\": \"...\", \"rate\": \"...\", \"teg\": \"...\", \"monthly\": \"...\", \"total_cost\": \"...\", \"interest\": \"...\", \"status\": \"...\", \"reason\": \"...\", \"feature\": \"...\", \"condition\": \"...\"},\n".
            "  \"constraints\": [{\"label\": \"...\", \"status\": \"ok|warn|block\", \"detail\": \"...\"}]\n".
            "}\n".
            "Regles:\n".
            "- appuie-toi sur le contexte donne, pas sur des generalites\n".
            "- si selected_credit ou selected_garantie sont renseignes, focalise toute reponse sur cette cible\n".
            "- metrics: 2 a 4 elements maximum\n".
            "- recommendations: 2 a 4 actions concretes maximum\n".
            "- compare au moins 3 offres bancaires quand l intent concerne la simulation, la decision ou les recommandations\n".
            "- choisis best_offer selon le meilleur cout global si le dossier est viable\n".
            "- constraints doit refleter les vraies contraintes metier du contexte\n".
            "- decision doit etre cohérente avec score et risque\n".
            "- answer doit etre directement exploitable par un utilisateur final\n".
            "Contexte applicatif:\n%s\n".
            "Reference fallback locale:\n%s",
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array{
     *   provider: string,
     *   intent: string,
     *   title: string,
     *   answer: string,
     *   decision: string,
     *   score: float,
     *   risk_level: string,
     *   metrics: array<int, array{label: string, value: string}>,
     *   recommendations: array<int, string>,
     *   offers: array<int, array{
     *     bank: string,
     *     rate: string,
     *     monthly: string,
     *     total_cost: string,
     *     interest: string,
     *     status: string,
     *     reason: string
     *   }>,
     *   best_offer: array<string, string>,
     *   constraints: array<int, array{label: string, status: string, detail: string}>
     * } $fallback
     * @return array{
     *   provider: string,
     *   intent: string,
     *   title: string,
     *   answer: string,
     *   decision: string,
     *   score: float,
     *   risk_level: string,
     *   metrics: array<int, array{label: string, value: string}>,
     *   recommendations: array<int, string>,
     *   offers: array<int, array{
     *     bank: string,
     *     rate: string,
     *     monthly: string,
     *     total_cost: string,
     *     interest: string,
     *     status: string,
     *     reason: string
     *   }>,
     *   best_offer: array<string, string>,
     *   constraints: array<int, array{label: string, status: string, detail: string}>
     * }
     */
    private function normalizeCreditAssistantResult(array $data, array $fallback): array
    {
        $intent = $this->normalizeCreditAssistantIntent((string) ($data['intent'] ?? $fallback['intent']));
        $title = trim((string) ($data['title'] ?? ''));
        $answer = trim((string) ($data['answer'] ?? ''));
        $decision = $this->normalizeAssistantDecision((string) ($data['decision'] ?? $fallback['decision']));
        $score = $this->numberFromMixed($data['score'] ?? null);
        $riskLevel = $this->normalizeRiskLabel((string) ($data['risk_level'] ?? $fallback['risk_level']));

        $metrics = [];
        foreach ((array) ($data['metrics'] ?? []) as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $label = trim((string) ($metric['label'] ?? ''));
            $value = trim((string) ($metric['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $metrics[] = ['label' => $label, 'value' => $value];
            if (count($metrics) >= 4) {
                break;
            }
        }

        $recommendations = [];
        foreach ((array) ($data['recommendations'] ?? []) as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $recommendations[] = $value;
            if (count($recommendations) >= 4) {
                break;
            }
        }

        $offers = [];
        foreach ((array) ($data['offers'] ?? []) as $offer) {
            if (!is_array($offer)) {
                continue;
            }
            $bank = trim((string) ($offer['bank'] ?? ''));
            if ($bank === '') {
                continue;
            }
            $offers[] = [
                'bank' => $bank,
                'rate' => trim((string) ($offer['rate'] ?? '')),
                'teg' => trim((string) ($offer['teg'] ?? '')),
                'monthly' => trim((string) ($offer['monthly'] ?? '')),
                'total_cost' => trim((string) ($offer['total_cost'] ?? '')),
                'interest' => trim((string) ($offer['interest'] ?? '')),
                'status' => trim((string) ($offer['status'] ?? '')),
                'reason' => trim((string) ($offer['reason'] ?? '')),
                'feature' => trim((string) ($offer['feature'] ?? '')),
                'condition' => trim((string) ($offer['condition'] ?? '')),
            ];
            if (count($offers) >= 5) {
                break;
            }
        }

        $bestOffer = [];
        if (is_array($data['best_offer'] ?? null)) {
            foreach (['bank', 'rate', 'teg', 'monthly', 'total_cost', 'interest', 'status', 'reason', 'feature', 'condition'] as $field) {
                $value = trim((string) (($data['best_offer'][$field] ?? '')));
                if ($value !== '') {
                    $bestOffer[$field] = $value;
                }
            }
        }

        $constraints = [];
        foreach ((array) ($data['constraints'] ?? []) as $constraint) {
            if (!is_array($constraint)) {
                continue;
            }
            $label = trim((string) ($constraint['label'] ?? ''));
            $detail = trim((string) ($constraint['detail'] ?? ''));
            if ($label === '' || $detail === '') {
                continue;
            }
            $constraints[] = [
                'label' => $label,
                'status' => $this->normalizeConstraintStatus((string) ($constraint['status'] ?? 'warn')),
                'detail' => $detail,
            ];
            if (count($constraints) >= 5) {
                break;
            }
        }

        return [
            'provider' => 'OpenRouter',
            'intent' => $intent,
            'title' => $title !== '' ? $title : $fallback['title'],
            'answer' => $answer !== '' ? $answer : $fallback['answer'],
            'decision' => $decision,
            'score' => round($this->clamp($score ?? (float) $fallback['score'], 0.0, 100.0), 1),
            'risk_level' => $riskLevel,
            'metrics' => $metrics !== [] ? $metrics : $fallback['metrics'],
            'recommendations' => $recommendations !== [] ? $recommendations : $fallback['recommendations'],
            'offers' => $offers !== [] ? $offers : $fallback['offers'],
            'best_offer' => $bestOffer !== [] ? $bestOffer : $fallback['best_offer'],
            'constraints' => $constraints !== [] ? $constraints : $fallback['constraints'],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function resolveCreditAssistantCandidate(array $context): array
    {
        $draft = is_array($context['draft'] ?? null) ? $context['draft'] : [];
        $selectedCredit = is_array($context['selected_credit'] ?? null) ? $context['selected_credit'] : [];
        $selectedGuarantee = is_array($context['selected_garantie'] ?? null) ? $context['selected_garantie'] : [];
        $hasSelectedCredit = $selectedCredit !== [];

        $candidate = $selectedCredit !== [] ? $selectedCredit : [];
        foreach ([
            'idCredit',
            'typeCredit',
            'montantDemande',
            'autofinancement',
            'duree',
            'tauxInteret',
            'mensualite',
            'montantAccorde',
            'salaire',
            'typeContrat',
            'ancienneteAnnees',
            'statut',
            'risk_score',
            'garantie',
        ] as $field) {
            if ($hasSelectedCredit) {
                continue;
            }

            if (array_key_exists($field, $draft) && $draft[$field] !== '' && $draft[$field] !== null) {
                $candidate[$field] = $draft[$field];
            }
        }

        if ($selectedGuarantee !== []) {
            $candidate['garantie'] = $selectedGuarantee;
        } elseif (!isset($candidate['garantie']) && is_array($selectedCredit['garantie'] ?? null)) {
            $candidate['garantie'] = $selectedCredit['garantie'];
        }

        return $candidate;
    }

    private function resolveCreditAssistantIntent(array $context): string
    {
        $raw = trim((string) ($context['intent'] ?? ''));
        if ($raw !== '') {
            return $this->normalizeCreditAssistantIntent($raw);
        }

        $text = $this->normalizeText(trim((string) ($context['message'] ?? '')));

        return match (true) {
            str_contains($text, 'simul') => 'simulation',
            str_contains($text, 'score') || str_contains($text, 'scoring') => 'score',
            str_contains($text, 'garant') => 'garantie',
            str_contains($text, 'decision') || str_contains($text, 'accepte') || str_contains($text, 'refuse') => 'decision',
            default => 'recommendation',
        };
    }

    private function normalizeCreditAssistantIntent(string $value): string
    {
        $normalized = $this->normalizeText($value);

        return match (true) {
            str_contains($normalized, 'simul') || str_contains($normalized, 'simulation') => 'simulation',
            str_contains($normalized, 'score') => 'score',
            str_contains($normalized, 'garant') => 'garantie',
            str_contains($normalized, 'decision') => 'decision',
            str_contains($normalized, 'recommend') || str_contains($normalized, 'recommand') || str_contains($normalized, 'conseil') => 'recommendation',
            default => 'recommendation',
        };
    }

    private function normalizeAssistantDecision(string $value): string
    {
        $normalized = $this->normalizeText($value);

        return match (true) {
            str_contains($normalized, 'accept') || str_contains($normalized, 'accepte') => 'Accepte',
            str_contains($normalized, 'refuse') || str_contains($normalized, 'reject') => 'Refuse',
            default => 'A etudier',
        };
    }

    private function mapAssistantDecision(string $status): string
    {
        return match ($this->normalizeStatusLabel($status)) {
            'Accepte' => 'Accepte',
            'Refuse' => 'Refuse',
            default => 'A etudier',
        };
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array{
     *   provider: string,
     *   approval_probability: float,
     *   failure_probability: float,
     *   score: float,
     *   risk_level: string,
     *   suggested_status: string,
     *   explanation: string
     * } $eligibility
     * @param array<string, mixed> $portfolio
     * @return array<int, array{
     *   bank: string,
     *   rate: string,
     *   monthly: string,
     *   total_cost: string,
     *   interest: string,
     *   status: string,
     *   reason: string
     * }>
     */
    private function buildCreditAssistantOffers(array $candidate, array $eligibility, array $portfolio): array
    {
        $amount = max(0.0, (float) ($candidate['montantDemande'] ?? 0));
        $autoFunding = max(0.0, (float) ($candidate['autofinancement'] ?? 0));
        $duration = max(0, (int) ($candidate['duree'] ?? 0));
        $salary = max(0.0, (float) ($candidate['salaire'] ?? 0));
        $score = (float) ($eligibility['score'] ?? 0);
        $baseRate = (float) ($candidate['tauxInteret'] ?? 0);
        if ($baseRate <= 0.0) {
            $baseRate = $this->defaultAssistantBaseRate((string) ($candidate['typeCredit'] ?? ''));
        }

        if ($amount <= 0.0 || $duration <= 0) {
            return [];
        }

        $principal = max(0.0, $amount - $autoFunding);
        $discount = $score >= 80.0 ? -0.60 : ($score >= 65.0 ? -0.25 : ($score < 45.0 ? 0.55 : 0.15));
        $definitions = [
            [
                'bank' => 'NEXORA Bank',
                'spread' => -0.42,
                'teg_spread' => 0.30,
                'fee' => 60.0,
                'feature' => 'Partenaire privilegie · Deblocage 48h · Sans frais dossier',
                'condition' => 'Domiciliation salaire requise',
            ],
            [
                'bank' => 'BIAT',
                'spread' => -0.12,
                'teg_spread' => 0.33,
                'fee' => 210.0,
                'feature' => 'Traitement prioritaire et suivi conseiller',
                'condition' => 'Justificatif de revenu recent requis',
            ],
            [
                'bank' => 'Amen Bank',
                'spread' => 0.04,
                'teg_spread' => 0.36,
                'fee' => 165.0,
                'feature' => 'Assurance incluse et mensualite stable',
                'condition' => 'Anciennete professionnelle souhaitee',
            ],
            [
                'bank' => 'Attijari bank',
                'spread' => -0.03,
                'teg_spread' => 0.34,
                'fee' => 185.0,
                'feature' => 'Souplesse de remboursement anticipe',
                'condition' => 'Dossier complet avant validation finale',
            ],
            [
                'bank' => 'UIB',
                'spread' => 0.11,
                'teg_spread' => 0.38,
                'fee' => 175.0,
                'feature' => 'Offre standard avec accompagnement agence',
                'condition' => 'Capacite d endettement a confirmer',
            ],
        ];

        $offers = [];
        foreach ($definitions as $definition) {
            $annualRate = max(0.1, $baseRate + (float) $definition['spread'] + $discount);
            $tegRate = $annualRate + (float) ($definition['teg_spread'] ?? 0.35);
            $monthly = $this->estimateMonthlyPayment($amount, $autoFunding, $duration, $annualRate);
            $totalRepayment = round(($monthly * $duration) + (float) $definition['fee'], 2);
            $interest = round(max(0.0, $totalRepayment - $principal), 2);
            $debtRatio = $salary > 0 ? ($monthly / $salary) : 1.0;
            $status = $debtRatio > 0.55 || ((int) ($portfolio['open_credits'] ?? 0)) >= ((int) ($portfolio['max_active_credits'] ?? 3))
                ? 'Non recommande'
                : ($debtRatio > 0.40 ? 'A etudier' : 'Eligible');
            $reason = match ($status) {
                'Eligible' => (string) $definition['bank'] === 'NEXORA Bank'
                    ? 'Offre maison competitive avec frais dossier reduits et mensualite soutenable.'
                    : 'Mensualite soutenable avec cout global competitif.',
                'A etudier' => (string) $definition['bank'] === 'NEXORA Bank'
                    ? 'Offre Nexora interessante mais ratio d endettement a surveiller.'
                    : 'Mensualite acceptable mais ratio d endettement a surveiller.',
                default => 'Charge mensuelle ou regle metier trop restrictive pour cette offre.',
            };

            $offers[] = [
                'bank' => (string) $definition['bank'],
                'rate' => number_format($annualRate, 2, '.', ' ').' %',
                'teg' => number_format($tegRate, 2, '.', ' ').' %',
                'monthly' => number_format($monthly, 2, '.', ' ').' DT',
                'total_cost' => number_format($totalRepayment, 2, '.', ' ').' DT',
                'interest' => number_format($interest, 2, '.', ' ').' DT',
                'status' => $status,
                'reason' => $reason,
                'feature' => (string) ($definition['feature'] ?? ''),
                'condition' => (string) ($definition['condition'] ?? ''),
            ];
        }

        return $offers;
    }

    /**
     * @param array<int, array{
     *   bank: string,
     *   rate: string,
     *   monthly: string,
     *   total_cost: string,
     *   interest: string,
     *   status: string,
     *   reason: string
     * }> $offers
     * @return array<string, string>
     */
    private function resolveBestCreditAssistantOffer(array $offers): array
    {
        if ($offers === []) {
            return [];
        }

        $eligible = array_values(array_filter(
            $offers,
            static fn (array $offer): bool => ($offer['status'] ?? '') !== 'Non recommande'
        ));
        $pool = $eligible !== [] ? $eligible : $offers;

        usort($pool, function (array $left, array $right): int {
            $costComparison = $this->numberFromMixed($left['total_cost'] ?? '0') <=> $this->numberFromMixed($right['total_cost'] ?? '0');
            if ($costComparison !== 0) {
                return $costComparison;
            }

            $leftIsNexora = trim((string) ($left['bank'] ?? '')) === 'NEXORA Bank';
            $rightIsNexora = trim((string) ($right['bank'] ?? '')) === 'NEXORA Bank';

            return $leftIsNexora === $rightIsNexora ? 0 : ($leftIsNexora ? -1 : 1);
        });

        $best = $pool[0] ?? [];
        if ($best === []) {
            return [];
        }

        if (trim((string) ($best['reason'] ?? '')) === '') {
            $best['reason'] = 'Offre retenue pour son cout global le plus faible.';
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $portfolio
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function buildCreditAssistantConstraints(array $candidate, array $portfolio, float $monthly, float $coverage): array
    {
        $amount = max(0.0, (float) ($candidate['montantDemande'] ?? 0));
        $autoFunding = max(0.0, (float) ($candidate['autofinancement'] ?? 0));
        $salary = max(0.0, (float) ($candidate['salaire'] ?? 0));
        $openCredits = (int) ($portfolio['open_credits'] ?? 0);
        $maxCredits = max(1, (int) ($portfolio['max_active_credits'] ?? 3));
        $debtRatio = $salary > 0 ? round(($monthly / $salary) * 100, 1) : 100.0;
        $contributionRatio = $amount > 0 ? round(($autoFunding / $amount) * 100, 1) : 0.0;

        return [
            [
                'label' => 'Nombre max de credits',
                'status' => $openCredits >= $maxCredits ? 'block' : ($openCredits === ($maxCredits - 1) ? 'warn' : 'ok'),
                'detail' => sprintf('%d credit(s) ouvert(s) sur un maximum de %d.', $openCredits, $maxCredits),
            ],
            [
                'label' => 'Ratio d endettement',
                'status' => $debtRatio > 55.0 ? 'block' : ($debtRatio > 40.0 ? 'warn' : 'ok'),
                'detail' => sprintf('Le ratio mensualite / salaire est estime a %.1f%%.', $debtRatio),
            ],
            [
                'label' => 'Apport minimum',
                'status' => $contributionRatio < 10.0 ? 'warn' : 'ok',
                'detail' => sprintf('L apport personnel represente %.1f%% du montant demande.', $contributionRatio),
            ],
            [
                'label' => 'Couverture de garantie',
                'status' => $coverage > 0 && $coverage < 50.0 ? 'warn' : ($coverage <= 0 ? 'warn' : 'ok'),
                'detail' => $coverage > 0
                    ? sprintf('La garantie couvre environ %.1f%% du financement.', $coverage)
                    : 'Aucune garantie suffisamment couverte n est associee au dossier.',
            ],
        ];
    }

    /**
     * @param array<int, array{label: string, status: string, detail: string}> $constraints
     */
    private function resolveCreditAssistantDecision(string $status, array $constraints): string
    {
        $normalized = $this->mapAssistantDecision($status);
        $hasBlocker = false;
        $hasWarn = false;

        foreach ($constraints as $constraint) {
            $state = $this->normalizeConstraintStatus((string) ($constraint['status'] ?? 'warn'));
            if ($state === 'block') {
                $hasBlocker = true;
            } elseif ($state === 'warn') {
                $hasWarn = true;
            }
        }

        if ($hasBlocker) {
            return 'Refuse';
        }

        if ($hasWarn && $normalized === 'Accepte') {
            return 'A etudier';
        }

        return $normalized;
    }

    private function normalizeConstraintStatus(string $value): string
    {
        $normalized = $this->normalizeText($value);

        return match (true) {
            str_contains($normalized, 'block') || str_contains($normalized, 'bloqu') => 'block',
            str_contains($normalized, 'ok') || str_contains($normalized, 'valid') => 'ok',
            default => 'warn',
        };
    }

    private function defaultAssistantBaseRate(string $creditType): float
    {
        $normalized = $this->normalizeText($creditType);

        return match (true) {
            str_contains($normalized, 'immob') => 6.8,
            str_contains($normalized, 'auto') || str_contains($normalized, 'vehicule') => 7.4,
            str_contains($normalized, 'pro') => 8.3,
            default => 8.9,
        };
    }

    private function estimateMonthlyPayment(float $amount, float $autoFunding, int $duration, float $rate): float
    {
        $principal = max(0.0, $amount - $autoFunding);
        if ($principal <= 0.0 || $duration <= 0) {
            return 0.0;
        }

        $monthlyRate = $rate / 100 / 12;

        return $monthlyRate <= 0
            ? round($principal / $duration, 2)
            : round(($principal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$duration)), 2);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{
     *   approval_probability: float,
     *   failure_probability: float,
     *   score: float,
     *   risk_level: string,
     *   suggested_status: string
     * } $baseline
     */
    private function buildCreditEligibilityPrompt(array $payload, array $baseline): string
    {
        return sprintf(
            "Tu es un analyste de risque de credit bancaire. Reponds STRICTEMENT en JSON pur (sans markdown, sans texte autour).\n".
            "Schema JSON attendu:\n".
            "{\n".
            "  \"approval_probability\": number,\n".
            "  \"failure_probability\": number,\n".
            "  \"score\": number,\n".
            "  \"risk_level\": \"Faible|Moyen|Eleve\",\n".
            "  \"suggested_status\": \"Accepte|En attente|Refuse\",\n".
            "  \"explanation\": \"phrase courte en francais\"\n".
            "}\n".
            "Contraintes: approval_probability + failure_probability = 100, toutes les probabilites entre 0 et 100, score entre 0 et 100.\n".
            "Donnees dossier:\n%s\n".
            "Repere interne (reference): approbation %.1f, echec %.1f, score %.1f, risque %s, statut %s.\n".
            "Utilise les donnees du dossier avant tout, et garde explanation <= 25 mots.",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (float) $baseline['approval_probability'],
            (float) $baseline['failure_probability'],
            (float) $baseline['score'],
            (string) $baseline['risk_level'],
            (string) $baseline['suggested_status']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonObject(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/```(?:json)?/i', '', $clean) ?? $clean;
        $clean = str_replace('```', '', $clean);

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($clean, $start, $end - $start + 1);
        if ($json === false || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *   provider: string,
     *   approval_probability: float,
     *   failure_probability: float,
     *   score: float,
     *   risk_level: string,
     *   suggested_status: string,
     *   explanation: string
     * }|null
     */
    private function normalizeEligibilityResult(array $data, string $fallbackExplanation): ?array
    {
        $approval = $this->numberFromMixed($data['approval_probability'] ?? null);
        $failure = $this->numberFromMixed($data['failure_probability'] ?? null);
        $score = $this->numberFromMixed($data['score'] ?? null);

        if ($approval === null || $failure === null || $score === null) {
            return null;
        }

        $approval = $this->clamp($approval, 0.0, 100.0);
        $failure = $this->clamp($failure, 0.0, 100.0);

        $sum = $approval + $failure;
        if ($sum > 0) {
            $approval = ($approval / $sum) * 100.0;
            $failure = 100.0 - $approval;
        } else {
            $approval = 50.0;
            $failure = 50.0;
        }

        $score = $this->clamp($score, 0.0, 100.0);
        $risk = $this->normalizeRiskLabel((string) ($data['risk_level'] ?? ''));
        $status = $this->normalizeStatusLabel((string) ($data['suggested_status'] ?? ''));
        $explanation = trim((string) ($data['explanation'] ?? ''));

        return [
            'provider' => 'OpenRouter',
            'approval_probability' => round($approval, 1),
            'failure_probability' => round($failure, 1),
            'score' => round($score, 1),
            'risk_level' => $risk,
            'suggested_status' => $status,
            'explanation' => $explanation !== '' ? $explanation : $fallbackExplanation,
        ];
    }

    private function numberFromMixed(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/-?\d+(?:[.,]\d+)?/', $raw, $matches) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[0]);
    }

    private function normalizeRiskLabel(string $raw): string
    {
        $value = $this->normalizeText($raw);

        return match (true) {
            str_contains($value, 'faible') || str_contains($value, 'low') => 'Faible',
            str_contains($value, 'eleve') || str_contains($value, 'high') => 'Eleve',
            default => 'Moyen',
        };
    }

    private function normalizeStatusLabel(string $raw): string
    {
        $value = $this->normalizeText($raw);

        return match (true) {
            str_contains($value, 'accepte') || str_contains($value, 'accept') || str_contains($value, 'approve') => 'Accepte',
            str_contains($value, 'refuse') || str_contains($value, 'rejet') || str_contains($value, 'fail') => 'Refuse',
            default => 'En attente',
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   provider: string,
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * }
     */
    private function buildFallbackProfileCoach(array $context): array
    {
        $securityScore = $this->clamp((float) ($context['security_score'] ?? 0), 0.0, 100.0);
        $savingsScore = $this->clamp((float) ($context['savings_score'] ?? 0), 0.0, 100.0);
        $predictedSpending = max(0.0, (float) ($context['predicted_spending'] ?? 0));
        $surplus = is_array($context['surplus'] ?? null) ? $context['surplus'] : [];
        $surplusAmount = max(0.0, (float) ($surplus['surplus'] ?? 0));
        $surplusShown = (bool) ($surplus['show'] ?? false);

        $topCategory = 'depenses courantes';
        $topCategories = is_array($context['top_spending_categories'] ?? null) ? $context['top_spending_categories'] : [];
        if ($topCategories !== [] && is_array($topCategories[0] ?? null)) {
            $candidate = trim((string) ($topCategories[0]['category'] ?? ''));
            if ($candidate !== '') {
                $topCategory = $candidate;
            }
        }

        $headline = $securityScore >= 75 && $savingsScore >= 55
            ? 'Profil stable avec potentiel d epargne supplementaire.'
            : 'Profil a surveiller pour renforcer stabilite et epargne.';

        $summary = sprintf(
            'Score securite %.0f/100, score epargne %.0f/100, depense previsionnelle %.2f DT ce mois.',
            $securityScore,
            $savingsScore,
            $predictedSpending
        );

        $risk = $securityScore < 65
            ? 'Le niveau de securite reste moyen: verifier biometrie et activite inhabituelle.'
            : sprintf('Le principal risque porte sur la categorie %s si la tendance continue.', $topCategory);

        $opportunity = $surplusShown && $surplusAmount > 0
            ? sprintf('Un surplus de %.2f DT peut etre dirige vers un coffre des maintenant.', $surplusAmount)
            : 'Une meilleure allocation mensuelle peut augmenter le score epargne rapidement.';

        $actions = [];
        if ($securityScore < 70) {
            $actions[] = 'Activer la biometrie et revoir les alertes de connexion cette semaine.';
        }
        if ($savingsScore < 50) {
            $actions[] = sprintf('Fixer un plafond sur %s pour les 30 prochains jours.', $topCategory);
        }
        if ($surplusShown && $surplusAmount > 0) {
            $actions[] = sprintf('Transferer %.2f DT vers un objectif coffre pour verrouiller le surplus.', max(10.0, $surplusAmount * 0.35));
        }
        $actions[] = 'Relancer une analyse IA apres vos prochains mouvements financiers.';

        $actions = array_values(array_slice($actions, 0, 3));

        return [
            'provider' => 'Fallback',
            'headline' => $headline,
            'summary' => $summary,
            'risk' => $risk,
            'opportunity' => $opportunity,
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * } $baseline
     */
    private function buildProfileCoachPrompt(array $context, array $baseline): string
    {
        return sprintf(
            "Tu es un coach financier bancaire. Reponds STRICTEMENT en JSON pur, sans markdown.\n".
            "Schema JSON attendu:\n".
            "{\n".
            "  \"headline\": \"string court\",\n".
            "  \"summary\": \"string court\",\n".
            "  \"risk\": \"string court\",\n".
            "  \"opportunity\": \"string court\",\n".
            "  \"actions\": [\"action 1\", \"action 2\", \"action 3\"]\n".
            "}\n".
            "Regles:\n".
            "- Langue: francais.\n".
            "- Exactement 3 actions.\n".
            "- Chaque action doit etre concrete, concise, et <= 16 mots.\n".
            "- Pas de promesses impossibles, pas de jargon.\n".
            "Contexte profil (JSON):\n%s\n".
            "Repere interne de coherence:\nheadline=%s\nsummary=%s\nrisk=%s\nopportunity=%s",
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $baseline['headline'],
            $baseline['summary'],
            $baseline['risk'],
            $baseline['opportunity']
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array{
     *   provider: string,
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * } $fallback
     * @return array{
     *   provider: string,
     *   headline: string,
     *   summary: string,
     *   risk: string,
     *   opportunity: string,
     *   actions: array<int, string>
     * }
     */
    private function normalizeProfileCoachResult(array $data, array $fallback): array
    {
        $headline = trim((string) ($data['headline'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        $risk = trim((string) ($data['risk'] ?? ''));
        $opportunity = trim((string) ($data['opportunity'] ?? ''));

        $actions = [];
        $rawActions = $data['actions'] ?? [];
        if (is_array($rawActions)) {
            foreach ($rawActions as $item) {
                $action = trim((string) $item);
                if ($action === '') {
                    continue;
                }
                $actions[] = $action;
                if (count($actions) >= 3) {
                    break;
                }
            }
        }

        foreach ($fallback['actions'] as $item) {
            if (count($actions) >= 3) {
                break;
            }
            if (!in_array($item, $actions, true)) {
                $actions[] = $item;
            }
        }

        return [
            'provider' => 'OpenRouter',
            'headline' => $headline !== '' ? $headline : $fallback['headline'],
            'summary' => $summary !== '' ? $summary : $fallback['summary'],
            'risk' => $risk !== '' ? $risk : $fallback['risk'],
            'opportunity' => $opportunity !== '' ? $opportunity : $fallback['opportunity'],
            'actions' => array_values(array_slice($actions, 0, 3)),
        ];
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
        ]);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Appel OpenRouter specifique au scoring credit avec des parametres optimises.
     */
    private function requestGeminiForCredit(string $prompt): ?string
    {
        return $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.2,
                'maxOutputTokens' => 500,
                'responseMimeType' => 'application/json',
            ],
            20
        );
    }


    private function requestGemini(string $prompt): ?string
    {
        return $this->requestGeminiWithModelFallback(
            $prompt,
            [
                'temperature' => 0.4,
                'maxOutputTokens' => 220,
            ],
            15
        );
    }

    /**
     * @param array<string, mixed> $generationConfig
     */
    private function requestGeminiWithModelFallback(string $prompt, array $generationConfig, int $timeout): ?string
    {
        $this->lastGeminiError = null;

        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            $this->lastGeminiError = 'Missing OPENROUTER_API_KEY';
            return null;
        }

        foreach ($this->candidateModelNames() as $model) {
            try {
                $responseFormat = (string) ($generationConfig['responseMimeType'] ?? '') === 'application/json'
                    ? ['type' => 'json_object']
                    : null;

                $body = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'stream' => false,
                    'temperature' => (float) ($generationConfig['temperature'] ?? 0.2),
                    'max_tokens' => max(1, (int) ($generationConfig['maxOutputTokens'] ?? 300)),
                ];
                if ($responseFormat !== null) {
                    $body['response_format'] = $responseFormat;
                }

                $response = $this->httpClient->request('POST', $this->openRouterEndpoint(), [
                    'headers' => [
                        'Authorization' => 'Bearer '.$apiKey,
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => $this->openRouterReferer(),
                        'X-Title' => $this->openRouterAppName(),
                    ],
                    'json' => $body,
                    'timeout' => $timeout,
                ]);

                $statusCode = $response->getStatusCode();
                $data = $response->toArray(false);
                if ($statusCode >= 400) {
                    $this->lastGeminiError = trim((string) ($data['error']['message'] ?? ('HTTP '.$statusCode)));
                    continue;
                }

                $candidateText = $this->normalizeOpenRouterContent($data['choices'][0]['message']['content'] ?? null);
                if ($candidateText !== '') {
                    return $candidateText;
                }
                $this->lastGeminiError = 'Empty OpenRouter response.';
            } catch (\Throwable $exception) {
                $this->lastGeminiError = 'OpenRouter request failed: '.$exception->getMessage();
                continue;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function candidateModelNames(): array
    {
        $configured = $this->modelName();
        $fallbacks = [
            'openai/gpt-oss-120b:free',
            'openai/gpt-oss-20b:free',
        ];

        $models = array_values(array_filter(array_unique(array_merge([$configured], $fallbacks))));

        return $models;
    }

    private function normalizeOpenRouterContent(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $item) {
            if (is_string($item)) {
                $parts[] = $item;
                continue;
            }

            if (is_array($item)) {
                if (isset($item['text']) && is_string($item['text'])) {
                    $parts[] = $item['text'];
                    continue;
                }
                if (isset($item['type'], $item['text']) && $item['type'] === 'text' && is_string($item['text'])) {
                    $parts[] = $item['text'];
                }
            }
        }

        return trim(implode('', $parts));
    }

    private function buildPrompt(array $context): string
    {
        $nom = trim((string) ($context['nom'] ?? ''));
        $prenom = trim((string) ($context['prenom'] ?? ''));
        $role = trim((string) ($context['role'] ?? 'ROLE_USER'));
        $status = trim((string) ($context['status'] ?? 'PENDING'));
        $note = trim((string) ($context['reason'] ?? $context['prompt'] ?? ''));

        return sprintf(
            "Tu es un assistant de banque en francais. Donne une recommandation concise (max 4 lignes) pour la gestion d'un utilisateur.\nNom: %s %s\nRole: %s\nStatut: %s\nContexte: %s\nReponse attendue: actions concretes pour l'admin.",
            $prenom,
            $nom,
            $role !== '' ? $role : 'ROLE_USER',
            $status !== '' ? $status : 'PENDING',
            $note !== '' ? $note : 'Aucun contexte additionnel'
        );
    }

    private function fallbackAdvice(array $context): string
    {
        $status = strtoupper(trim((string) ($context['status'] ?? 'PENDING')));
        $role = strtoupper(trim((string) ($context['role'] ?? 'ROLE_USER')));
        $nom = trim((string) ($context['prenom'] ?? '')).' '.trim((string) ($context['nom'] ?? ''));
        $nom = trim($nom) !== '' ? trim($nom) : 'Utilisateur';

        $lines = [];
        if ($status === 'PENDING') {
            $lines[] = sprintf('%s est en attente: verifier email, telephone et piece justificative avant approbation.', $nom);
        } elseif ($status === 'BANNED') {
            $lines[] = sprintf('%s est banni: conserver une justification claire et planifier une revue manuelle.', $nom);
        } else {
            $lines[] = sprintf('%s a le statut %s: controler les derniers logs avant toute modification.', $nom, $status);
        }

        if ($role === 'ROLE_ADMIN') {
            $lines[] = 'Compte administrateur: exiger mot de passe fort et biometrie active.';
        } else {
            $lines[] = 'Compte utilisateur: appliquer le principe de moindre privilege.';
        }

        $lines[] = 'Documenter la decision dans les notes admin et notifier le client si le statut change.';

        return implode(' ', $lines);
    }

    private function apiKey(): string
    {
        return trim((string) (
            $_ENV['OPENROUTER_API_KEY']
            ?? $_SERVER['OPENROUTER_API_KEY']
            ?? getenv('OPENROUTER_API_KEY')
            ?? $_ENV['NEXORA_GEMINI_API_KEY']
            ?? $_SERVER['NEXORA_GEMINI_API_KEY']
            ?? getenv('NEXORA_GEMINI_API_KEY')
            ?? $_ENV['GEMINI_API_KEY']
            ?? $_SERVER['GEMINI_API_KEY']
            ?? getenv('GEMINI_API_KEY')
            ?? ''
        ));
    }

    private function modelName(): string
    {
        $model = trim((string) (
            $_ENV['OPENROUTER_MODEL']
            ?? $_SERVER['OPENROUTER_MODEL']
            ?? getenv('OPENROUTER_MODEL')
            ?? $_ENV['GEMINI_MODEL']
            ?? $_SERVER['GEMINI_MODEL']
            ?? getenv('GEMINI_MODEL')
            ?? ''
        ));

        return $model !== '' ? $model : 'openai/gpt-oss-120b:free';
    }

    private function openRouterEndpoint(): string
    {
        $endpoint = trim((string) (
            $_ENV['OPENROUTER_BASE_URL']
            ?? $_SERVER['OPENROUTER_BASE_URL']
            ?? getenv('OPENROUTER_BASE_URL')
            ?? ''
        ));

        return $endpoint !== '' ? $endpoint : 'https://openrouter.ai/api/v1/chat/completions';
    }

    private function openRouterReferer(): string
    {
        return trim((string) (
            $_ENV['OPENROUTER_HTTP_REFERER']
            ?? $_SERVER['OPENROUTER_HTTP_REFERER']
            ?? getenv('OPENROUTER_HTTP_REFERER')
            ?? 'http://127.0.0.1:8001'
        ));
    }

    private function openRouterAppName(): string
    {
        return trim((string) (
            $_ENV['OPENROUTER_APP_NAME']
            ?? $_SERVER['OPENROUTER_APP_NAME']
            ?? getenv('OPENROUTER_APP_NAME')
            ?? 'Nexora Portal'
        ));
    }

    private function isQuotaError(string $message): bool
    {
        $normalized = mb_strtolower($message, 'UTF-8');

        return str_contains($normalized, 'quota')
            || str_contains($normalized, 'resource_exhausted')
            || str_contains($normalized, 'rate limit')
            || str_contains($normalized, 'insufficient')
            || str_contains($normalized, '429');
    }
}
