<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class GamificationService
{
    private const WHEEL_SEGMENTS = [5, 10, 20, 15, 8, 12, 3, 7, 25, 2, 18, 6];
    private const WHEEL_TARGET_POINTS = 100;
    private const WHEEL_REWARD_AMOUNT = 50.0;

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBankingSecurity $security,
        private readonly NotificationService $notificationService,
        private readonly ActivityService $activityService,
    ) {
    }

    public function getDragonState(int $userId): array
    {
        $accounts = $this->connection->fetchAllAssociative('SELECT * FROM compte WHERE idUser = ?', [$userId]);
        $vaults = $this->connection->fetchAllAssociative('SELECT * FROM coffrevirtuel WHERE idUser = ?', [$userId]);
        $recentTransactions = $this->connection->fetchAllAssociative(
            'SELECT * FROM transactions WHERE idUser = ? ORDER BY idTransaction DESC LIMIT 30',
            [$userId]
        );

        $totalBalance = array_sum(array_map(static fn (array $row): float => (float) ($row['solde'] ?? 0), $accounts));
        $activeVaults = count($vaults);
        $bestProgress = 0.0;
        foreach ($vaults as $vault) {
            $goal = max(1.0, (float) ($vault['objectifMontant'] ?? 0));
            $bestProgress = max($bestProgress, ((float) ($vault['montantActuel'] ?? 0)) / $goal);
        }

        $recentSpending = 0.0;
        $daysSinceSavings = 999;
        foreach ($recentTransactions as $row) {
            $amount = (float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0.0);
            
            // Résolution logique du type
            $typeRaw = strtolower(trim((string) ($row['typeTransaction'] ?? '')));
            $resolvedType = 'UNKNOWN';
            if (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                $resolvedType = 'CREDIT';
            } elseif (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || str_contains($typeRaw, 'paimenet')) {
                $resolvedType = 'DEBIT';
            } elseif (str_contains($typeRaw, 'virement')) {
                $source = (int) ($row['idCompte'] ?? 0);
                $dest   = (int) ($row['idCompteDestinataire'] ?? 0);
                // CREDIT si dest == NULL/0 (inconnu → reçu)
                // CREDIT si dest == compte source (reçu sur ce même compte)
                // DEBIT  si dest est un compte différent (envoyé)
                $resolvedType = ($dest === 0 || $dest === $source) ? 'CREDIT' : 'DEBIT';
            }

            if ($resolvedType === 'DEBIT') {
                $recentSpending += $amount;
            }

            $description = strtolower((string) ($row['description'] ?? ''));
            if ((str_contains($description, 'coffre') || str_contains($description, 'epargne')) && $daysSinceSavings === 999) {
                $date = $this->parseDate((string) ($row['dateTransaction'] ?? ''));
                if ($date !== null) {
                    $daysSinceSavings = (int) $date->diff(new \DateTimeImmutable())->format('%a');
                }
            }
        }

        $mood = 'neutre';
        $message = 'The dragon is watching the account and waiting for the next savings move.';
        if ($totalBalance > 3000 && $bestProgress >= 0.60) {
            $mood = 'heureux';
            $message = 'Savings progress is strong, so the dragon is in a very good mood.';
        } elseif ($daysSinceSavings > 60) {
            $mood = 'triste';
            $message = 'No recent coffre transfer was detected, and the dragon wants to be fed.';
        } elseif ($recentSpending > max(500, $totalBalance * 0.4)) {
            $mood = 'fache';
            $message = 'Recent spending is high compared with current balances. The dragon wants discipline.';
        } elseif ($bestProgress >= 0.25 || $activeVaults > 0) {
            $mood = 'peutContent';
            $message = 'Savings behavior exists, but the dragon expects more regular progress.';
        }

        $image = match ($mood) {
            'heureux' => 'images/hero_heureux.png',
            'triste' => 'images/hero_triste.png',
            'fache' => 'images/hero_fache.png',
            'peutContent' => 'images/hero_peutContent.png',
            default => 'images/hero_neutre.png',
        };

        return [
            'mood' => $mood,
            'image' => $image,
            'message' => $message,
            'total_balance' => round($totalBalance, 2),
            'vault_count' => $activeVaults,
            'savings_progress' => round($bestProgress * 100, 1),
            'days_since_savings' => $daysSinceSavings === 999 ? null : $daysSinceSavings,
            'advice' => $bestProgress < 0.4
                ? 'Feed the dragon from an active account to build a stronger coffre habit.'
                : 'Keep the current rhythm and avoid large unexplained debit spikes.',
        ];
    }

    public function feedDragon(int $userId, int $accountId, int $vaultId, float $amount): array
    {
        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Feed amount must be greater than zero.');
        }

        $this->connection->transactional(function () use ($userId, $accountId, $vaultId, $amount): void {
            $account = $this->connection->fetchAssociative(
                'SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1',
                [$accountId, $userId]
            );
            $vault = $this->connection->fetchAssociative(
                'SELECT * FROM coffrevirtuel WHERE idCoffre = ? AND idUser = ? LIMIT 1',
                [$vaultId, $userId]
            );

            if (!$account || !$vault) {
                throw new \RuntimeException('The selected account or vault was not found.');
            }

            $newBalance = round(((float) $account['solde']) - $amount, 2);
            if ($newBalance < 0) {
                throw new \RuntimeException('Account balance is not sufficient for the dragon feed.');
            }

            $newVaultAmount = round(((float) $vault['montantActuel']) + $amount, 2);

            $this->connection->update('compte', [
                'solde' => $newBalance,
            ], [
                'idCompte' => $accountId,
                'idUser' => $userId,
            ]);

            $this->connection->update('coffrevirtuel', [
                'montantActuel' => $newVaultAmount,
            ], [
                'idCoffre' => $vaultId,
                'idUser' => $userId,
            ]);

            $this->connection->insert('transactions', [
                'idCompte' => $accountId,
                'idUser' => $userId,
                'categorie' => 'Epargne',
                'dateTransaction' => date('Y-m-d'),
                'montant' => $this->security->encryptAmount($amount),
                'typeTransaction' => 'DEBIT',
                'soldeApres' => $newBalance,
                'description' => 'Virement coffre - dragon feed',
                'montantPaye' => $this->security->encryptAmount($amount),
            ]);

            $this->activityService->log($userId, 'DRAGON_FEED', 'Symfony portal', sprintf('Fed %.2f DT into vault #%d.', $amount, $vaultId));
        });

        return $this->getDragonState($userId);
    }

    public function getWheelStatus(int $userId): array
    {
        $eligibility = $this->getWheelEligibility($userId);
        $trustedMonth = $this->getTrustedMonth();
        $row = $this->connection->fetchAssociative('SELECT * FROM roue_fortune_points WHERE idUser = ? LIMIT 1', [$userId]) ?: [];
        $alreadyPlayed = trim((string) ($row['dernierMois'] ?? '')) === $trustedMonth;
        $totalPoints = (int) ($row['totalPoints'] ?? 0);

        return [
            'trusted_month' => $trustedMonth,
            'already_played' => $alreadyPlayed,
            'can_spin' => $eligibility['eligible'] && !$alreadyPlayed,
            'segments' => self::WHEEL_SEGMENTS,
            'total_points' => $totalPoints,
            'last_month' => (string) ($row['dernierMois'] ?? ''),
            'last_spin_date' => (string) ($row['dernierTour'] ?? ''),
            'last_points' => (int) ($row['pointsGagnes'] ?? 0),
            'bonus_ready' => $totalPoints >= self::WHEEL_TARGET_POINTS,
            'bonus_target' => self::WHEEL_TARGET_POINTS,
            'remaining_points' => max(0, self::WHEEL_TARGET_POINTS - $totalPoints),
            'reward_amount' => self::WHEEL_REWARD_AMOUNT,
            'eligibility' => $eligibility,
        ];
    }

    public function spinWheel(int $userId, ?int $rewardAccountId = null): array
    {
        $status = $this->getWheelStatus($userId);
        if (!$status['eligibility']['eligible']) {
            throw new \RuntimeException('Les trois conditions doivent etre respectees avant de tourner la roue.');
        }
        if ($status['already_played']) {
            throw new \RuntimeException('La roue ne peut etre tournee qu une seule fois par mois.');
        }

        $points = self::WHEEL_SEGMENTS[array_rand(self::WHEEL_SEGMENTS)];
        $trustedNow = $this->getTrustedNow();
        $trustedMonth = $trustedNow->format('Y-m');
        $trustedDate = $trustedNow->format('Y-m-d');

        $row = $this->connection->fetchAssociative('SELECT * FROM roue_fortune_points WHERE idUser = ? LIMIT 1', [$userId]);
        $total = min(self::WHEEL_TARGET_POINTS, (int) ($row['totalPoints'] ?? 0) + $points);

        if ($row) {
            $this->connection->update('roue_fortune_points', [
                'totalPoints' => $total,
                'dernierTour' => $trustedDate,
                'dernierMois' => $trustedMonth,
                'pointsGagnes' => $points,
            ], [
                'idUser' => $userId,
            ]);
        } else {
            $this->connection->insert('roue_fortune_points', [
                'idUser' => $userId,
                'totalPoints' => $total,
                'dernierTour' => $trustedDate,
                'dernierMois' => $trustedMonth,
                'pointsGagnes' => $points,
            ]);
        }

        $this->activityService->log($userId, 'WHEEL_SPIN', 'Symfony portal', sprintf('Wheel spin awarded %d points.', $points));

        $bonusCredit = null;
        if ($total >= self::WHEEL_TARGET_POINTS) {
            $account = $this->resolveWheelBonusAccount($userId, $rewardAccountId);
            $bonusCredit = $this->creditWheelBonus($userId, (int) $account['idCompte']);
        }

        $status = $this->getWheelStatus($userId);
        $status['spin_result'] = [
            'points' => $points,
            'bonus_credited' => $bonusCredit !== null,
            'reward_amount' => self::WHEEL_REWARD_AMOUNT,
            'credited_account' => $bonusCredit['numeroCompte'] ?? null,
            'message' => $bonusCredit !== null
                ? sprintf(
                    'La roue a ajoute +%d points. Le bonus de %.0f DT a ete credite sur le compte %s.',
                    $points,
                    self::WHEEL_REWARD_AMOUNT,
                    (string) ($bonusCredit['numeroCompte'] ?? '#'.$bonusCredit['idCompte'])
                )
                : sprintf(
                    'La roue a ajoute +%d points. Il manque encore %d point%s pour atteindre %.0f DT.',
                    $points,
                    max(0, self::WHEEL_TARGET_POINTS - $total),
                    max(0, self::WHEEL_TARGET_POINTS - $total) > 1 ? 's' : '',
                    self::WHEEL_REWARD_AMOUNT
                ),
        ];

        return $status;
    }

    public function claimWheelBonus(int $userId, int $accountId): array
    {
        $status = $this->getWheelStatus($userId);
        if (!$status['bonus_ready']) {
            throw new \RuntimeException('Le bonus de 50 DT n est pas encore disponible.');
        }

        $this->creditWheelBonus($userId, $accountId);

        return $this->getWheelStatus($userId);
    }

    public function getWheelEligibility(int $userId): array
    {
        $month = $this->getTrustedMonth();
        $accounts = $this->connection->fetchAllAssociative(
            'SELECT idCompte, numeroCompte, solde, statutCompte FROM compte WHERE idUser = ? ORDER BY idCompte DESC',
            [$userId]
        );
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM transactions WHERE idUser = ? AND dateTransaction LIKE ? ORDER BY idTransaction DESC',
            [$userId, $month.'%']
        );

        $currentBalance = 0.0;
        foreach ($accounts as $account) {
            $currentBalance += (float) ($account['solde'] ?? 0);
        }

        $conditionSolde = $currentBalance > 0;
        $conditionBudget = false;
        $conditionEpargne = false;
        $totalCredits = 0.0;
        $totalDebits = 0.0;
        $savingsTransfers = 0;

        foreach ($rows as $row) {
                    $amount = (float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0.0);

        $typeRaw = strtolower(trim((string) ($row['typeTransaction'] ?? '')));
        $resolvedType = 'UNKNOWN';

        // =========================
        // CREDIT
        // =========================
        if (
            str_contains($typeRaw, 'depot') ||
            str_contains($typeRaw, 'versement')
        ) {
            $resolvedType = 'CREDIT';
        }

        // =========================
        // DEBIT
        // =========================
        elseif (
            str_contains($typeRaw, 'paiement') ||
            str_contains($typeRaw, 'retrait') ||
            str_contains($typeRaw, 'paimenet')
        ) {
            $resolvedType = 'DEBIT';
        }

        // =========================
        // VIREMENT
        // =========================
        elseif (str_contains($typeRaw, 'virement')) {
            $source = (int) ($row['idCompte'] ?? 0);
            $dest   = (int) ($row['idCompteDestinataire'] ?? 0);
            // CREDIT si dest == compte source (reçu sur ce compte)
            // CREDIT si dest == NULL/0 (destinataire inconnu → reçu)
            // DEBIT  si dest est un compte différent du source (envoyé)
            $resolvedType = ($dest === 0 || $dest === $source) ? 'CREDIT' : 'DEBIT';
        }

        // =========================
        // CREDIT / DEBIT ACCUMULATION
        // =========================
        if ($resolvedType === 'CREDIT') {
            $totalCredits += $amount;
        }

        if ($resolvedType === 'DEBIT') {
            $totalDebits += $amount;

            $description = strtolower((string) ($row['description'] ?? ''));
            $category = strtolower((string) ($row['categorie'] ?? ''));

            if (
                $category === 'epargne' ||
                str_contains($description, 'coffre') ||
                str_contains($description, 'epargne')
            ) {
                $conditionEpargne = true;
                $savingsTransfers++;
            }
        }
    }

         // 📊 Budget rule

        $budgetLimit = round($totalCredits * 0.8, 2);
        if ($totalCredits > 0) {
            $conditionBudget = $totalDebits <= $budgetLimit;
        }

         // 🎯 Final eligibility
        $conditionsOk = ($conditionSolde ? 1 : 0) + ($conditionBudget ? 1 : 0) + ($conditionEpargne ? 1 : 0);
        $eligible = $conditionsOk === 3;

        $conditions = [
            'budget' => [
                'key' => 'budget',
                'label' => 'Le total des depenses doit etre inferieur ou egal a 80 % des revenus.',
                'short_label' => 'Budget mensuel maitrise',
                'ok' => $conditionBudget,
                'detail' => sprintf(
                    'Depenses: %.2f DT | Revenus: %.2f DT | Seuil: %.2f DT',
                    round($totalDebits, 2),
                    round($totalCredits, 2),
                    $budgetLimit
                ),
                'reason' => $conditionBudget
                    ? 'Les depenses du mois restent sous le seuil autorise.'
                    : ($totalCredits <= 0
                        ? 'Aucun revenu credite ce mois-ci, le ratio depenses/revenus ne peut pas etre valide.'
                        : sprintf('Les depenses atteignent %.2f DT alors que le seuil autorise est de %.2f DT.', round($totalDebits, 2), $budgetLimit)),
                'solution' => $conditionBudget
                    ? 'Continuez ce rythme pour conserver l acces a la roue.'
                    : ($totalCredits <= 0
                        ? 'Creditez un revenu ce mois-ci ou attendez le prochain revenu avant de tourner la roue.'
                        : sprintf('Reduisez les depenses d au moins %.2f DT ou augmentez les revenus de ce mois.', max(0, round($totalDebits - $budgetLimit, 2)))),
            ],
            'balance' => [
                'key' => 'balance',
                'label' => 'Le solde actuel doit etre positif.',
                'short_label' => 'Solde actuel positif',
                'ok' => $conditionSolde,
                'detail' => sprintf('Solde actuel cumule: %.2f DT', round($currentBalance, 2)),
                'reason' => $conditionSolde
                    ? 'Le solde cumule des comptes est positif.'
                    : 'Le solde cumule actuel est nul ou negatif.',
                'solution' => $conditionSolde
                    ? 'Gardez une marge positive sur vos comptes.'
                    : sprintf('Ajoutez au moins %.2f DT sur vos comptes pour repasser au-dessus de zero.', abs(round($currentBalance, 2)) + 0.01),
            ],
            'savings' => [
                'key' => 'savings',
                'label' => 'Un transfert vers un coffre doit avoir ete effectue ce mois-ci.',
                'short_label' => 'Transfert vers epargne detecte',
                'ok' => $conditionEpargne,
                'detail' => $conditionEpargne
                    ? sprintf('%d transfert%s vers un coffre detecte%s.', $savingsTransfers, $savingsTransfers > 1 ? 's' : '', $savingsTransfers > 1 ? 's' : '')
                    : 'Aucun transfert vers un coffre ou une categorie epargne n a ete detecte ce mois-ci.',
                'reason' => $conditionEpargne
                    ? 'Au moins un debit vers le coffre ou l epargne a ete trouve.'
                    : 'Aucun debit avec la categorie epargne ni une description contenant coffre ou epargne n a ete trouve ce mois-ci.',
                'solution' => $conditionEpargne
                    ? 'Votre habitude d epargne debloque bien cette condition.'
                    : 'Effectuez un debit vers un coffre ou une transaction categorisee epargne pour debloquer la roue.',
            ],
        ];

        $failedConditions = array_values(array_filter(
            array_map(
                static fn (array $condition): ?array => $condition['ok'] ? null : $condition,
                $conditions
            )
        ));

        $suggestion = $eligible
            ? 'Les trois conditions sont validees. Vous pouvez tourner la roue une fois ce mois-ci.'
            : implode(' ', array_map(
                static fn (array $condition): string => $condition['solution'],
                $failedConditions
            ));

        return [
            'eligible' => $eligible,
            'condition_solde' => $conditionSolde,
            'condition_budget' => $conditionBudget,
            'condition_epargne' => $conditionEpargne,
            'conditions_ok' => $conditionsOk,
            'conditions' => $conditions,
            'failed_conditions' => $failedConditions,
            'message' => $eligible
                ? 'Les trois conditions du mois sont respectees. La roue est debloquee.'
                : 'La roue restera verrouillee tant que les trois conditions ne sont pas toutes valides.',
            'suggestion' => $suggestion,
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'budget_limit' => $budgetLimit,
            'current_balance' => round($currentBalance, 2),
            'savings_transfers' => $savingsTransfers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWheelBonusAccount(int $userId, ?int $preferredAccountId = null): array
    {
        if ($preferredAccountId !== null && $preferredAccountId > 0) {
            $preferred = $this->connection->fetchAssociative(
                'SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1',
                [$preferredAccountId, $userId]
            );
            if ($preferred) {
                return $preferred;
            }
        }

        $account = $this->connection->fetchAssociative(
            "SELECT * FROM compte
             WHERE idUser = ?
             ORDER BY CASE WHEN LOWER(TRIM(statutCompte)) = 'actif' THEN 0 ELSE 1 END, solde DESC, idCompte DESC
             LIMIT 1",
            [$userId]
        );

        if (!$account) {
            throw new \RuntimeException('Aucun compte disponible pour crediter le bonus de la roue.');
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function creditWheelBonus(int $userId, int $accountId): array
    {
        $account = $this->connection->fetchAssociative(
            'SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1',
            [$accountId, $userId]
        );

        if (!$account) {
            throw new \RuntimeException('Le compte choisi pour recevoir le bonus est introuvable.');
        }

        $newBalance = round(((float) $account['solde']) + self::WHEEL_REWARD_AMOUNT, 2);
        $trustedDate = $this->getTrustedNow()->format('Y-m-d');

        $this->connection->transactional(function () use ($userId, $accountId, $newBalance, $trustedDate): void {
            $this->connection->update('compte', [
                'solde' => $newBalance,
            ], [
                'idCompte' => $accountId,
                'idUser' => $userId,
            ]);

            $this->connection->insert('transactions', [
                'idCompte' => $accountId,
                'idUser' => $userId,
                'categorie' => 'Recompense',
                'dateTransaction' => $trustedDate,
                'montant' => $this->security->encryptAmount(self::WHEEL_REWARD_AMOUNT),
                'typeTransaction' => 'CREDIT',
                'soldeApres' => $newBalance,
                'description' => 'Bonus roue de fortune +50 DT',
                'montantPaye' => $this->security->encryptAmount(self::WHEEL_REWARD_AMOUNT),
            ]);

            $this->connection->update('roue_fortune_points', [
                'totalPoints' => 0,
            ], [
                'idUser' => $userId,
            ]);
        });

        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'WHEEL_BONUS',
            'Bonus roue credite',
            sprintf('Le bonus de %.0f DT de la roue a ete ajoute au compte %s.', self::WHEEL_REWARD_AMOUNT, (string) ($account['numeroCompte'] ?? '#'.$accountId))
        );
        $this->activityService->log($userId, 'WHEEL_BONUS', 'Symfony portal', sprintf('Wheel bonus credited to account #%d.', $accountId));

        $account['solde'] = $newBalance;

        return $account;
    }

    private function getTrustedNow(): \DateTimeImmutable
    {
        $value = $this->connection->fetchOne('SELECT NOW()');

        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable(trim($value));
            } catch (\Throwable) {
            }
        }

        return new \DateTimeImmutable('now');
    }

    private function getTrustedMonth(): string
    {
        return $this->getTrustedNow()->format('Y-m');
    }

    private function parseDate(string $date): ?\DateTimeImmutable
    {
        $trimmed = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
            return null;
        }

        try {
            return new \DateTimeImmutable(substr($trimmed, 0, 10));
        } catch (\Throwable) {
            return null;
        }
    }
}