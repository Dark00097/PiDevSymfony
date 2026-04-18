<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiService
{
    private ?string $lastGeminiError = null;
    private ?string $lastSuccessfulProvider = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->openRouterApiKey() !== '' || $this->geminiApiKey() !== '';
    }

    public function generateUserManagementAdvice(array $context): array
    {
        $prompt = $this->buildPrompt($context);
        $responseText = $this->requestGemini($prompt);

        if ($responseText !== null) {
            return [
                'provider' => $this->lastSuccessfulProvider ?? 'OpenRouter',
                'text' => $responseText,
            ];
        }

        return [
            'provider' => 'Fallback',
            'text' => $this->fallbackAdvice($context),
        ];
    }

    /**
     * Génère un scoring IA pour un dossier de crédit via Gemini.
     *
     * @return array{provider: string, text: string}
     */
    public function generateCreditScoringAdvice(string $prompt): array
    {
        $responseText = $this->requestGeminiForCredit($prompt);

        if ($responseText !== null) {
            return [
                'provider' => $this->lastSuccessfulProvider ?? 'OpenRouter',
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

        $normalized['provider'] = $this->lastSuccessfulProvider ?? 'OpenRouter';

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
        $normalized['provider'] = $this->lastSuccessfulProvider ?? 'OpenRouter';

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
            'provider' => 'Gemini',
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
     * Appel Gemini spécifique au scoring crédit avec des paramètres optimisés.
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
        $this->lastSuccessfulProvider = null;

        if ($this->openRouterApiKey() !== '') {
            $openRouterText = $this->requestViaOpenRouter($prompt, $generationConfig, $timeout);
            if ($openRouterText !== null) {
                return $openRouterText;
            }
        }

        if ($this->geminiApiKey() !== '') {
            $geminiText = $this->requestViaNativeGemini($prompt, $generationConfig, $timeout);
            if ($geminiText !== null) {
                return $geminiText;
            }
        }

        if ($this->lastGeminiError === null) {
            $this->lastGeminiError = 'Missing OPENROUTER_API_KEY and GEMINI_API_KEY';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $generationConfig
     */
    private function requestViaOpenRouter(string $prompt, array $generationConfig, int $timeout): ?string
    {
        $apiKey = $this->openRouterApiKey();
        if ($apiKey === '') {
            return null;
        }

        foreach ($this->openRouterCandidateModelNames() as $model) {
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
                if (str_contains($model, 'nemotron-3-super-120b-a12b:free')) {
                    $body['reasoning'] = ['enabled' => true];
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
                    $this->lastSuccessfulProvider = 'OpenRouter';
                    $this->lastGeminiError = null;

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
    private function openRouterCandidateModelNames(): array
    {
        $configured = $this->openRouterModelName();
        $fallbacks = [
            'openai/gpt-oss-120b:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
            'openai/gpt-oss-20b:free',
        ];

        $models = array_values(array_filter(array_unique(array_merge([$configured], $fallbacks))));

        return $models;
    }

    /**
     * @param array<string, mixed> $generationConfig
     */
    private function requestViaNativeGemini(string $prompt, array $generationConfig, int $timeout): ?string
    {
        $apiKey = $this->geminiApiKey();
        if ($apiKey === '') {
            return null;
        }

        $model = $this->geminiModelName();
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($apiKey)
        );

        $config = [
            'temperature' => (float) ($generationConfig['temperature'] ?? 0.2),
            'maxOutputTokens' => max(16, (int) ($generationConfig['maxOutputTokens'] ?? 300)),
            'thinkingConfig' => [
                'thinkingBudget' => 0,
            ],
        ];
        if (($generationConfig['responseMimeType'] ?? null) === 'application/json') {
            $config['responseMimeType'] = 'application/json';
        }

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $config,
                ],
                'timeout' => $timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400) {
                $this->lastGeminiError = trim((string) ($data['error']['message'] ?? ('HTTP '.$statusCode)));

                return null;
            }

            $candidateText = $this->normalizeGeminiContent($data);
            if ($candidateText === '') {
                $this->lastGeminiError = 'Empty Gemini response.';

                return null;
            }

            $this->lastSuccessfulProvider = 'Gemini';
            $this->lastGeminiError = null;

            return $candidateText;
        } catch (\Throwable $exception) {
            $this->lastGeminiError = 'Gemini request failed: '.$exception->getMessage();

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizeGeminiContent(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return '';
        }

        $buffer = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $buffer[] = $part['text'];
            }
        }

        return trim(implode('', $buffer));
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

    private function openRouterApiKey(): string
    {
        return $this->firstNonEmpty([
            $_ENV['OPENROUTER_API_KEY'] ?? null,
            $_SERVER['OPENROUTER_API_KEY'] ?? null,
            getenv('OPENROUTER_API_KEY'),
        ]);
    }

    private function geminiApiKey(): string
    {
        return $this->firstNonEmpty([
            $_ENV['NEXORA_GEMINI_API_KEY'] ?? null,
            $_SERVER['NEXORA_GEMINI_API_KEY'] ?? null,
            getenv('NEXORA_GEMINI_API_KEY'),
            $_ENV['GEMINI_API_KEY'] ?? null,
            $_SERVER['GEMINI_API_KEY'] ?? null,
            getenv('GEMINI_API_KEY'),
        ]);
    }

    private function openRouterModelName(): string
    {
        return $this->firstNonEmpty([
            $_ENV['OPENROUTER_MODEL'] ?? null,
            $_SERVER['OPENROUTER_MODEL'] ?? null,
            getenv('OPENROUTER_MODEL'),
        ], 'openai/gpt-oss-120b:free');
    }

    private function geminiModelName(): string
    {
        return $this->firstNonEmpty([
            $_ENV['GEMINI_MODEL'] ?? null,
            $_SERVER['GEMINI_MODEL'] ?? null,
            getenv('GEMINI_MODEL'),
        ], 'gemini-2.5-flash');
    }

    private function openRouterEndpoint(): string
    {
        return $this->firstNonEmpty([
            $_ENV['OPENROUTER_BASE_URL'] ?? null,
            $_SERVER['OPENROUTER_BASE_URL'] ?? null,
            getenv('OPENROUTER_BASE_URL'),
        ], 'https://openrouter.ai/api/v1/chat/completions');
    }

    private function openRouterReferer(): string
    {
        return $this->firstNonEmpty([
            $_ENV['OPENROUTER_HTTP_REFERER'] ?? null,
            $_SERVER['OPENROUTER_HTTP_REFERER'] ?? null,
            getenv('OPENROUTER_HTTP_REFERER'),
        ], 'http://127.0.0.1:8001');
    }

    private function openRouterAppName(): string
    {
        return $this->firstNonEmpty([
            $_ENV['OPENROUTER_APP_NAME'] ?? null,
            $_SERVER['OPENROUTER_APP_NAME'] ?? null,
            getenv('OPENROUTER_APP_NAME'),
        ], 'Nexora Portal');
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmpty(array $values, string $default = ''): string
    {
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $default;
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
