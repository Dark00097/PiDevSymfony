<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PaymentPredictionService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== ''
            && !str_contains($this->apiKey, 'REMPLACER')
            && str_starts_with(trim($this->apiKey), 'gsk_');
    }

    /**
     * Analyse les transactions passées et prédit les paiements futurs
     * 
     * @param array $transactions Liste des transactions de l'utilisateur
     * @return array{predictions: array, advice: string, analysis: string, next_month_forecast: array}
     */
    public function predictFuturePayments(array $transactions): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackPrediction($transactions);
        }

        // Préparer les données pour l'IA
        $transactionSummary = $this->prepareTransactionSummary($transactions);
        
        $prompt = $this->buildPredictionPrompt($transactionSummary);
        
        $result = $this->callGrok($prompt, 1200);
        
        if ($result !== null) {
            return $this->parseAIResponse($result);
        }

        return $this->fallbackPrediction($transactions);
    }

    private function prepareTransactionSummary(array $transactions): array
    {
        $summary = [
            'total_transactions' => count($transactions),
            'categories' => [],
            'monthly_patterns' => [],
            'recent_payments' => [],
            'all_types' => [
                'DEPOT' => ['count' => 0, 'total' => 0, 'amounts' => []],
                'RETRAIT' => ['count' => 0, 'total' => 0, 'amounts' => []],
                'VIREMENT' => ['count' => 0, 'total' => 0, 'amounts' => []],
                'PAIEMENT' => ['count' => 0, 'total' => 0, 'amounts' => []],
            ],
        ];

        $categoryTotals = [];
        $monthlyData = [];

        foreach ($transactions as $transaction) {
            $type = strtoupper(trim((string) ($transaction['typeTransaction'] ?? 'DEPOT')));
            $category = trim((string) ($transaction['categorie'] ?? 'Autre'));
            $amount = (float) ($transaction['montant_value'] ?? 0);
            $date = trim((string) ($transaction['dateTransaction'] ?? ''));
            
            // Agrégation par type de transaction
            if (isset($summary['all_types'][$type])) {
                $summary['all_types'][$type]['count']++;
                $summary['all_types'][$type]['total'] += $amount;
                $summary['all_types'][$type]['amounts'][] = $amount;
            }
            
            // Agrégation par catégorie (pour les paiements)
            if ($type === 'PAIEMENT') {
                if (!isset($categoryTotals[$category])) {
                    $categoryTotals[$category] = ['count' => 0, 'total' => 0, 'amounts' => []];
                }
                $categoryTotals[$category]['count']++;
                $categoryTotals[$category]['total'] += $amount;
                $categoryTotals[$category]['amounts'][] = $amount;
                
                // Garder les 10 derniers paiements
                if (count($summary['recent_payments']) < 10) {
                    $summary['recent_payments'][] = [
                        'category' => $category,
                        'amount' => $amount,
                        'date' => $date,
                    ];
                }
            }
            
            // Agrégation mensuelle
            if ($date !== '') {
                $month = substr($date, 0, 7); // YYYY-MM
                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = [
                        'count' => 0,
                        'total' => 0,
                        'by_type' => [
                            'DEPOT' => 0,
                            'RETRAIT' => 0,
                            'VIREMENT' => 0,
                            'PAIEMENT' => 0,
                        ]
                    ];
                }
                $monthlyData[$month]['count']++;
                $monthlyData[$month]['total'] += $amount;
                if (isset($monthlyData[$month]['by_type'][$type])) {
                    $monthlyData[$month]['by_type'][$type] += $amount;
                }
            }
        }

        // Calculer les moyennes par catégorie
        foreach ($categoryTotals as $category => $data) {
            $summary['categories'][$category] = [
                'count' => $data['count'],
                'total' => $data['total'],
                'average' => $data['count'] > 0 ? $data['total'] / $data['count'] : 0,
            ];
        }

        $summary['monthly_patterns'] = $monthlyData;

        return $summary;
    }

    private function buildPredictionPrompt(array $summary): string
    {
        $categoriesText = '';
        foreach ($summary['categories'] as $category => $data) {
            $categoriesText .= sprintf(
                "- %s: %d paiements, total %.2f DT, moyenne %.2f DT\n",
                $category,
                $data['count'],
                $data['total'],
                $data['average']
            );
        }

        $recentPaymentsText = '';
        foreach (array_slice($summary['recent_payments'], 0, 5) as $payment) {
            $recentPaymentsText .= sprintf(
                "- %s: %.2f DT le %s\n",
                $payment['category'],
                $payment['amount'],
                $payment['date']
            );
        }
        
        // Statistiques par type de transaction
        $typeStatsText = '';
        foreach ($summary['all_types'] as $type => $data) {
            if ($data['count'] > 0) {
                $avg = $data['total'] / $data['count'];
                $typeStatsText .= sprintf(
                    "- %s: %d transactions, total %.2f DT, moyenne %.2f DT\n",
                    $type,
                    $data['count'],
                    $data['total'],
                    $avg
                );
            }
        }
        
        // Patterns mensuels
        $monthlyText = '';
        $recentMonths = array_slice($summary['monthly_patterns'], -3, 3, true);
        foreach ($recentMonths as $month => $data) {
            $monthlyText .= sprintf(
                "- %s: %d transactions, total %.2f DT\n",
                $month,
                $data['count'],
                $data['total']
            );
        }

        return <<<PROMPT
Tu es un conseiller financier expert. Analyse les habitudes financières suivantes et fournis une prédiction complète.

DONNÉES D'ANALYSE:
Total de transactions: {$summary['total_transactions']}

Statistiques par type de transaction:
{$typeStatsText}

Répartition par catégorie (paiements):
{$categoriesText}

Derniers paiements:
{$recentPaymentsText}

Patterns mensuels récents:
{$monthlyText}

INSTRUCTIONS:
Fournis ta réponse au format JSON strict suivant (sans markdown, sans backticks):
{
  "predictions": [
    {
      "category": "nom de la catégorie",
      "estimated_amount": montant estimé en nombre,
      "estimated_date": "date estimée au format YYYY-MM-DD",
      "confidence": "high/medium/low",
      "reason": "explication courte"
    }
  ],
  "next_month_forecast": {
    "total_deposits": montant estimé des dépôts,
    "total_withdrawals": montant estimé des retraits,
    "total_payments": montant estimé des paiements,
    "total_transfers": montant estimé des virements,
    "net_balance": solde net estimé (dépôts - retraits - paiements),
    "confidence": "high/medium/low",
    "key_insights": ["insight 1", "insight 2", "insight 3"]
  },
  "analysis": "analyse globale des habitudes financières en 2-3 phrases",
  "advice": "3 conseils financiers concrets et personnalisés"
}

Prédis:
1. Les 3-5 prochains paiements probables basés sur les patterns observés
2. Les montants totaux estimés pour le mois prochain par type de transaction
3. Le solde net estimé pour le mois prochain
PROMPT;
    }

    private function callGrok(string $prompt, int $maxTokens = 1200): ?string
    {
        $apiKey = trim($this->apiKey);
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.3,
                ],
                'timeout' => 15,
                'max_duration' => 15,
            ]);

            $data = $response->toArray(false);
            $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            return $text !== '' ? $text : null;

        } catch (\Throwable $e) {
            error_log('PaymentPredictionService error: ' . $e->getMessage());
            return null;
        }
    }

    private function parseAIResponse(string $response): array
    {
        // Nettoyer la réponse (enlever les backticks markdown si présents)
        $response = preg_replace('/```json\s*|\s*```/', '', $response);
        $response = trim($response);

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            
            return [
                'predictions' => $data['predictions'] ?? [],
                'next_month_forecast' => $data['next_month_forecast'] ?? $this->getDefaultForecast(),
                'analysis' => $data['analysis'] ?? 'Analyse non disponible',
                'advice' => $data['advice'] ?? 'Conseils non disponibles',
                'provider' => 'Grok AI',
            ];
        } catch (\JsonException $e) {
            error_log('JSON parse error: ' . $e->getMessage());
            error_log('Response was: ' . $response);
            return $this->fallbackPrediction([]);
        }
    }
    
    private function getDefaultForecast(): array
    {
        return [
            'total_deposits' => 0,
            'total_withdrawals' => 0,
            'total_payments' => 0,
            'total_transfers' => 0,
            'net_balance' => 0,
            'confidence' => 'low',
            'key_insights' => ['Données insuffisantes pour une prédiction précise'],
        ];
    }

    private function fallbackPrediction(array $transactions): array
    {
        $categories = [];
        $typeStats = [
            'DEPOT' => ['count' => 0, 'total' => 0],
            'RETRAIT' => ['count' => 0, 'total' => 0],
            'PAIEMENT' => ['count' => 0, 'total' => 0],
            'VIREMENT' => ['count' => 0, 'total' => 0],
        ];
        
        foreach ($transactions as $transaction) {
            $type = strtoupper(trim((string) ($transaction['typeTransaction'] ?? 'DEPOT')));
            $amount = (float) ($transaction['montant_value'] ?? 0);
            
            // Stats par type
            if (isset($typeStats[$type])) {
                $typeStats[$type]['count']++;
                $typeStats[$type]['total'] += $amount;
            }
            
            // Stats par catégorie (paiements uniquement)
            if ($type === 'PAIEMENT') {
                $category = trim((string) ($transaction['categorie'] ?? 'Autre'));
                if (!isset($categories[$category])) {
                    $categories[$category] = ['count' => 0, 'total' => 0];
                }
                $categories[$category]['count']++;
                $categories[$category]['total'] += $amount;
            }
        }

        $predictions = [];
        $today = new \DateTime();
        
        foreach ($categories as $category => $data) {
            if ($data['count'] > 0) {
                $avgAmount = $data['total'] / $data['count'];
                $nextDate = clone $today;
                $nextDate->modify('+' . (7 * count($predictions) + 7) . ' days');
                
                $predictions[] = [
                    'category' => $category,
                    'estimated_amount' => round($avgAmount, 2),
                    'estimated_date' => $nextDate->format('Y-m-d'),
                    'confidence' => 'medium',
                    'reason' => sprintf('Basé sur %d paiements précédents', $data['count']),
                ];
            }
        }
        
        // Calculer les prévisions du mois prochain
        $avgDeposits = $typeStats['DEPOT']['count'] > 0 
            ? $typeStats['DEPOT']['total'] / $typeStats['DEPOT']['count'] 
            : 0;
        $avgWithdrawals = $typeStats['RETRAIT']['count'] > 0 
            ? $typeStats['RETRAIT']['total'] / $typeStats['RETRAIT']['count'] 
            : 0;
        $avgPayments = $typeStats['PAIEMENT']['count'] > 0 
            ? $typeStats['PAIEMENT']['total'] / $typeStats['PAIEMENT']['count'] 
            : 0;
        $avgTransfers = $typeStats['VIREMENT']['count'] > 0 
            ? $typeStats['VIREMENT']['total'] / $typeStats['VIREMENT']['count'] 
            : 0;
        
        // Estimer le nombre de transactions du mois prochain (basé sur la moyenne)
        $totalTransactions = array_sum(array_column($typeStats, 'count'));
        $avgTransactionsPerMonth = max(1, $totalTransactions / 3); // Moyenne sur 3 mois
        
        $nextMonthForecast = [
            'total_deposits' => round($avgDeposits * max(1, $typeStats['DEPOT']['count'] / 3), 2),
            'total_withdrawals' => round($avgWithdrawals * max(1, $typeStats['RETRAIT']['count'] / 3), 2),
            'total_payments' => round($avgPayments * max(1, $typeStats['PAIEMENT']['count'] / 3), 2),
            'total_transfers' => round($avgTransfers * max(1, $typeStats['VIREMENT']['count'] / 3), 2),
            'net_balance' => 0,
            'confidence' => 'medium',
            'key_insights' => [
                sprintf('Environ %.0f transactions prévues', $avgTransactionsPerMonth),
                'Basé sur vos habitudes des derniers mois',
                'Prévisions ajustées selon vos patterns'
            ],
        ];
        
        $nextMonthForecast['net_balance'] = round(
            $nextMonthForecast['total_deposits'] 
            - $nextMonthForecast['total_withdrawals'] 
            - $nextMonthForecast['total_payments'],
            2
        );

        return [
            'predictions' => array_slice($predictions, 0, 5),
            'next_month_forecast' => $nextMonthForecast,
            'analysis' => 'Analyse basée sur vos habitudes financières récentes.',
            'advice' => 'Continuez à suivre vos dépenses régulièrement. Établissez un budget mensuel. Prévoyez une épargne de sécurité.',
            'provider' => 'Fallback',
        ];
    }
}
