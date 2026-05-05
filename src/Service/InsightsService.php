<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class InsightsService
{
    private const SUPERPLUS_NOTIFICATION_TABLE = 'superplus_notifications';
    private ?bool $superplusNotificationSchemaReady = null;
    private ?bool $transactionsDestinationColumnExists = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly BankingService $bankingService,
        private readonly ActivityService $activityService,
        private readonly LegacyBankingSecurity $security,
    ) {
    }

    /**
     * Analyse la sécurité des comptes d'un utilisateur.
     *
     * @return array{score: int, summary: string, sections: array<int, array<string, mixed>>, recommendations: array<int, string>}
     */
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

    public function getSpendingPrediction(int $userId, ?int $accountId = null): array
    {
        $allAccounts = $this->bankingService->listAccounts($userId);
        $selectedAccount = null;

        if ($accountId !== null && $accountId > 0) {
            foreach ($allAccounts as $account) {
                if ((int) ($account['idCompte'] ?? 0) === $accountId) {
                    $selectedAccount = $account;
                    break;
                }
            }
        }

        if ($selectedAccount === null && $allAccounts !== []) {
            $selectedAccount = $allAccounts[0];
        }

        $selectedAccountId = $selectedAccount !== null ? (int) ($selectedAccount['idCompte'] ?? 0) : null;
        $accounts = $selectedAccount !== null ? [$selectedAccount] : [];

        // IDs de tous les comptes appartenant à l'utilisateur — nécessaire pour classifier
        // correctement les VIREMENT entrants (destinataire = compte propre) vs sortants
        $userAccountIds = array_map(
            static fn (array $row): int => (int) $row['idCompte'],
            $allAccounts
        );

        $transactions = array_values(array_filter(
            $this->bankingService->listTransactions($userId),
            static fn (array $transaction): bool => $selectedAccountId === null
                || (int) ($transaction['idCompte'] ?? 0) === $selectedAccountId
        ));
        $today = new \DateTimeImmutable('today');
        $recentWindowStart = $today->modify('-30 days');
        $currentMonth = $this->currentMonth();
        $byCategory = [];
        $monthlyCredits = 0.0;
        $monthlyDebits = 0.0;
        $months = [];
        $dailyDebits = [];
        $creditPatterns = [];
        $projectionDays = 30;

        foreach ($transactions as $transaction) {
            $date = $this->normalizeDate((string) ($transaction['dateTransaction'] ?? ''));
            if ($date === null) {
                continue;
            }

            $month = substr($date, 0, 7);
            if ($month !== null) {
                $months[$month] = true;
            }

            $amount = (float) ($transaction['montant_value'] ?? 0);
            $typeRaw = strtolower(trim((string) ($transaction['typeTransaction'] ?? '')));

            // Logique de résolution du SENS (CREDIT vs DEBIT)
            // Règles métier officielles :
            // DEPOT / VERSEMENT               → CREDIT (entrée d'argent)
            // PAIEMENT / RETRAIT              → DEBIT  (sortie d'argent)
            // VIREMENT :
            //   idCompteDestinataire == compte courant de la ligne → CREDIT (reçu)
            //   idCompteDestinataire != compte courant de la ligne → DEBIT  (envoyé)
            //   idCompteDestinataire NULL/0                        → DEBIT  (inconnu → DEBIT)
            $resolvedType = 'UNKNOWN';
            if (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                $resolvedType = 'CREDIT';
            } elseif (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || $typeRaw === 'debit' || str_contains($typeRaw, 'paimenet')) {
                $resolvedType = 'DEBIT';
            } elseif (str_contains($typeRaw, 'virement')) {
                $dest = (int) ($transaction['idCompteDestinataire'] ?? 0);
                $txAccountId = (int) ($transaction['idCompte'] ?? 0);
                // CREDIT si idCompteDestinataire == compte courant (réception)
                // CREDIT si idCompteDestinataire NULL/0 (inconnu → CREDIT)
                // DEBIT  si idCompteDestinataire est un compte différent (envoi)
                $resolvedType = ($dest === 0 || $dest === $txAccountId) ? 'CREDIT' : 'DEBIT';
            }

            // Résolution intelligente de la catégorie :
            // - Si PAIEMENT → la catégorie est toujours renseignée (Alimentation, Transport, Shopping, Education)
            // - Si DEPOT / RETRAIT / VIREMENT → categorie est NULL en base → on déduit depuis le type
            $rawCategory = trim((string) ($transaction['categorie'] ?? ''));
            $category = $rawCategory !== '' ? $rawCategory : match (true) {
                str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement') => 'Revenu',
                str_contains($typeRaw, 'retrait')                                       => 'Retrait',
                str_contains($typeRaw, 'virement')                                     => 'Virement',
                default                                                                 => 'Autre',
            };
            $description = trim((string) ($transaction['description'] ?? ''));
            $dateObject = new \DateTimeImmutable($date);

            if ($resolvedType === 'DEBIT') {
                $byCategory[$category] = ($byCategory[$category] ?? 0.0) + $amount;
                if ($month === $currentMonth) {
                    $monthlyDebits += $amount;
                }

                if ($dateObject >= $recentWindowStart) {
                    $dailyDebits[$date] = ($dailyDebits[$date] ?? 0.0) + $amount;
                }
            } elseif ($resolvedType === 'CREDIT') {
                if ($month === $currentMonth) {
                    $monthlyCredits += $amount;
                }

                $signature = $this->resolveRecurringCreditSignature($category, $description);
                $creditPatterns[$signature]['label'] = $this->resolveRecurringCreditLabel($category, $description);
                $creditPatterns[$signature]['keyword_hint'] = $this->isRecurringIncomeKeyword($category, $description);
                $creditPatterns[$signature]['months'][$month] = true;
                $creditPatterns[$signature]['amounts'][] = $amount;
                $creditPatterns[$signature]['days'][] = (int) $dateObject->format('j');
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

        $averageDailyDebit = $dailyDebits === []
            ? 0.0
            : round(array_sum($dailyDebits) / count($dailyDebits), 2);
        $totalPredicted = round($averageDailyDebit * $projectionDays, 2);
        $savingsRate = $monthlyCredits > 0 ? max(0.0, 1 - ($monthlyDebits / max($monthlyCredits, 1))) : 0.0;
        $savingsScore = (int) round(max(0, min(100, $savingsRate * 100)));
        $currentBalance = round(array_sum(array_map(
            static fn (array $row): float => (float) ($row['solde'] ?? 0.0),
            $accounts
        )), 2);

        [$recurringCredits, $scheduledCredits, $nextRecurringCredit] = $this->buildRecurringCreditsForecast(
            $creditPatterns,
            $today,
            $projectionDays
        );

        $futureProjection = [];
        $runningBalance = $currentBalance;
        $firstZeroDay = null;
        $firstNegativeDay = null;

        for ($offset = 1; $offset <= $projectionDays; $offset++) {
            $futureDate = $today->modify(sprintf('+%d day', $offset));
            $futureKey = $futureDate->format('Y-m-d');
            $income = round((float) ($scheduledCredits[$futureKey] ?? 0.0), 2);
            $expense = $averageDailyDebit;
            $runningBalance = round($runningBalance - $expense + $income, 2);

            $point = [
                'day' => $offset,
                'date' => $futureKey,
                'label' => 'J+'.$offset,
                'display_label' => $futureDate->format('d/m'),
                'balance' => $runningBalance,
                'income' => $income,
                'expense' => $expense,
                'is_negative' => $runningBalance < 0,
                'is_critical' => $runningBalance <= 0,
            ];

            if ($firstZeroDay === null && $runningBalance <= 0) {
                $firstZeroDay = $point;
            }
            if ($firstNegativeDay === null && $runningBalance < 0) {
                $firstNegativeDay = $point;
            }

            $futureProjection[] = $point;
        }

        $lastProjectionPoint = $futureProjection !== [] ? $futureProjection[count($futureProjection) - 1] : null;
        $projectedBalance7 = (float) ($futureProjection[6]['balance'] ?? $currentBalance);
        $projectedBalance15 = (float) ($futureProjection[14]['balance'] ?? ($lastProjectionPoint['balance'] ?? $currentBalance));
        $projectedBalance30 = (float) ($lastProjectionPoint['balance'] ?? $currentBalance);

        $alerts = [];
        if ($firstZeroDay !== null) {
            $alerts[] = [
                'level' => $firstNegativeDay !== null ? 'danger' : 'warning',
                'title' => $firstNegativeDay !== null ? 'Solde negatif detecte' : 'Seuil critique approche',
                'text' => $firstNegativeDay !== null
                    ? sprintf('Votre solde passera sous 0 DT vers %s (%s).', $firstNegativeDay['label'], $firstNegativeDay['date'])
                    : sprintf('Votre solde atteindra 0 DT vers %s (%s).', $firstZeroDay['label'], $firstZeroDay['date']),
            ];
        }

        if ($nextRecurringCredit !== null) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Revenu recurrent identifie',
                'text' => sprintf(
                    '%s de %.2f DT attendu le %s.',
                    $nextRecurringCredit['label'],
                    $nextRecurringCredit['amount'],
                    $nextRecurringCredit['date']
                ),
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'Projection saine',
                'text' => 'Le solde reste positif sur les 30 prochains jours selon le rythme financier actuel.',
            ];
        }

        $tips = [];
        if ($predictions !== []) {
            $tips[] = sprintf('La categorie "%s" reste la depense la plus lourde a surveiller.', $predictions[0]['category']);
        }
        if ($averageDailyDebit > 0) {
            $tips[] = sprintf('Vos depenses recentes tournent autour de %.2f DT par jour.', $averageDailyDebit);
        }
        if ($firstNegativeDay !== null) {
            $tips[] = sprintf('Reduisez les debits quotidiens ou alimentez le compte avant %s pour eviter le negatif.', $firstNegativeDay['label']);
        } elseif ($savingsScore >= 50) {
            $tips[] = 'Le rythme actuel reste compatible avec une epargne additionnelle si vous gardez cette discipline.';
        }
        if ($tips === []) {
            $tips[] = 'Ajoutez plus de transactions pour rendre la prediction plus precise.';
        }

        $status = $firstNegativeDay !== null ? 'danger' : ($firstZeroDay !== null ? 'warning' : 'stable');
        $summary = $firstNegativeDay !== null
            ? sprintf(
                'Au rythme actuel de %.2f DT par jour, le solde pourrait devenir negatif en %d jours.',
                $averageDailyDebit,
                (int) $firstNegativeDay['day']
            )
            : ($nextRecurringCredit !== null
                ? sprintf(
                    'Le solde reste positif sur la fenetre projetee. Prochain revenu recurrent : %.2f DT le %s.',
                    (float) $nextRecurringCredit['amount'],
                    (string) $nextRecurringCredit['date']
                )
                : 'Le solde futur reste globalement stable sur les prochains jours selon l historique disponible.');

        return [
            'predictions' => $predictions,
            'total_predicted_spending' => $totalPredicted,
            'savings_score' => $savingsScore,
            'conseils' => $tips,
            'summary' => $summary,
            'current_balance' => $currentBalance,
            'average_daily_debit' => $averageDailyDebit,
            'projection_days' => $projectionDays,
            'projection_windows' => [7, 15, 30],
            'future_projection' => $futureProjection,
            'projected_balance_7' => round($projectedBalance7, 2),
            'projected_balance_15' => round($projectedBalance15, 2),
            'projected_balance_30' => round($projectedBalance30, 2),
            'recurring_credits' => $recurringCredits,
            'next_recurring_credit' => $nextRecurringCredit,
            'alerts' => $alerts,
            'first_zero_day' => $firstZeroDay,
            'first_negative_day' => $firstNegativeDay,
            'projection_status' => $status,
            'projection_status_label' => match ($status) {
                'danger' => 'Alerte previsionnelle',
                'warning' => 'Vigilance requise',
                default => 'Projection stable',
            },
            'selected_account' => $selectedAccount !== null ? [
                'idCompte' => (int) ($selectedAccount['idCompte'] ?? 0),
                'numeroCompte' => (string) ($selectedAccount['numeroCompte'] ?? ''),
                'typeCompte' => (string) ($selectedAccount['typeCompte'] ?? ''),
                'solde' => round((float) ($selectedAccount['solde'] ?? 0.0), 2),
                'statutCompte' => (string) ($selectedAccount['statutCompte'] ?? ''),
            ] : null,
            'scoped_transaction_count' => count($transactions),
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
        $this->ensureSuperplusNotificationSchema();
        $currentMonth = $this->currentMonth();
        $alreadyShown = (bool) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE idUser = ? AND moisAffiche = ?', self::SUPERPLUS_NOTIFICATION_TABLE),
            [$userId, $currentMonth]
        );

        $months = [];
        for ($offset = 0; $offset < 4; $offset++) {
            $months[] = $this->monthKeyFromOffset($offset);
        }

        // Récupérer tous les idCompte appartenant à cet utilisateur
        // Utilisé pour classifier correctement les VIREMENT entrants vs sortants
        $userAccountIds = array_map(
            static fn (array $row): int => (int) $row['idCompte'],
            $this->connection->fetchAllAssociative(
                'SELECT idCompte FROM compte WHERE idUser = ?',
                [$userId]
            )
        );

        $creditsByMonth = [];
        $rows = $this->fetchTransactionsForSurplus($userId);

        foreach ($rows as $row) {
            $typeRaw = strtolower(trim((string) ($row['typeTransaction'] ?? '')));
            $resolvedType = 'UNKNOWN';

            // Règles métier officielles CREDIT/DEBIT :
            // DEPOT       → CREDIT (argent entrant)
            // VERSEMENT   → CREDIT (argent entrant)
            // PAIEMENT    → DEBIT  (argent sortant)
            // RETRAIT     → DEBIT  (argent sortant)
            // VIREMENT :
            //   idCompteDestinataire == compte courant de la ligne → CREDIT (virement reçu)
            //   idCompteDestinataire != compte courant de la ligne → DEBIT  (virement envoyé)
            //   idCompteDestinataire NULL/0                        → DEBIT  (inconnu → DEBIT)
            if (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                $resolvedType = 'CREDIT';
            } elseif (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || $typeRaw === 'debit' || str_contains($typeRaw, 'paimenet')) {
                $resolvedType = 'DEBIT';
            } elseif (str_contains($typeRaw, 'virement')) {
                $dest = (int) ($row['idCompteDestinataire'] ?? 0);
                $sourceAccountId = (int) ($row['idCompte'] ?? 0);
                // CREDIT si idCompteDestinataire == compte courant (réception)
                // CREDIT si idCompteDestinataire NULL/0 (inconnu → CREDIT)
                // DEBIT  si idCompteDestinataire est un compte différent (envoi)
                if ($dest === 0 || $dest === $sourceAccountId) {
                    $resolvedType = 'CREDIT';
                } else {
                    $resolvedType = 'DEBIT';
                }
            }

            if ($resolvedType !== 'CREDIT') {
                continue;
            }

            $date = $this->normalizeDate((string) ($row['dateTransaction'] ?? ''));
            if ($date === null) {
                continue;
            }

            $month = substr($date, 0, 7);
            if (!in_array($month, $months, true)) {
                continue;
            }

            $creditsByMonth[$month] = ($creditsByMonth[$month] ?? 0.0) + ((float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0));
        }

        $currentIncome = round((float) ($creditsByMonth[$currentMonth] ?? 0.0), 2);
        $historyMonths = array_slice($months, 1, 3);
        $historyIncomes = array_values(array_map(
            static fn (string $month): float => round((float) ($creditsByMonth[$month] ?? 0.0), 2),
            $historyMonths
        ));
        $average = $historyIncomes === [] ? 0.0 : round(array_sum($historyIncomes) / count($historyIncomes), 2);
        $maxHistory = $historyIncomes === [] ? 0.0 : max($historyIncomes);
        $minHistory = $historyIncomes === [] ? 0.0 : min($historyIncomes);
        $historySpread = round($maxHistory - $minHistory, 2);
        $stabilityTolerance = round(max(100.0, $average * 0.15), 2);
        $stabilityOk = count($historyIncomes) === 3 && $average > 0 && $historySpread <= $stabilityTolerance;
        $surplus = round(max(0.0, $currentIncome - $average), 2);
        $surplusThreshold = round(max(150.0, $average * 0.15), 2);
        $recommendedTransfer = round(max(0.0, $surplus * 0.20), 2);

        $accounts = $this->bankingService->listAccounts($userId);
        $sourceAccount = $this->resolveSurplusSourceAccount($accounts, $recommendedTransfer);
        $canTransfer = $sourceAccount !== null
            && $recommendedTransfer > 0
            && (float) ($sourceAccount['solde'] ?? 0.0) >= $recommendedTransfer;
        $shouldShow = !$alreadyShown
            && $stabilityOk
            && $currentIncome > $average
            && $surplus >= $surplusThreshold
            && $recommendedTransfer >= 20.0
            && $canTransfer;

        $monthlyIncomes = array_map(function (string $month) use ($creditsByMonth, $currentMonth): array {
            return [
                'month' => $month,
                'label' => $month === $currentMonth ? 'Mois actuel' : $month,
                'income' => round((float) ($creditsByMonth[$month] ?? 0.0), 2),
                'is_current' => $month === $currentMonth,
            ];
        }, array_reverse($months));

        if ($shouldShow) {
            $message = sprintf(
                'Surplus detecte : les credits du mois atteignent %.2f DT contre une moyenne recente de %.2f DT. Superplus recommande d epargner %.2f DT.',
                $currentIncome,
                $average,
                $recommendedTransfer
            );
        } elseif ($currentIncome <= 0) {
            $message = 'Pas de surplus detecte : aucun credit n a encore ete enregistre ce mois-ci.';
        } elseif (!$stabilityOk) {
            $message = 'Pas de surplus detecte : les credits des trois derniers mois ne sont pas assez stables pour servir de reference fiable.';
        } elseif ($surplus < $surplusThreshold) {
            $message = 'Pas de surplus detecte : le revenu du mois reste trop proche de la moyenne des trois derniers mois.';
        } elseif (!$canTransfer) {
            $message = 'Pas de surplus detecte : aucun compte actif ne peut financer le montant recommande.';
        } else {
            $message = 'Pas de surplus detecte pour ce mois.';
        }

        return [
            'show' => $shouldShow,
            'already_shown' => $alreadyShown,
            'current_income' => $currentIncome,
            'average_income' => $average,
            'surplus' => $surplus,
            'recommended_transfer' => $recommendedTransfer,
            'display_recommended_transfer' => $shouldShow ? $recommendedTransfer : 0.0,
            'stability_ok' => $stabilityOk,
            'history_spread' => $historySpread,
            'stability_tolerance' => $stabilityTolerance,
            'surplus_threshold' => $surplusThreshold,
            'monthly_incomes' => $monthlyIncomes,
            'source_account' => $sourceAccount,
            'message' => $message,
            'ui_tone' => $shouldShow ? 'accent' : 'success',
            'status_label' => $shouldShow ? 'Surplus detecte' : 'Aucun surplus detecte',
            'month' => $currentMonth,
        ];
    }

    public function acknowledgeMonthlySurplus(int $userId, string $month): void
    {
        $this->ensureSuperplusNotificationSchema();
        $existing = $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE idUser = ? AND moisAffiche = ?', self::SUPERPLUS_NOTIFICATION_TABLE),
            [$userId, $month]
        );

        if ((int) $existing > 0) {
            return;
        }

        $this->connection->insert(self::SUPERPLUS_NOTIFICATION_TABLE, [
            'idUser' => $userId,
            'moisAffiche' => $month,
        ]);
    }

    private function ensureSuperplusNotificationSchema(): void
    {
        if ($this->superplusNotificationSchemaReady === true) {
            return;
        }

        try {
            $this->connection->executeStatement(
                'CREATE TABLE IF NOT EXISTS superplus_notifications (
                    idUser INT NOT NULL,
                    moisAffiche VARCHAR(7) NOT NULL,
                    dateCreation DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (idUser, moisAffiche)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->superplusNotificationSchemaReady = true;
        } catch (\Throwable $exception) {
            $this->superplusNotificationSchemaReady = false;

            throw new \RuntimeException(
                'Unable to initialize superplus_notifications table: '.$exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTransactionsForSurplus(int $userId): array
    {
        if ($this->hasTransactionsDestinationColumn()) {
            return $this->connection->fetchAllAssociative(
                'SELECT dateTransaction, montant, typeTransaction, idCompte, idCompteDestinataire
                 FROM transactions
                 WHERE idUser = ?
                 ORDER BY idTransaction DESC',
                [$userId]
            );
        }

        return $this->connection->fetchAllAssociative(
            'SELECT dateTransaction, montant, typeTransaction, idCompte, NULL AS idCompteDestinataire
             FROM transactions
             WHERE idUser = ?
             ORDER BY idTransaction DESC',
            [$userId]
        );
    }

    private function hasTransactionsDestinationColumn(): bool
    {
        if ($this->transactionsDestinationColumnExists !== null) {
            return $this->transactionsDestinationColumnExists;
        }

        try {
            $columns = $this->connection->createSchemaManager()->listTableColumns('transactions');
            $normalized = array_change_key_case($columns, CASE_LOWER);
            $this->transactionsDestinationColumnExists = array_key_exists('idcomptedestinataire', $normalized);
        } catch (\Throwable) {
            $this->transactionsDestinationColumnExists = false;
        }

        return $this->transactionsDestinationColumnExists;
    }

    public function transferDetectedMonthlySurplusToVault(int $userId, int $vaultId): array
    {
        $surplus = $this->detectMonthlySurplus($userId);
        $amount = round((float) ($surplus['recommended_transfer'] ?? 0.0), 2);
        if (!(bool) ($surplus['show'] ?? false) || $amount <= 0) {
            throw new \RuntimeException('Aucun surplus disponible a transferer pour ce mois.');
        }

        $vault = null;
        foreach ($this->bankingService->listVaults($userId) as $row) {
            if ((int) ($row['idCoffre'] ?? 0) === $vaultId) {
                $vault = $row;
                break;
            }
        }

        if ($vault === null) {
            throw new \RuntimeException('Le coffre selectionne est introuvable.');
        }

        $accounts = $this->bankingService->listAccounts($userId);
        $sourceAccount = $this->resolveSurplusSourceAccount($accounts, $amount);
        if ($sourceAccount === null) {
            throw new \RuntimeException('Aucun compte actif ne peut financer automatiquement ce transfert.');
        }

        $goal = (float) ($vault['objectifMontant'] ?? 0.0);
        $currentVaultAmount = (float) ($vault['montantActuel'] ?? 0.0);
        if ($goal > 0) {
            $remainingCapacity = round(max(0.0, $goal - $currentVaultAmount), 2);
            if ($remainingCapacity <= 0) {
                throw new \RuntimeException('Ce coffre a deja atteint son objectif.');
            }

            $amount = min($amount, $remainingCapacity);
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Le montant de surplus recommande est invalide.');
        }

        $sourceBalance = (float) ($sourceAccount['solde'] ?? 0.0);
        if ($sourceBalance < $amount) {
            throw new \RuntimeException('Le compte source selectionne automatiquement ne dispose pas d un solde suffisant.');
        }

        $newBalance = round($sourceBalance - $amount, 2);
        $newVaultAmount = round($currentVaultAmount + $amount, 2);
        $shouldLock = $goal > 0 && $newVaultAmount >= $goal;
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->connection->transactional(function () use (
            $userId,
            $vaultId,
            $vault,
            $amount,
            $sourceAccount,
            $newBalance,
            $newVaultAmount,
            $shouldLock,
            $today
        ): void {
            $this->connection->update('compte', [
                'solde' => $newBalance,
            ], [
                'idCompte' => (int) $sourceAccount['idCompte'],
                'idUser' => $userId,
            ]);

            $vaultCriteria = ['idCoffre' => $vaultId];
            if ((int) ($vault['idUser'] ?? 0) > 0) {
                $vaultCriteria['idUser'] = $userId;
            }

            $this->connection->update('coffrevirtuel', [
                'montantActuel' => $newVaultAmount,
                'estVerrouille' => $shouldLock ? 1 : (int) ($vault['estVerrouille'] ?? 0),
            ], $vaultCriteria);

            $this->connection->insert('transactions', [
                'idCompte' => (int) $sourceAccount['idCompte'],
                'idUser' => $userId,
                'categorie' => 'Epargne',
                'dateTransaction' => $today,
                'montant' => $this->security->encryptAmount($amount),
                'typeTransaction' => 'DEBIT',
                'soldeApres' => $newBalance,
                'description' => sprintf('Transfert surplus mensuel vers coffre #%d', $vaultId),
                'montantPaye' => $this->security->encryptAmount($amount),
            ]);
        });

        $this->acknowledgeMonthlySurplus($userId, (string) ($surplus['month'] ?? $this->currentMonth()));
        $this->activityService->log(
            $userId,
            'SURPLUS_TRANSFER',
            'Symfony portal',
            sprintf('Transferred %.2f DT of detected monthly surplus to vault #%d.', $amount, $vaultId)
        );

        return [
            'amount' => $amount,
            'vault_id' => $vaultId,
            'vault_name' => (string) ($vault['nom'] ?? '#'.$vaultId),
            'source_account' => [
                'idCompte' => (int) ($sourceAccount['idCompte'] ?? 0),
                'numeroCompte' => (string) ($sourceAccount['numeroCompte'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $creditPatterns
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float>, 2: array<string, mixed>|null}
     */
    private function buildRecurringCreditsForecast(array $creditPatterns, \DateTimeImmutable $today, int $horizonDays): array
    {
        $credits = [];
        $schedule = [];
        $nextRecurring = null;

        foreach ($creditPatterns as $pattern) {
            $monthsCount = count(array_keys((array) ($pattern['months'] ?? [])));
            $keywordHint = (bool) ($pattern['keyword_hint'] ?? false);
            if ($monthsCount < 2 && !$keywordHint) {
                continue;
            }

            $amounts = array_values(array_map('floatval', (array) ($pattern['amounts'] ?? [])));
            $days = array_values(array_map('intval', (array) ($pattern['days'] ?? [])));
            if ($amounts === [] || $days === []) {
                continue;
            }

            $amount = round(array_sum($amounts) / count($amounts), 2);
            $dayOfMonth = max(1, min(28, (int) round(array_sum($days) / count($days))));
            $occurrences = $this->buildRecurringCreditOccurrences($today, $dayOfMonth, $amount, $horizonDays);
            if ($occurrences === []) {
                continue;
            }

            $label = trim((string) ($pattern['label'] ?? 'Revenu recurrent')) ?: 'Revenu recurrent';
            $credit = [
                'label' => $label,
                'amount' => $amount,
                'day_of_month' => $dayOfMonth,
                'occurrences' => $occurrences,
            ];
            $credits[] = $credit;

            foreach ($occurrences as $occurrence) {
                $schedule[$occurrence['date']] = round(($schedule[$occurrence['date']] ?? 0.0) + $amount, 2);
                if ($nextRecurring === null || strcmp((string) $occurrence['date'], (string) $nextRecurring['date']) < 0) {
                    $nextRecurring = [
                        'label' => $label,
                        'amount' => $amount,
                        'date' => $occurrence['date'],
                    ];
                }
            }
        }

        usort($credits, static fn (array $left, array $right): int => ((float) ($right['amount'] ?? 0.0)) <=> ((float) ($left['amount'] ?? 0.0)));

        return [$credits, $schedule, $nextRecurring];
    }

    /**
     * @return array<int, array{date:string,label:string}>
     */
    private function buildRecurringCreditOccurrences(\DateTimeImmutable $today, int $dayOfMonth, float $amount, int $horizonDays): array
    {
        if ($amount <= 0 || $horizonDays <= 0) {
            return [];
        }

        $endDate = $today->modify(sprintf('+%d day', $horizonDays));
        $cursor = $today->modify('first day of this month');
        $occurrences = [];

        while ($cursor <= $endDate) {
            $lastDay = (int) $cursor->format('t');
            $targetDay = min($dayOfMonth, $lastDay);
            $candidate = $cursor->setDate(
                (int) $cursor->format('Y'),
                (int) $cursor->format('m'),
                $targetDay
            );

            if ($candidate > $today && $candidate <= $endDate) {
                $occurrences[] = [
                    'date' => $candidate->format('Y-m-d'),
                    'label' => $candidate->format('d/m'),
                ];
            }

            $cursor = $cursor->modify('+1 month');
        }

        return $occurrences;
    }

    private function resolveRecurringCreditSignature(string $category, string $description): string
    {
        $base = strtolower(trim($category));
        if ($base !== '' && $base !== 'autre') {
            return $base;
        }

        $cleanDescription = strtolower(trim(preg_replace('/\d+/', '', $description) ?? $description));

        return $cleanDescription !== '' ? $cleanDescription : 'credit';
    }

    private function resolveRecurringCreditLabel(string $category, string $description): string
    {
        $categoryLabel = trim($category);
        if ($categoryLabel !== '' && strtolower($categoryLabel) !== 'autre') {
            return $categoryLabel;
        }

        $descriptionLabel = trim($description);

        return $descriptionLabel !== '' ? $descriptionLabel : 'Revenu recurrent';
    }

    private function isRecurringIncomeKeyword(string $category, string $description): bool
    {
        $text = strtolower(trim($category.' '.$description));

        foreach (['salaire', 'salary', 'payroll', 'paie', 'revenu'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
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

    private function monthKeyFromOffset(int $monthsBack): string
    {
        return (new \DateTimeImmutable('first day of this month'))
            ->modify(sprintf('-%d month', max(0, $monthsBack)))
            ->format('Y-m');
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @return array<string, mixed>|null
     */
    private function resolveSurplusSourceAccount(array $accounts, float $requiredAmount): ?array
    {
        $activeAccounts = array_values(array_filter($accounts, static function (array $account): bool {
            return strtolower(trim((string) ($account['statutCompte'] ?? ''))) === 'actif';
        }));

        usort($activeAccounts, static fn (array $left, array $right): int => ((float) ($right['solde'] ?? 0.0)) <=> ((float) ($left['solde'] ?? 0.0)));

        foreach ($activeAccounts as $account) {
            if ((float) ($account['solde'] ?? 0.0) >= $requiredAmount) {
                return $account;
            }
        }

        return $activeAccounts[0] ?? null;
    }
}
