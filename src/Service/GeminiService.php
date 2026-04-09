<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiService
{
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
                'provider' => 'Gemini',
                'text' => $responseText,
            ];
        }

        return [
            'provider' => 'Fallback',
            'text' => $this->fallbackAdvice($context),
        ];
    }

    private function requestGemini(string $prompt): ?string
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return null;
        }

        $model = $this->modelName();
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($apiKey)
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'json' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 220,
                    ],
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);
            $candidateText = trim((string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));

            return $candidateText !== '' ? $candidateText : null;
        } catch (\Throwable) {
            return null;
        }
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
        return trim((string) ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? ''));
    }

    private function modelName(): string
    {
        $model = trim((string) ($_ENV['GEMINI_MODEL'] ?? $_SERVER['GEMINI_MODEL'] ?? ''));

        return $model !== '' ? $model : 'gemini-2.0-flash';
    }
}
