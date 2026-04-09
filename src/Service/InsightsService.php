<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class InsightsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly BankingService $bankingService,
        private readonly ActivityService $activityService,
        private readonly LegacyBankingSecurity $security,
    ) {
    }

    public function getAccountSecurityAnalysis(int $userId): array
    {
        $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE idUser = ? LIMIT 1', [$userId]) ?: [];
        $accounts = $this->bankingService->listAccounts($userId);
        $transactions = $this->bankingService->listTransactions($userId);
        $recentActivity = $this->activityService->listRecent($userId, 12);

        $activeAccounts = array_values(array_filter($accounts, static fn (array $row): bool => strtolower((string) ($row['statutCompte'] ?? '')) === 'actif'));
        $blockedAccounts = array_values(array_filter($accounts, static fn (array $row): bool => strtolower((string) ($row['statutCompte'] ?? '')) === 'bloque'));
        $anomalies = array_values(array_filter($transactions, static fn (array $row): bool => (bool) ($row['is_anomalie'] ?? false)));
        $recentLogins = $this->activityService->countByType($userId, 'LOGIN', 30);
        $biometricEnabled = (bool) ($user['biometric_enabled'] ?? false);

        $score = 55;
        $score += $biometricEnabled ? 15 : 0;
        $score += count($activeAccounts) > 0 ? 10 : -5;
        $score += count($blockedAccounts) === 0 ? 8 : -8;
        $score += count($anomalies) === 0 ? 8 : -12;
        $score += $recentLogins >= 2 ? 4 : 0;
        $score += count($recentActivity) >= 3 ? 4 : -3;
        $score = max(15, min(98, $score));

        $sections = [
            [
                'title' => 'Authentication posture',
                'tone' => $biometricEnabled ? 'good' : 'warning',
                'text' => $biometricEnabled
                    ? 'Biometric login is enabled, which matches the Java app security preference flow.'
                    : 'Biometric login is disabled. Enable it to match the strongest profile configuration.',
            ],
            [
                'title' => 'Account hygiene',
                'tone' => count($blockedAccounts) === 0 ? 'good' : 'warning',
                'text' => count($blockedAccounts) === 0
                    ? sprintf('%d active account(s) are in service without blocked states.', count($activeAccounts))
                    : sprintf('%d blocked account(s) need review to reduce support risk.', count($blockedAccounts)),
            ],
            [
                'title' => 'Transaction behavior',
                'tone' => count($anomalies) === 0 ? 'good' : 'danger',
                'text' => count($anomalies) === 0
                    ? 'No anomaly spike was detected from the recent encrypted transaction history.'
                    : sprintf('%d transaction(s) stand out compared with the user average and should be reviewed.', count($anomalies)),
            ],
            [
                'title' => 'Operational continuity',
                'tone' => $recentLogins >= 1 ? 'good' : 'warning',
                'text' => $recentLogins >= 1
                    ? sprintf('The profile logged %d recent session(s), and activity traces are available for auditing.', $recentLogins)
                    : 'There is little recent activity, so continuous monitoring signals are limited.',
            ],
        ];

        $recommendations = [];
        if (!$biometricEnabled) {
            $recommendations[] = 'Enable biometric login from the profile tools.';
        }
        if (count($blockedAccounts) > 0) {
            $recommendations[] = 'Review blocked accounts and reactivate only the ones still in use.';
        }
        if (count($anomalies) > 0) {
            $recommendations[] = 'Inspect the flagged transactions and confirm they are expected.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Keep the current security posture and continue saving activity logs.';
        }

        $summary = $score >= 80
            ? 'The account posture is strong and close to the intended Java workflow.'
            : ($score >= 60
                ? 'The account is generally healthy but still has a few hardening actions to apply.'
                : 'The account needs stronger controls before it matches the safest operating profile.');

        return [
            'score' => $score,
            'summary' => $summary,
            'sections' => $sections,
            'recommendations' => $recommendations,
        ];
    }

    public function exportAccountSecurityAnalysis(int $userId): string
    {
        $analysis = $this->getAccountSecurityAnalysis($userId);
        $lines = [
            'NEXORA ACCOUNT SECURITY ANALYSIS',
            'Generated at: '.(new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'Score: '.$analysis['score'].'/100',
            '',
            'Summary:',
            $analysis['summary'],
            '',
            'Sections:',
        ];

        foreach ($analysis['sections'] as $section) {
            $lines[] = sprintf('- %s: %s', $section['title'], $section['text']);
        }

        $lines[] = '';
        $lines[] = 'Recommendations:';
        foreach ($analysis['recommendations'] as $recommendation) {
            $lines[] = '- '.$recommendation;
        }

        return implode("\n", $lines)."\n";
    }

    public function getSpendingPrediction(int $userId): array
    {
        $transactions = $this->bankingService->listTransactions($userId);
        $byCategory = [];
        $monthlyCredits = 0.0;
        $monthlyDebits = 0.0;
        $months = [];

        foreach ($transactions as $transaction) {
            $date = $this->normalizeDate((string) ($transaction['dateTransaction'] ?? ''));
            $month = $date !== null ? substr($date, 0, 7) : null;
            if ($month !== null) {
                $months[$month] = true;
            }

            $amount = (float) ($transaction['montant_value'] ?? 0);
            $type = strtoupper((string) ($transaction['typeTransaction'] ?? ''));
            $category = trim((string) ($transaction['categorie'] ?? 'Autre')) ?: 'Autre';

            if ($type === 'DEBIT') {
                $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;
                if ($month === $this->currentMonth()) {
                    $monthlyDebits += $amount;
                }
            } elseif ($month === $this->currentMonth()) {
                $monthlyCredits += $amount;
            }
        }

        $monthCount = max(1, count($months));
        $predictions = [];
        foreach ($byCategory as $category => $total) {
            $predictions[] = [
                'category' => $category,
                'predicted_amount' => round($total / $monthCount, 2),
            ];
        }

        usort($predictions, static fn (array $left, array $right): int => $right['predicted_amount'] <=> $left['predicted_amount']);
        $predictions = array_slice($predictions, 0, 6);

        $totalPredicted = array_sum(array_column($predictions, 'predicted_amount'));
        $savingsRate = $monthlyCredits > 0 ? max(0.0, 1 - ($monthlyDebits / max($monthlyCredits, 1))) : 0.0;
        $savingsScore = (int) round(max(0, min(100, $savingsRate * 100)));

        $tips = [];
        if ($predictions !== []) {
            $tips[] = sprintf('Watch the "%s" category, which is the highest expected spend next month.', $predictions[0]['category']);
        }
        if ($savingsScore < 25) {
            $tips[] = 'Move one debit each month toward savings or coffre transfers to improve the score.';
        } elseif ($savingsScore >= 50) {
            $tips[] = 'Current inflow versus spending is healthy enough to support additional savings goals.';
        }
        if ($tips === []) {
            $tips[] = 'Keep transaction categories precise so next-month forecasting stays reliable.';
        }

        return [
            'predictions' => $predictions,
            'total_predicted_spending' => round($totalPredicted, 2),
            'savings_score' => $savingsScore,
            'conseils' => $tips,
            'summary' => $predictions === []
                ? 'Not enough transaction history is available yet for a category forecast.'
                : sprintf('Expected monthly spend is %.2f DT across %d major categories.', $totalPredicted, count($predictions)),
        ];
    }

    public function getAccountAdvisor(int $userId): array
    {
        $accounts = $this->bankingService->listAccounts($userId);
        $credits = $this->bankingService->listCredits($userId);
        $vaults = $this->bankingService->listVaults($userId);
        $activity = $this->activityService->listRecent($userId, 8);

        $totalBalance = array_sum(array_map(static fn (array $row): float => (float) ($row['solde'] ?? 0), $accounts));
        $activeCredits = array_values(array_filter($credits, static fn (array $row): bool => in_array(strtolower((string) ($row['statut'] ?? '')), ['en cours', 'accepte', 'approved', 'active'], true)));
        $vaultProgress = 0.0;
        foreach ($vaults as $vault) {
            $goal = max(1.0, (float) ($vault['objectifMontant'] ?? 0));
            $vaultProgress = max($vaultProgress, ((float) ($vault['montantActuel'] ?? 0)) / $goal);
        }

        $actions = [];
        if ($totalBalance < 500) {
            $actions[] = 'Keep more liquidity in the active account to absorb credit payments and wheel eligibility checks.';
        }
        if ($activeCredits !== []) {
            $actions[] = sprintf('%d credit(s) are active, so watch the debt ratio before creating another request.', count($activeCredits));
        }
        if ($vaultProgress < 0.25 && $vaults !== []) {
            $actions[] = 'Feed the dragon or schedule regular coffre transfers to increase savings progress.';
        }
        if ($activity === []) {
            $actions[] = 'Use the portal more regularly so the advisor can base recommendations on recent actions.';
        }
        if ($actions === []) {
            $actions[] = 'The profile is balanced across accounts, credits, and savings activity.';
        }

        return [
            'summary' => sprintf(
                'The user currently holds %.2f DT across %d account(s), %d vault(s), and %d credit dossier(s).',
                $totalBalance,
                count($accounts),
                count($vaults),
                count($credits)
            ),
            'action_items' => $actions,
            'recent_activity' => $activity,
        ];
    }

    public function getCashbackAdvisor(int $userId): array
    {
        $entries = $this->bankingService->listCashbacks($userId);
        $partners = $this->bankingService->listPartenaires();
        $favouriteCategories = [];

        foreach ($entries as $entry) {
            $partnerName = strtolower(trim((string) ($entry['partenaire_nom'] ?? '')));
            if ($partnerName !== '') {
                $favouriteCategories[$partnerName] = ($favouriteCategories[$partnerName] ?? 0) + 1;
            }
        }

        usort($partners, static fn (array $left, array $right): int => ((float) ($right['rating'] ?? 0)) <=> ((float) ($left['rating'] ?? 0)));
        $recommended = [];
        foreach ($partners as $partner) {
            $recommended[] = [
                'name' => $partner['nom'],
                'category' => $partner['categorie'],
                'rating' => (float) ($partner['rating'] ?? 0),
                'reason' => array_key_exists(strtolower((string) $partner['nom']), $favouriteCategories)
                    ? 'Already used successfully; consider repeating if the cashback rate stays attractive.'
                    : 'High partner rating and available cashback terms make this a strong next option.',
            ];
            if (count($recommended) >= 3) {
                break;
            }
        }

        $approvedAmount = 0.0;
        foreach ($entries as $entry) {
            if (in_array(strtolower((string) ($entry['statut'] ?? '')), ['valide', 'credite', 'approved'], true)) {
                $approvedAmount += (float) ($entry['montant_cashback'] ?? 0);
            }
        }

        return [
            'summary' => sprintf(
                'The profile has %d cashback dossier(s) and %.2f DT already validated or credited.',
                count($entries),
                $approvedAmount
            ),
            'recommended_partners' => $recommended,
            'tips' => [
                'Prioritize partners with high rating when purchase amounts exceed 200 DT.',
                'Add a useful rating comment after each cashback request to help admin bonus decisions.',
                'Watch expiration dates so validated cashback is not left unused.',
            ],
        ];
    }

    public function parseAccountSpeechText(string $text): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        $type = 'Courant';
        if (preg_match('/\b(epargne|saving)\b/i', $normalized)) {
            $type = 'Epargne';
        } elseif (preg_match('/\b(pro|professionnel|business)\b/i', $normalized)) {
            $type = 'Professionnel';
        }

        $status = preg_match('/\b(bloque|blocked)\b/i', $normalized) ? 'Bloque' : 'Actif';
        $number = null;
        if (preg_match('/\b([A-Z]{1,4}[- ]?\d{2,8})\b/', strtoupper($normalized), $match)) {
            $number = str_replace(' ', '', $match[1]);
        }
        if ($number === null) {
            $number = 'ACC-'.random_int(1000, 9999);
        }

        $solde = $this->extractNumberAfterKeywords($normalized, ['solde', 'balance'], 0.0);
        $withdrawal = $this->extractNumberAfterKeywords($normalized, ['retrait', 'withdrawal'], 100.0);
        $transfer = $this->extractNumberAfterKeywords($normalized, ['virement', 'transfer'], 100.0);

        return [
            'numeroCompte' => $number,
            'solde' => $solde,
            'typeCompte' => $type,
            'statutCompte' => $status,
            'plafondRetrait' => $withdrawal,
            'plafondVirement' => $transfer,
            'dateOuverture' => date('Y-m-d'),
        ];
    }

    public function improveComplaint(string $text): array
    {
        $normalized = trim($text);
        $lower = strtolower($normalized);
        $sentiment = 'Neutral';
        $severity = 'Medium';

        if (preg_match('/(urgent|fraud|blocked|impossible|stolen|arnaque|urgent)/i', $normalized)) {
            $sentiment = 'Critical';
            $severity = 'High';
        } elseif (preg_match('/(delay|late|problem|issue|erreur|probleme)/i', $normalized)) {
            $sentiment = 'Negative';
        }

        $improved = $normalized;
        if ($normalized !== '') {
            $improved = 'Bonjour, je souhaite signaler le probleme suivant: '.rtrim(ucfirst($normalized), '.').'. Merci de verifier la situation et de me communiquer la resolution.';
        }

        return [
            'original' => $text,
            'improved' => $improved,
            'sentiment' => $sentiment,
            'severity' => $severity,
        ];
    }

    public function generateGuaranteeDescription(array $data): string
    {
        $type = trim((string) ($data['typeGarantie'] ?? 'garantie'));
        $address = trim((string) ($data['adresseBien'] ?? 'adresse non precisee'));
        $estimated = (float) ($data['valeurEstimee'] ?? 0);
        $owner = trim((string) ($data['nomGarant'] ?? 'le garant'));

        return sprintf(
            'La garantie de type %s est rattachee a %s, situee a %s, avec une valeur estimee de %.2f DT. Le dossier est prepare pour evaluation bancaire et verification documentaire.',
            $type,
            $owner !== '' ? $owner : 'le garant',
            $address !== '' ? $address : 'adresse non precisee',
            $estimated
        );
    }

    public function getTranslations(string $language): array
    {
        $lang = strtolower(trim($language));
        $catalog = [
            'fr' => [
                'accounts' => 'Comptes',
                'transactions' => 'Transactions',
                'credits' => 'Credits',
                'vaults' => 'Coffres',
                'cashback' => 'Cashback',
                'notifications' => 'Notifications',
                'profile' => 'Profil',
            ],
            'en' => [
                'accounts' => 'Accounts',
                'transactions' => 'Transactions',
                'credits' => 'Loans',
                'vaults' => 'Vaults',
                'cashback' => 'Cashback',
                'notifications' => 'Notifications',
                'profile' => 'Profile',
            ],
            'ar' => [
                'accounts' => 'الحسابات',
                'transactions' => 'المعاملات',
                'credits' => 'القروض',
                'vaults' => 'الخزائن',
                'cashback' => 'الاسترجاع النقدي',
                'notifications' => 'الإشعارات',
                'profile' => 'الملف الشخصي',
            ],
        ];

        return [
            'language' => $lang,
            'labels' => $catalog[$lang] ?? $catalog['en'],
        ];
    }

    public function detectMonthlySurplus(int $userId): array
    {
        $currentMonth = $this->currentMonth();
        $alreadyShown = (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM surplus_notifications WHERE idUser = ? AND moisAffiche = ?',
            [$userId, $currentMonth]
        );

        $creditsByMonth = [];
        $rows = $this->connection->fetchAllAssociative(
            'SELECT dateTransaction, montant, typeTransaction FROM transactions WHERE idUser = ? ORDER BY idTransaction DESC',
            [$userId]
        );

        foreach ($rows as $row) {
            if (strtoupper((string) ($row['typeTransaction'] ?? '')) !== 'CREDIT') {
                continue;
            }

            $date = $this->normalizeDate((string) ($row['dateTransaction'] ?? ''));
            if ($date === null) {
                continue;
            }

            $month = substr($date, 0, 7);
            $creditsByMonth[$month] = ($creditsByMonth[$month] ?? 0.0) + ((float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0));
        }

        $currentIncome = (float) ($creditsByMonth[$currentMonth] ?? 0.0);
        unset($creditsByMonth[$currentMonth]);
        krsort($creditsByMonth);
        $history = array_slice(array_values($creditsByMonth), 0, 3);
        $average = $history === [] ? 0.0 : array_sum($history) / count($history);
        $surplus = $currentIncome - $average;
        $shouldShow = !$alreadyShown && $currentIncome > 0 && $average > 0 && $currentIncome >= ($average * 1.15);

        $message = $shouldShow
            ? sprintf('Income this month is %.2f DT versus a recent average of %.2f DT. Consider moving %.2f DT to a vault or investment target.', $currentIncome, $average, max(10.0, $surplus * 0.35))
            : 'No unusual monthly income surplus is waiting for action.';

        return [
            'show' => $shouldShow,
            'current_income' => round($currentIncome, 2),
            'average_income' => round($average, 2),
            'surplus' => round(max(0.0, $surplus), 2),
            'message' => $message,
            'month' => $currentMonth,
        ];
    }

    public function acknowledgeMonthlySurplus(int $userId, string $month): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM surplus_notifications WHERE idUser = ? AND moisAffiche = ?',
            [$userId, $month]
        );

        if ((int) $existing > 0) {
            return;
        }

        $this->connection->insert('surplus_notifications', [
            'idUser' => $userId,
            'moisAffiche' => $month,
        ]);
    }

    private function extractNumberAfterKeywords(string $text, array $keywords, float $fallback): float
    {
        foreach ($keywords as $keyword) {
            if (preg_match('/'.$keyword.'\D*([0-9]+(?:[.,][0-9]+)?)/i', $text, $match)) {
                return (float) str_replace(',', '.', $match[1]);
            }
        }

        return $fallback;
    }

    private function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed, $match)) {
            return substr($match[0], 0, 10);
        }

        return null;
    }

    private function currentMonth(): string
    {
        return (new \DateTimeImmutable())->format('Y-m');
    }
}
