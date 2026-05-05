<?php

namespace App\Controller\Sections;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Endpoint : POST /api/vault-suggestions
 * Génère 4 recommandations de coffres virtuels via Groq IA
 */
#[Route('/api/vault-suggestions', name: 'api_vault_suggestions', methods: ['POST'])]
final class VaultAiController extends AbstractController
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL   = 'llama-3.1-8b-instant';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $body        = json_decode($request->getContent(), true) ?? [];
            $accountData = $body['account'] ?? [];
            $vaults      = $body['vaults']  ?? [];

            $prompt      = $this->buildPrompt($accountData, $vaults);
            $content     = $this->callGroq($prompt);
            $suggestions = $this->parseResponse($content);

            return new JsonResponse(['suggestions' => $suggestions]);

        } catch (\Throwable $e) {
            // Fallback : on retourne des suggestions par défaut pour ne pas bloquer l'UI
            return new JsonResponse([
                'suggestions' => $this->fallbackSuggestions(),
                'fallback'    => true,
                'debug'       => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE
    // ─────────────────────────────────────────────────────────────

    private function buildPrompt(array $account, array $vaults): string
    {
        $type     = $account['typeCompte']        ?? 'Courant';
        $balance  = (float) ($account['solde']    ?? 0);
        $retrait  = (float) ($account['plafondRetrait']  ?? 0);
        $virement = (float) ($account['plafondVirement'] ?? 0);
        $status   = $account['statutCompte']      ?? 'Actif';
        $nbVaults = count($vaults);

        $existingLines = '';
        foreach ($vaults as $v) {
            $existingLines .= sprintf(
                '- %s : objectif %s DT, actuel %s DT, statut %s' . PHP_EOL,
                $v['nom']             ?? '?',
                $v['objectifMontant'] ?? '0',
                $v['montantActuel']   ?? '0',
                $v['status']          ?? '?'
            );
        }
        if ($existingLines === '') {
            $existingLines = '  (aucun coffre existant)' . PHP_EOL;
        }

        return <<<PROMPT
Tu es un conseiller financier expert dans une banque numérique tunisienne appelée Nexora.
Ta mission : recommander exactement 4 coffres virtuels bien adaptés et utiles pour aider les clients à atteindre leurs objectifs financiers, améliorer leur épargne et sécuriser leur avenir.

Profil du client :
- Type de compte   : {$type}
- Solde actuel     : {$balance} DT
- Plafond retrait  : {$retrait} DT
- Plafond virement : {$virement} DT
- Statut compte    : {$status}
- Coffres existants ({$nbVaults}) :
{$existingLines}

Instructions :
- Propose des coffres DIFFÉRENTS de ceux déjà existants
- Adapte les objectifs au solde du client (objectif réaliste = 20% à 300% du solde)
- Varie les catégories pour couvrir urgence, projet, long terme et bien-être
- Rédige des descriptions motivantes et personnalisées en français

Génère exactement 4 recommandations. Réponds UNIQUEMENT avec un tableau JSON valide, sans texte avant ni après, sans markdown, sans backticks.

Format strict :
[
  {
    "nom": "Nom court du coffre (max 4 mots)",
    "description": "Description motivante et personnalisée en 1-2 phrases (max 60 mots)",
    "objectifMontant": 5000,
    "dureeEstimee": "6 mois",
    "categorie": "urgence",
    "icon": "fa-shield-heart",
    "couleur": "#14b8a6",
    "priorite": "haute",
    "avantages": ["avantage concret 1", "avantage concret 2", "avantage concret 3"]
  }
]

Valeurs autorisées pour "categorie" : urgence, voyage, projet, retraite, education, sante, loisir, immobilier
Valeurs autorisées pour "icon"      : fa-shield-heart, fa-plane, fa-house, fa-graduation-cap, fa-heart-pulse, fa-gamepad, fa-piggy-bank, fa-briefcase
Valeurs autorisées pour "couleur"   : #14b8a6, #3b82f6, #8b5cf6, #f59e0b, #ef4444, #10b981, #f97316, #06b6d4
Valeurs autorisées pour "priorite"  : haute, moyenne, faible
PROMPT;
    }

    private function groqApiKey(): string
    {
        return (string) ($_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: '');
    }

    private function callGroq(string $prompt): string
    {
        $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey(),
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::GROQ_MODEL,
                'temperature' => 0.75,
                'max_tokens'  => 1800,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'timeout' => 30,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $statusCode = $response->getStatusCode();
        $data       = $response->toArray(false);

        if ($statusCode !== 200) {
            $msg = $data['error']['message'] ?? ('Groq HTTP ' . $statusCode);
            throw new \RuntimeException($msg);
        }

        return trim($data['choices'][0]['message']['content'] ?? '[]');
    }

    private function parseResponse(string $content): array
    {
        // Nettoyer les blocs markdown éventuels
        $content = preg_replace('/```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/```\s*/i', '', $content);
        $content = trim($content);

        // Extraire uniquement le tableau JSON
        $start = strpos($content, '[');
        $end   = strrpos($content, ']');
        if ($start !== false && $end !== false) {
            $content = substr($content, $start, $end - $start + 1);
        }

        $suggestions = json_decode($content, true);

        if (!is_array($suggestions) || empty($suggestions)) {
            return $this->fallbackSuggestions();
        }

        return array_slice($suggestions, 0, 4);
    }

    private function fallbackSuggestions(): array
    {
        return [
            [
                'nom'             => "Fonds d'urgence",
                'description'     => "Constituez une réserve de 3 à 6 mois de dépenses pour faire face aux imprévus sans stress financier.",
                'objectifMontant' => 3000,
                'dureeEstimee'    => '6 mois',
                'categorie'       => 'urgence',
                'icon'            => 'fa-shield-heart',
                'couleur'         => '#ef4444',
                'priorite'        => 'haute',
                'avantages'       => ['Sécurité financière immédiate', 'Accès rapide aux fonds', "Tranquillité d'esprit garantie"],
            ],
            [
                'nom'             => 'Projet Voyage',
                'description'     => "Épargnez pour votre prochain grand voyage et profitez-en en toute sérénité sans vous endetter.",
                'objectifMontant' => 5000,
                'dureeEstimee'    => '12 mois',
                'categorie'       => 'voyage',
                'icon'            => 'fa-plane',
                'couleur'         => '#3b82f6',
                'priorite'        => 'moyenne',
                'avantages'       => ['Planification facile', 'Motivation régulière', 'Objectif concret et mesurable'],
            ],
            [
                'nom'             => 'Épargne Retraite',
                'description'     => "Préparez votre avenir dès maintenant avec un plan d'épargne long terme régulier et discipliné.",
                'objectifMontant' => 20000,
                'dureeEstimee'    => '5 ans',
                'categorie'       => 'retraite',
                'icon'            => 'fa-piggy-bank',
                'couleur'         => '#8b5cf6',
                'priorite'        => 'moyenne',
                'avantages'       => ['Horizon long terme', 'Capitalisation progressive', 'Sécurité future garantie'],
            ],
            [
                'nom'             => 'Formation & Éducation',
                'description'     => "Investissez dans vos compétences ou celles de vos enfants pour un avenir plus compétitif et épanouissant.",
                'objectifMontant' => 8000,
                'dureeEstimee'    => '2 ans',
                'categorie'       => 'education',
                'icon'            => 'fa-graduation-cap',
                'couleur'         => '#10b981',
                'priorite'        => 'faible',
                'avantages'       => ['Retour sur investissement élevé', 'Développement personnel', 'Opportunités professionnelles accrues'],
            ],
        ];
    }
}
