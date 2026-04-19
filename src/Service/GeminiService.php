<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service IA — utilise l'API Grok (xAI), compatible OpenAI.
 * Conserve le nom GeminiService pour ne pas casser les dépendances existantes.
 */
final class GeminiService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL     = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
        private readonly string $modelName = '',  // conservé pour compatibilité, non utilisé
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== ''
            && !str_contains($this->apiKey, 'REMPLACER')
            && str_starts_with(trim($this->apiKey), 'gsk_');
    }

    public function improveReclamationDescription(string $rawDescription, array $context = []): string
    {
        $type      = trim((string) ($context['type']      ?? ''));
        $categorie = trim((string) ($context['categorie'] ?? ''));
        $montant   = trim((string) ($context['montant']   ?? ''));

        $prompt = sprintf(
            "Tu es un assistant bancaire. Améliore la description suivante d'une réclamation bancaire en la rendant claire, professionnelle et précise (max 3 phrases, en français, sans guillemets ni tirets).\n\nContexte : type = \"%s\", catégorie = \"%s\", montant = %s DT.\n\nDescription originale : \"%s\"\n\nDescription améliorée :",
            $type      !== '' ? $type      : 'Non précisé',
            $categorie !== '' ? $categorie : 'Non précisée',
            $montant   !== '' ? $montant   : '?',
            $rawDescription
        );

        $result = $this->callGrok($prompt, 200);

        return $result !== null ? trim($result) : $rawDescription;
    }

    public function generateUserManagementAdvice(array $context): array
    {
        $nom    = trim((string) ($context['nom']    ?? ''));
        $prenom = trim((string) ($context['prenom'] ?? ''));
        $role   = trim((string) ($context['role']   ?? 'ROLE_USER'));
        $status = trim((string) ($context['status'] ?? 'PENDING'));
        $note   = trim((string) ($context['reason'] ?? $context['prompt'] ?? ''));

        $prompt = sprintf(
            "Tu es un assistant de banque en français. Donne une recommandation concise (max 4 lignes) pour la gestion d'un utilisateur.\nNom: %s %s\nRôle: %s\nStatut: %s\nContexte: %s\nRéponse attendue: actions concrètes pour l'admin.",
            $prenom,
            $nom,
            $role   !== '' ? $role   : 'ROLE_USER',
            $status !== '' ? $status : 'PENDING',
            $note   !== '' ? $note   : 'Aucun contexte additionnel'
        );

        $result = $this->callGrok($prompt, 220);

        if ($result !== null) {
            return ['provider' => 'Grok', 'text' => $result];
        }

        return ['provider' => 'Fallback', 'text' => $this->fallbackAdvice($context)];
    }

    public function analyseReclamations(string $prompt): ?string
    {
        return $this->callGrok($prompt, 800);
    }

    private function callGrok(string $prompt, int $maxTokens = 220): ?string
    {
        $apiKey = trim($this->apiKey);
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.4,
                ],
                'timeout'      => 10,
                'max_duration' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            
            // Gestion des erreurs HTTP
            if ($statusCode === 403) {
                error_log('[Grok API] Erreur 403 : Clé API invalide ou expirée');
                return null;
            }
            
            if ($statusCode === 429) {
                error_log('[Grok API] Erreur 429 : Quota dépassé');
                return null;
            }
            
            if ($statusCode !== 200) {
                error_log('[Grok API] Erreur HTTP ' . $statusCode);
                return null;
            }

            $data = $response->toArray(false);
            $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            return $text !== '' ? $text : null;

        } catch (\Throwable $e) {
            error_log('[Grok API] Exception : ' . $e->getMessage());
            return null;
        }
    }

    private function fallbackAdvice(array $context): string
    {
        $status = strtoupper(trim((string) ($context['status'] ?? 'PENDING')));
        $role   = strtoupper(trim((string) ($context['role']   ?? 'ROLE_USER')));
        $nom    = trim(trim((string) ($context['prenom'] ?? '')) . ' ' . trim((string) ($context['nom'] ?? '')));
        $nom    = $nom !== '' ? $nom : 'Utilisateur';

        $lines = [];
        if ($status === 'PENDING') {
            $lines[] = "$nom est en attente : vérifier email, téléphone et pièce justificative avant approbation.";
        } elseif ($status === 'BANNED') {
            $lines[] = "$nom est banni : conserver une justification claire et planifier une revue manuelle.";
        } else {
            $lines[] = "$nom a le statut $status : contrôler les derniers logs avant toute modification.";
        }

        $lines[] = $role === 'ROLE_ADMIN'
            ? 'Compte administrateur : exiger mot de passe fort et biométrie active.'
            : 'Compte utilisateur : appliquer le principe de moindre privilège.';

        $lines[] = 'Documenter la décision dans les notes admin et notifier le client si le statut change.';

        return implode(' ', $lines);
    }
}
