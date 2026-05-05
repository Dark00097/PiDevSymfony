<?php

namespace App\Service;

final class CashbackCompanionService
{
    public function __construct(
        private readonly BankingService $bankingService,
        private readonly InsightsService $insightsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAssistantReply(int $userId, string $message): array
    {
        try {
            $entries = $this->bankingService->listCashbacks($userId);
            $partners = $this->bankingService->listPartenaires();
            $advisor = $this->insightsService->getCashbackAdvisor($userId);
        } catch (\Throwable) {
            $entries = [];
            $partners = [];
            $advisor = ['tips' => []];
        }

        $activePartners = $this->rankPartners($partners);
        $stats = $this->summarizeEntries($entries);

        $normalized = mb_strtolower(trim($message));
        $requestedCity = $this->extractRequestedCity($message);
        $wantsOffers = $this->containsAny($normalized, ['offre', 'meilleur', 'best', 'partenaire', 'partner', 'ville', 'city']);
        $wantsInsights = $this->containsAny($normalized, ['insight', 'analyse', 'resume', 'stat', 'statut', 'attente', 'valide']);
        $wantsHelp = $normalized === '' || $this->containsAny($normalized, ['aide', 'help', 'comment', 'fonctionne', 'cashback']);

        $title = 'Assistant cashback';
        $answer = sprintf(
            'Vous avez %d cashback(s) enregistre(s), %.2f DT deja valides ou credites, et %d dossier(s) encore en attente.',
            $stats['count'],
            $stats['approved_amount'],
            $stats['pending_count']
        );

        if ($wantsOffers) {
            $cityOffers = $requestedCity !== ''
                ? array_values(array_filter($activePartners, fn (array $offer): bool => $this->partnerMatchesCity($offer, $requestedCity)))
                : [];
            $topOffers = array_slice($cityOffers !== [] ? $cityOffers : $activePartners, 0, 3);

            if ($requestedCity !== '') {
                if ($cityOffers === []) {
                    $answer = sprintf(
                        'Je n ai pas trouve de partenaire cashback a %s pour le moment. Voici les meilleures offres disponibles dans votre reseau actuel.',
                        $requestedCity
                    );
                } else {
                    $answer = sprintf(
                        'Voici les meilleures offres cashback disponibles a %s, en priorisant le taux, la note et la qualite globale.',
                        $requestedCity
                    );
                }
            } else {
                $answer = 'Voici les meilleures offres cashback actuellement disponibles dans vos partenaires actifs, en priorisant le taux, la note et la qualite globale.';
            }

            return [
                'title' => 'Meilleures offres',
                'answer' => $answer,
                'metrics' => $this->buildMetrics($stats),
                'offers' => array_map(fn (array $offer): array => [
                    'name' => (string) ($offer['nom'] ?? 'Partenaire'),
                    'category' => (string) ($offer['categorie'] ?? 'General'),
                    'city' => (string) ($offer['ville'] ?? ''),
                    'rating' => number_format((float) ($offer['rating'] ?? 0), 1, '.', ''),
                    'cashback' => number_format((float) ($offer['tauxCashback'] ?? 0), 2, '.', '').'%',
                    'cashback_max' => number_format((float) ($offer['tauxCashbackMax'] ?? 0), 2, '.', '').'%',
                    'reason' => (string) ($offer['_assistant_reason'] ?? 'Offre attractive du reseau cashback.'),
                ], $topOffers),
                'suggestions' => [
                    'Quels partenaires sont les plus rentables pour moi ?',
                    'Donne-moi un resume de mes cashback en attente',
                    'Comment maximiser mon cashback ce mois-ci ?',
                ],
            ];
        }

        if ($wantsInsights) {
            $bestPartner = $stats['best_partner'] !== '' ? $stats['best_partner'] : 'aucun partenaire dominant';
            $answer = sprintf(
                'Resume rapide: %.2f DT valides/credites, %.2f DT cumules au total, partenaire le plus rentable: %s.',
                $stats['approved_amount'],
                $stats['total_amount'],
                $bestPartner
            );

            return [
                'title' => 'Vos insights cashback',
                'answer' => $answer,
                'metrics' => $this->buildMetrics($stats),
                'offers' => [],
                'suggestions' => array_values(array_slice((array) ($advisor['tips'] ?? []), 0, 3)),
            ];
        }

        if ($wantsHelp) {
            return [
                'title' => 'Aide cashback',
                'answer' => 'Pour bien utiliser cette section: enregistrez un achat, choisissez un partenaire, laissez une note utile, puis suivez vos statuts et partenaires les plus rentables depuis votre historique.',
                'metrics' => $this->buildMetrics($stats),
                'offers' => array_map(fn (array $offer): array => [
                    'name' => (string) ($offer['nom'] ?? 'Partenaire'),
                    'category' => (string) ($offer['categorie'] ?? 'General'),
                    'city' => (string) ($offer['ville'] ?? ''),
                    'rating' => number_format((float) ($offer['rating'] ?? 0), 1, '.', ''),
                    'cashback' => number_format((float) ($offer['tauxCashback'] ?? 0), 2, '.', '').'%',
                    'cashback_max' => number_format((float) ($offer['tauxCashbackMax'] ?? 0), 2, '.', '').'%',
                    'reason' => (string) ($offer['_assistant_reason'] ?? 'Bon candidat pour vos prochains achats.'),
                ], array_slice($activePartners, 0, 2)),
                'suggestions' => [
                    'Quelles sont les meilleures offres du moment ?',
                    'Quels partenaires me conseilles-tu ?',
                    'Resume mon historique cashback',
                ],
            ];
        }

        return [
            'title' => $title,
            'answer' => $answer,
            'metrics' => $this->buildMetrics($stats),
            'offers' => array_map(fn (array $offer): array => [
                'name' => (string) ($offer['nom'] ?? 'Partenaire'),
                'category' => (string) ($offer['categorie'] ?? 'General'),
                'city' => (string) ($offer['ville'] ?? ''),
                'rating' => number_format((float) ($offer['rating'] ?? 0), 1, '.', ''),
                'cashback' => number_format((float) ($offer['tauxCashback'] ?? 0), 2, '.', '').'%',
                'cashback_max' => number_format((float) ($offer['tauxCashbackMax'] ?? 0), 2, '.', '').'%',
                'reason' => (string) ($offer['_assistant_reason'] ?? 'Partenaire recommande.'),
            ], array_slice($activePartners, 0, 2)),
            'suggestions' => [
                'Montre-moi les meilleures offres cashback',
                'Quels sont mes cashback en attente ?',
                'Comment optimiser mes partenaires ?',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildHistoryBundle(int $userId): array
    {
        try {
            $entries = $this->bankingService->listCashbacks($userId);
            $partners = $this->bankingService->listPartenaires();
        } catch (\Throwable) {
            $entries = [];
            $partners = [];
        }
        $partnersById = [];
        $partnersByName = [];

        foreach ($partners as $partner) {
            $id = (int) ($partner['idPartenaire'] ?? 0);
            $name = trim((string) ($partner['nom'] ?? ''));
            if ($id > 0) {
                $partnersById[$id] = $partner;
            }
            if ($name !== '') {
                $partnersByName[mb_strtolower($name)] = $partner;
            }
        }

        $stats = $this->summarizeEntries($entries);
        $history = [];
        foreach ($entries as $entry) {
            $partnerId = (int) ($entry['id_partenaire'] ?? 0);
            $partnerName = trim((string) ($entry['partenaire_nom'] ?? ''));
            $partner = $partnersById[$partnerId] ?? $partnersByName[mb_strtolower($partnerName)] ?? null;

            $history[] = [
                'id_cashback' => (int) ($entry['id_cashback'] ?? 0),
                'partner_name' => $partnerName,
                'partner_category' => (string) ($partner['categorie'] ?? ''),
                'partner_city' => (string) ($partner['ville'] ?? ''),
                'partner_rating' => (float) ($partner['rating'] ?? 0),
                'partner_status' => (string) ($partner['status'] ?? ''),
                'purchase_amount' => round((float) ($entry['montant_achat'] ?? 0), 2),
                'cashback_amount' => round((float) ($entry['montant_cashback'] ?? 0), 2),
                'rate' => round((float) ($entry['taux_applique'] ?? 0), 2),
                'status' => (string) ($entry['statut'] ?? ''),
                'purchase_date' => (string) ($entry['date_achat'] ?? ''),
                'credit_date' => (string) ($entry['date_credit'] ?? ''),
                'expires_at' => (string) ($entry['date_expiration'] ?? ''),
                'transaction_ref' => (string) ($entry['transaction_ref'] ?? ''),
                'user_rating' => (float) ($entry['user_rating'] ?? 0),
                'user_rating_comment' => (string) ($entry['user_rating_comment'] ?? ''),
                'bonus_decision' => (string) ($entry['bonus_decision'] ?? ''),
                'bonus_note' => (string) ($entry['bonus_note'] ?? ''),
            ];
        }

        $bundle = [
            'type' => 'cashback_history_bundle',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'user_id' => $userId,
            'summary' => [
                'count' => $stats['count'],
                'total_cashback' => $stats['total_amount'],
                'approved_cashback' => $stats['approved_amount'],
                'pending_count' => $stats['pending_count'],
                'validated_count' => $stats['validated_count'],
                'best_partner' => $stats['best_partner'],
            ],
            'recommended_partners' => array_values(array_map(fn (array $partner): array => [
                'name' => (string) ($partner['nom'] ?? ''),
                'category' => (string) ($partner['categorie'] ?? ''),
                'city' => (string) ($partner['ville'] ?? ''),
                'rating' => (float) ($partner['rating'] ?? 0),
                'cashback' => (float) ($partner['tauxCashback'] ?? 0),
                'cashback_max' => (float) ($partner['tauxCashbackMax'] ?? 0),
                'status' => (string) ($partner['status'] ?? ''),
            ], array_slice($this->rankPartners($partners), 0, 5))),
            'history' => $history,
        ];

        $json = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'bundle' => $bundle,
            'json' => is_string($json) ? $json : '{}',
            'hash' => strtoupper(substr(hash('sha256', is_string($json) ? $json : '{}'), 0, 24)),
        ];
    }

    /**
     * @param array<string, mixed> $bundle
     */
    public function buildQrPayload(array $bundle, string $hash): string
    {
        $summary = (array) ($bundle['summary'] ?? []);
        $recommended = (array) ($bundle['recommended_partners'] ?? []);
        $history = is_array($bundle['history'] ?? null) ? $bundle['history'] : [];
        $topPartner = is_array($recommended[0] ?? null) ? (array) $recommended[0] : [];
        $topLine = trim((string) ($topPartner['name'] ?? '')) !== ''
            ? sprintf(
                '%s%s | Max %s%% | Note %s/5',
                (string) ($topPartner['name'] ?? ''),
                trim((string) ($topPartner['city'] ?? '')) !== '' ? ' - '.(string) ($topPartner['city'] ?? '') : '',
                number_format((float) ($topPartner['cashback_max'] ?? 0), 2, '.', ''),
                number_format((float) ($topPartner['rating'] ?? 0), 1, '.', '')
            )
            : 'Aucune offre recommandee';

        $recommendedLines = [];
        foreach (array_slice($recommended, 0, 3) as $partner) {
            if (!is_array($partner)) {
                continue;
            }

            $recommendedLines[] = sprintf(
                '- %s%s | %s%% max | %s/5',
                (string) ($partner['name'] ?? 'Partenaire'),
                trim((string) ($partner['city'] ?? '')) !== '' ? ' - '.(string) ($partner['city'] ?? '') : '',
                number_format((float) ($partner['cashback_max'] ?? 0), 2, '.', ''),
                number_format((float) ($partner['rating'] ?? 0), 1, '.', '')
            );
        }

        $historyLines = [];
        foreach (array_slice($history, 0, 4) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $historyLines[] = sprintf(
                '- %s | Achat %s DT | Cashback %s DT | %s | %s',
                (string) ($entry['partner_name'] ?? 'Partenaire'),
                number_format((float) ($entry['purchase_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($entry['cashback_amount'] ?? 0), 2, '.', ''),
                (string) ($entry['status'] ?? '-'),
                (string) ($entry['purchase_date'] ?? '-')
            );
        }

        return implode("\n", array_filter([
            'NEXORA CASHBACK BUNDLE',
            'Hash: '.$hash,
            'Genere le: '.(string) ($bundle['generated_at'] ?? ''),
            '',
            'BRIEFING',
            'Cashback total: '.number_format((float) ($summary['total_cashback'] ?? 0), 2, '.', ' ').' DT',
            'Valide / credite: '.number_format((float) ($summary['approved_cashback'] ?? 0), 2, '.', ' ').' DT',
            'Dossiers: '.(int) ($summary['count'] ?? 0),
            'En attente: '.(int) ($summary['pending_count'] ?? 0),
            'Valides: '.(int) ($summary['validated_count'] ?? 0),
            'Partenaire fort: '.((string) ($summary['best_partner'] ?? '') !== '' ? (string) ($summary['best_partner'] ?? '') : 'Aucun'),
            'Top offre: '.$topLine,
            '',
            $recommendedLines !== [] ? 'TOP OFFRES' : null,
            $recommendedLines !== [] ? implode("\n", $recommendedLines) : null,
            '',
            $historyLines !== [] ? 'HISTORIQUE RECENT' : null,
            $historyLines !== [] ? implode("\n", $historyLines) : null,
        ]));
    }

    /**
     * @param array<int, array<string, mixed>> $partners
     * @return array<int, array<string, mixed>>
     */
    private function rankPartners(array $partners): array
    {
        $ranked = array_map(function (array $partner): array {
            $status = mb_strtolower(trim((string) ($partner['status'] ?? '')));
            $activeBonus = in_array($status, ['actif', 'active'], true) ? 20 : 0;
            $score = ((float) ($partner['tauxCashbackMax'] ?? 0) * 4)
                + ((float) ($partner['tauxCashback'] ?? 0) * 2)
                + ((float) ($partner['rating'] ?? 0) * 10)
                + $activeBonus;

            $partner['_assistant_score'] = $score;
            $partner['_assistant_reason'] = sprintf(
                'Jusqu a %s de cashback avec une note de %s/5%s%s.',
                number_format((float) ($partner['tauxCashbackMax'] ?? 0), 2, '.', '').'%',
                number_format((float) ($partner['rating'] ?? 0), 1, '.', ''),
                $activeBonus > 0 ? ' et un statut actif' : '',
                trim((string) ($partner['ville'] ?? '')) !== '' ? ' a '.trim((string) ($partner['ville'] ?? '')) : ''
            );

            return $partner;
        }, $partners);

        usort($ranked, static fn (array $left, array $right): int => ((float) ($right['_assistant_score'] ?? 0)) <=> ((float) ($left['_assistant_score'] ?? 0)));

        return $ranked;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function summarizeEntries(array $entries): array
    {
        $totalAmount = 0.0;
        $approvedAmount = 0.0;
        $pendingCount = 0;
        $validatedCount = 0;
        $partnerEarnings = [];

        foreach ($entries as $entry) {
            $cashbackAmount = (float) ($entry['montant_cashback'] ?? 0);
            $status = mb_strtolower(trim((string) ($entry['statut'] ?? '')));
            $partnerName = trim((string) ($entry['partenaire_nom'] ?? ''));

            $totalAmount += $cashbackAmount;
            if (in_array($status, ['valide', 'credite', 'approved'], true)) {
                $approvedAmount += $cashbackAmount;
                $validatedCount++;
            }
            if (in_array($status, ['en attente', 'pending'], true)) {
                $pendingCount++;
            }
            if ($partnerName !== '') {
                $partnerEarnings[$partnerName] = ($partnerEarnings[$partnerName] ?? 0) + $cashbackAmount;
            }
        }

        arsort($partnerEarnings);

        return [
            'count' => count($entries),
            'total_amount' => round($totalAmount, 2),
            'approved_amount' => round($approvedAmount, 2),
            'pending_count' => $pendingCount,
            'validated_count' => $validatedCount,
            'best_partner' => (string) (array_key_first($partnerEarnings) ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<int, array{label:string,value:string}>
     */
    private function buildMetrics(array $stats): array
    {
        return [
            ['label' => 'Cashback total', 'value' => number_format((float) ($stats['total_amount'] ?? 0), 2, '.', ' ').' DT'],
            ['label' => 'Valide / credite', 'value' => number_format((float) ($stats['approved_amount'] ?? 0), 2, '.', ' ').' DT'],
            ['label' => 'En attente', 'value' => (string) ($stats['pending_count'] ?? 0)],
            ['label' => 'Partenaire fort', 'value' => (string) (($stats['best_partner'] ?? '') !== '' ? $stats['best_partner'] : 'Aucun')],
        ];
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractRequestedCity(string $message): string
    {
        $normalized = trim($message);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/(?:ville|city|a|à)\s+([a-zA-ZÀ-ÿ][a-zA-ZÀ-ÿ\-\s]{1,40})/u', $normalized, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $partner
     */
    private function partnerMatchesCity(array $partner, string $city): bool
    {
        $partnerCity = mb_strtolower(trim((string) ($partner['ville'] ?? '')));
        $requestedCity = mb_strtolower(trim($city));

        if ($partnerCity === '' || $requestedCity === '') {
            return false;
        }

        return str_contains($partnerCity, $requestedCity) || str_contains($requestedCity, $partnerCity);
    }
}
