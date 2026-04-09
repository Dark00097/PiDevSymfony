<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class GamificationService
{
    private const WHEEL_SEGMENTS = [5, 10, 20, 15, 8, 12, 3, 7, 25, 2, 18, 6];

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
            if (strtoupper((string) ($row['typeTransaction'] ?? '')) === 'DEBIT') {
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
                'statutTransaction' => 'VALIDED',
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
            'total_points' => $totalPoints,
            'last_month' => (string) ($row['dernierMois'] ?? ''),
            'last_spin_date' => (string) ($row['dernierTour'] ?? ''),
            'last_points' => (int) ($row['pointsGagnes'] ?? 0),
            'bonus_ready' => $totalPoints >= 100,
            'eligibility' => $eligibility,
        ];
    }

    public function spinWheel(int $userId): array
    {
        $status = $this->getWheelStatus($userId);
        if (!$status['eligibility']['eligible']) {
            throw new \RuntimeException('Wheel conditions are not fully satisfied this month.');
        }
        if ($status['already_played']) {
            throw new \RuntimeException('The wheel was already played this month.');
        }

        $points = self::WHEEL_SEGMENTS[array_rand(self::WHEEL_SEGMENTS)];
        $trustedMonth = $this->getTrustedMonth();
        $trustedDate = (new \DateTimeImmutable())->format('Y-m-d');

        $row = $this->connection->fetchAssociative('SELECT * FROM roue_fortune_points WHERE idUser = ? LIMIT 1', [$userId]);
        $total = min(100, (int) ($row['totalPoints'] ?? 0) + $points);

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

        $status = $this->getWheelStatus($userId);
        $status['spin_result'] = [
            'points' => $points,
            'message' => $total >= 100
                ? sprintf('Spin complete: +%d points. The 50 DT bonus is now ready.', $points)
                : sprintf('Spin complete: +%d points. %d points remain until the bonus.', $points, max(0, 100 - $total)),
        ];

        return $status;
    }

    public function claimWheelBonus(int $userId, int $accountId): array
    {
        $status = $this->getWheelStatus($userId);
        if (!$status['bonus_ready']) {
            throw new \RuntimeException('The 50 DT bonus is not available yet.');
        }

        $account = $this->connection->fetchAssociative(
            'SELECT * FROM compte WHERE idCompte = ? AND idUser = ? LIMIT 1',
            [$accountId, $userId]
        );

        if (!$account) {
            throw new \RuntimeException('The selected active account was not found.');
        }

        $newBalance = round(((float) $account['solde']) + 50, 2);

        $this->connection->transactional(function () use ($userId, $accountId, $newBalance): void {
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
                'dateTransaction' => date('Y-m-d'),
                'montant' => $this->security->encryptAmount(50),
                'typeTransaction' => 'CREDIT',
                'statutTransaction' => 'VALIDED',
                'soldeApres' => $newBalance,
                'description' => 'Bonus roue de fortune +50 DT',
                'montantPaye' => $this->security->encryptAmount(50),
            ]);

            $this->connection->update('roue_fortune_points', [
                'totalPoints' => 0,
                'pointsGagnes' => 0,
            ], [
                'idUser' => $userId,
            ]);
        });

        $this->notificationService->createNotification(
            $userId,
            null,
            $userId,
            'WHEEL_BONUS',
            'Wheel bonus credited',
            'The 50 DT reward from the Roue de Fortune was credited to your selected account.'
        );
        $this->activityService->log($userId, 'WHEEL_BONUS', 'Symfony portal', 'Claimed the 50 DT wheel bonus.');

        return $this->getWheelStatus($userId);
    }

    public function getWheelEligibility(int $userId): array
    {
        $month = $this->getTrustedMonth();
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM transactions WHERE idUser = ? AND dateTransaction LIKE ? ORDER BY idTransaction DESC',
            [$userId, $month.'%']
        );

        $conditionSolde = true;
        $conditionBudget = true;
        $conditionEpargne = false;
        $totalCredits = 0.0;
        $totalDebits = 0.0;
        $minBalance = null;

        foreach ($rows as $row) {
            $amount = (float) ($this->security->decryptAmount((string) ($row['montant'] ?? '')) ?? 0.0);
            $balance = ($row['soldeApres'] ?? null) !== null ? (float) $row['soldeApres'] : null;
            if ($balance !== null) {
                $minBalance = $minBalance === null ? $balance : min($minBalance, $balance);
                if ($balance < 0) {
                    $conditionSolde = false;
                }
            }

            $type = strtoupper((string) ($row['typeTransaction'] ?? ''));
            if ($type === 'CREDIT') {
                $totalCredits += $amount;
            } elseif ($type === 'DEBIT') {
                $totalDebits += $amount;
                $description = strtolower((string) ($row['description'] ?? ''));
                $category = strtolower((string) ($row['categorie'] ?? ''));
                if ($category === 'epargne' || str_contains($description, 'coffre') || str_contains($description, 'epargne')) {
                    $conditionEpargne = true;
                }
            }
        }

        if ($totalCredits > 0) {
            $conditionBudget = $totalDebits <= ($totalCredits * 0.8);
        } else {
            $conditionBudget = false;
        }

        $conditionsOk = ($conditionSolde ? 1 : 0) + ($conditionBudget ? 1 : 0) + ($conditionEpargne ? 1 : 0);
        $eligible = $conditionsOk === 3;

        $suggestions = [];
        if (!$conditionSolde) {
            $suggestions[] = sprintf('Recharge at least %.2f DT to bring the lowest monthly balance back to non-negative.', abs((float) ($minBalance ?? 0)));
        }
        if (!$conditionBudget) {
            $suggestions[] = sprintf('Monthly debits are %.2f DT against %.2f DT in credits, so spending needs to move under the 80%% threshold.', $totalDebits, $totalCredits * 0.8);
        }
        if (!$conditionEpargne) {
            $suggestions[] = 'Create at least one coffre or savings transfer this month.';
        }

        return [
            'eligible' => $eligible,
            'condition_solde' => $conditionSolde,
            'condition_budget' => $conditionBudget,
            'condition_epargne' => $conditionEpargne,
            'conditions_ok' => $conditionsOk,
            'message' => $eligible
                ? 'All three monthly conditions are satisfied. The wheel is unlocked.'
                : 'The wheel remains locked until all three monthly conditions are satisfied.',
            'suggestion' => $eligible ? 'Spin once this month to accumulate points.' : implode(' ', $suggestions),
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
        ];
    }

    private function getTrustedMonth(): string
    {
        $month = $this->connection->fetchOne("SELECT DATE_FORMAT(NOW(), '%Y-%m')");

        return is_string($month) && trim($month) !== ''
            ? trim($month)
            : (new \DateTimeImmutable())->format('Y-m');
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
