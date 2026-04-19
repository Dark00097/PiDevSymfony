<?php

declare(strict_types=1);

namespace App\Service;

final class BankingMlAssistantService
{
    public function __construct(
        private readonly BankingService $bankingService,
        private readonly BankingMlModelService $modelService,
    ) {
    }

    public function buildAdminAssistantData(?int $filterUserId = null): array
    {
        $context = $this->buildAssistantContext($filterUserId, true);
        $profiles = $context['profiles'];

        if ($profiles === []) {
            return $this->buildEmptyPayload(
                $context['dataset_counts'],
                $context['model_paths'],
                $context['training_notice']
            );
        }

        usort($profiles, static fn (array $left, array $right): int => ((int) ($right['risk_score'] ?? 0)) <=> ((int) ($left['risk_score'] ?? 0)));

        $segmentCounts = [
            'premium' => 0,
            'stable' => 0,
            'fragile' => 0,
            'risque' => 0,
        ];
        $distribution = [
            'stables' => 0,
            'epargnants' => 0,
            'a_risque' => 0,
        ];
        $stableClients = 0;
        $riskClients = 0;
        $scoreSum = 0;
        $alerts = [];

        foreach ($profiles as $profile) {
            $segmentKey = (string) ($profile['segment_key'] ?? 'fragile');
            if (array_key_exists($segmentKey, $segmentCounts)) {
                $segmentCounts[$segmentKey]++;
            }

            $scoreSum += (int) ($profile['risk_score'] ?? 0);
            $profileLabel = mb_strtolower((string) ($profile['profile_label'] ?? ''), 'UTF-8');

            if (in_array($segmentKey, ['premium', 'stable'], true)) {
                $stableClients++;
            }
            if ($segmentKey === 'risque') {
                $riskClients++;
            }

            if (str_contains($profileLabel, 'epargn') || str_contains($profileLabel, 'premium')) {
                $distribution['epargnants']++;
            } elseif ($segmentKey === 'risque' || $segmentKey === 'fragile') {
                $distribution['a_risque']++;
            } else {
                $distribution['stables']++;
            }

            foreach ((array) ($profile['alerts'] ?? []) as $alert) {
                $alerts[] = $alert;
            }
        }

        usort($alerts, fn (array $left, array $right): int => $this->alertPriority($right) <=> $this->alertPriority($left));
        $alerts = array_slice($alerts, 0, 4);

        return [
            'overview' => [
                'client_count' => count($profiles),
                'stable_count' => $stableClients,
                'risk_count' => $riskClients,
                'alert_count' => count($alerts),
                'average_score' => round($scoreSum / max(count($profiles), 1), 1),
            ],
            'table' => array_map(static function (array $profile): array {
                return [
                    'client_id' => $profile['client_id'],
                    'client_name' => $profile['client_name'],
                    'balance' => $profile['total_balance'],
                    'vault_total' => $profile['vault_current'],
                    'profile_label' => $profile['profile_label'],
                    'risk_score' => $profile['risk_score'],
                    'confidence' => $profile['confidence'],
                    'badge_tone' => $profile['badge_tone'],
                ];
            }, $profiles),
            'profiles' => array_values($profiles),
            'focus_client' => $this->resolveFocusClient($profiles),
            'segments' => $this->buildSegments($segmentCounts),
            'distribution' => [
                [
                    'label' => 'Stables',
                    'count' => $distribution['stables'],
                    'color' => '#2b7de9',
                ],
                [
                    'label' => 'Epargnants',
                    'count' => $distribution['epargnants'],
                    'color' => '#11b7aa',
                ],
                [
                    'label' => 'A risque',
                    'count' => $distribution['a_risque'],
                    'color' => '#e53935',
                ],
            ],
            'alerts' => $alerts,
            'recommendations' => $this->buildGlobalRecommendations($segmentCounts, $alerts),
            'dataset_counts' => $context['dataset_counts'],
            'model_paths' => $context['model_paths'],
            'training_notice' => $context['training_notice'],
        ];
    }

    public function buildTrainingDatasets(?int $filterUserId = null): array
    {
        return $this->buildAssistantContext($filterUserId, false)['datasets'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssistantContext(?int $filterUserId, bool $applyMlPredictions): array
    {
        $users = $this->indexUsers($this->bankingService->listUsers());
        $accounts = $this->bankingService->listAccounts($filterUserId);
        $vaults = $this->bankingService->listVaults($filterUserId);
        $transactions = $this->bankingService->listTransactions($filterUserId);
        $modelPaths = $this->modelService->getModelPaths();

        $buckets = $this->collectProfileBuckets($users, $accounts, $vaults, $transactions);
        $profiles = [];
        foreach ($buckets as $userId => $bucket) {
            $profiles[] = $this->hydrateProfile(
                $userId,
                (array) ($bucket['user'] ?? []),
                (array) ($bucket['accounts'] ?? []),
                (array) ($bucket['vaults'] ?? []),
                (array) ($bucket['transactions'] ?? [])
            );
        }

        $datasets = $this->buildDatasetRows($profiles, $buckets);
        $fallbackCounts = [
            'kmeans' => count($datasets['kmeans']),
            'classification' => count($datasets['classification']),
            'anomalies' => count($datasets['anomalies']),
        ];
        $datasetCounts = $this->modelService->getDatasetCounts($fallbackCounts);
        $trainingNotice = $this->modelService->getStatus()['training_notice'];

        if ($applyMlPredictions && $profiles !== []) {
            $predictions = $this->modelService->predict(
                $datasets['classification'],
                $datasets['kmeans'],
                $datasets['anomalies']
            );
            $trainingNotice = (string) ($predictions['training_notice'] ?? $trainingNotice);
            $profiles = $this->applyMlPredictions($profiles, $predictions);
        }

        return [
            'profiles' => $profiles,
            'datasets' => $datasets,
            'dataset_counts' => $datasetCounts,
            'model_paths' => $modelPaths,
            'training_notice' => $trainingNotice,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @param array<int, array<string, mixed>> $accounts
     * @param array<int, array<string, mixed>> $vaults
     * @param array<int, array<string, mixed>> $transactions
     * @return array<int, array<string, mixed>>
     */
    private function collectProfileBuckets(array $users, array $accounts, array $vaults, array $transactions): array
    {
        $profiles = [];
        $accountOwners = [];

        foreach ($accounts as $account) {
            $userId = $this->resolveClientId($account['idUser'] ?? null, $users);
            if ($userId === null) {
                continue;
            }

            $accountOwners[(int) ($account['idCompte'] ?? 0)] = $userId;
            $profiles[$userId]['user'] = $users[$userId];
            $profiles[$userId]['accounts'][] = $account;
            $profiles[$userId]['vaults'] ??= [];
            $profiles[$userId]['transactions'] ??= [];
        }

        foreach ($vaults as $vault) {
            $userId = $this->resolveClientId($vault['resolved_user_id'] ?? $vault['idUser'] ?? null, $users);
            if ($userId === null) {
                $accountId = (int) ($vault['idCompte'] ?? 0);
                $userId = $accountOwners[$accountId] ?? null;
            }
            if ($userId === null) {
                continue;
            }

            $profiles[$userId]['user'] ??= $users[$userId];
            $profiles[$userId]['accounts'] ??= [];
            $profiles[$userId]['vaults'][] = $vault;
            $profiles[$userId]['transactions'] ??= [];
        }

        foreach ($transactions as $transaction) {
            $userId = $this->resolveClientId($transaction['resolved_user_id'] ?? $transaction['idUser'] ?? null, $users);
            if ($userId === null) {
                $accountId = (int) ($transaction['idCompte'] ?? 0);
                $userId = $accountOwners[$accountId] ?? null;
            }
            if ($userId === null) {
                continue;
            }

            $profiles[$userId]['user'] ??= $users[$userId];
            $profiles[$userId]['accounts'] ??= [];
            $profiles[$userId]['vaults'] ??= [];
            $profiles[$userId]['transactions'][] = $transaction;
        }

        return $profiles;
    }

    /**
     * @param array<int, array<string, mixed>> $profiles
     * @param array<int, array<string, mixed>> $buckets
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildDatasetRows(array $profiles, array $buckets): array
    {
        $profilesById = [];
        foreach ($profiles as $profile) {
            $profilesById[(int) $profile['client_id']] = $profile;
        }

        return [
            'kmeans' => array_map(fn (array $profile): array => $this->buildKmeansDatasetRow($profile), $profiles),
            'classification' => array_map(fn (array $profile): array => $this->buildClassificationDatasetRow($profile), $profiles),
            'anomalies' => $this->buildAnomalyDatasetRows($profilesById, $buckets),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $accounts
     * @param array<int, array<string, mixed>> $vaults
     * @param array<int, array<string, mixed>> $transactions
     * @return array<string, mixed>
     */
    private function hydrateProfile(int $userId, array $user, array $accounts, array $vaults, array $transactions): array
    {
        $accountCount = count($accounts);
        $totalBalance = 0.0;
        $activeAccounts = 0;
        $blockedAccounts = 0;
        $closedAccounts = 0;
        $ageDays = [];
        $oldestAccountDate = null;
        $accountTypes = [];
        $maxWithdrawalLimit = 0.0;
        $maxTransferLimit = 0.0;

        foreach ($accounts as $account) {
            $totalBalance += (float) ($account['solde'] ?? 0);
            $status = $this->normalizeText((string) ($account['statutCompte'] ?? ''));
            $accountType = trim((string) ($account['typeCompte'] ?? ''));
            if ($accountType !== '') {
                $accountTypes[$accountType] = ($accountTypes[$accountType] ?? 0) + 1;
            }

            $maxWithdrawalLimit = max($maxWithdrawalLimit, (float) ($account['plafondRetrait'] ?? 0));
            $maxTransferLimit = max($maxTransferLimit, (float) ($account['plafondVirement'] ?? 0));

            if (str_contains($status, 'actif')) {
                ++$activeAccounts;
            } elseif (str_contains($status, 'bloque')) {
                ++$blockedAccounts;
            } elseif (str_contains($status, 'ferme')) {
                ++$closedAccounts;
            }

            $rawDate = trim((string) ($account['dateOuverture'] ?? ''));
            $oldestAccountDate = $this->minDateString($oldestAccountDate, $rawDate);
            $days = $this->daysSince($rawDate);
            if ($days !== null) {
                $ageDays[] = $days;
            }
        }

        $vaultCount = count($vaults);
        $vaultCurrent = 0.0;
        $vaultGoal = 0.0;
        $lockedVaults = 0;
        $activeVaults = 0;
        $blockedVaults = 0;
        $primaryVaultName = '';
        $largestVaultGoal = -1.0;
        $vaultDateCreationMin = null;
        $vaultDateObjectifsMax = null;

        foreach ($vaults as $vault) {
            $vaultCurrent += (float) ($vault['montantActuel'] ?? 0);
            $currentGoal = (float) ($vault['objectifMontant'] ?? 0);
            $vaultGoal += $currentGoal;
            $lockedVaults += (int) ($vault['estVerrouille'] ?? 0) === 1 ? 1 : 0;

            $status = $this->normalizeText((string) ($vault['status'] ?? ''));
            if (str_contains($status, 'actif')) {
                ++$activeVaults;
            }
            if (str_contains($status, 'bloque')) {
                ++$blockedVaults;
            }

            if ($currentGoal > $largestVaultGoal) {
                $largestVaultGoal = $currentGoal;
                $primaryVaultName = trim((string) ($vault['nom'] ?? ''));
            }

            $vaultDateCreationMin = $this->minDateString($vaultDateCreationMin, trim((string) ($vault['dateCreation'] ?? '')));
            $vaultDateObjectifsMax = $this->maxDateString($vaultDateObjectifsMax, trim((string) ($vault['dateObjectifs'] ?? '')));
        }

        $transactionCount = count($transactions);
        $debitCount = 0;
        $creditCount = 0;
        $virementCount = 0;
        $paymentCount = 0;
        $debitVolume = 0.0;
        $creditVolume = 0.0;
        $totalAmount = 0.0;
        $anomalyCount = 0;
        $categoryCounts = [
            'alimentation' => 0,
            'transport' => 0,
            'education' => 0,
            'shopping' => 0,
            'autres' => 0,
        ];

        foreach ($transactions as $transaction) {
            $amount = abs((float) ($transaction['montant_value'] ?? $transaction['montant'] ?? 0));
            $totalAmount += $amount;

            $typeRaw = $this->normalizeText((string) ($transaction['typeTransaction'] ?? ''));
            $sensRaw = $this->normalizeText((string) ($transaction['sens'] ?? ''));
            
            // Calcul du SENS selon les règles métier demandées :
            // Paiements et retraits -> Débit
            // Dépôts -> Crédit
            // Virements -> Sortie (Débit) ou Entrée (Crédit)
            $resolvedSens = $sensRaw;
            if ($resolvedSens !== 'debit' && $resolvedSens !== 'credit') {
                if (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || str_contains($typeRaw, 'paimenet')) {
                    $resolvedSens = 'debit';
                } elseif (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                    $resolvedSens = 'credit';
                } elseif (str_contains($typeRaw, 'virement')) {
                    // Pour les virements, on regarde s'il y a un compte destination
                    // Si idCompteDestinataire est présent, c'est généralement une sortie (débit)
                    $dest = trim((string)($transaction['idCompteDestinataire'] ?? ''));
                    $resolvedSens = ($dest !== '' && $dest !== '0') ? 'debit' : 'credit';
                } else {
                    // Fallback par défaut sur le type
                    $resolvedSens = (str_contains($typeRaw, 'debit')) ? 'debit' : 'credit';
                }
            }

            if ($resolvedSens === 'debit') {
                ++$debitCount;
                $debitVolume += $amount;
            } else {
                ++$creditCount;
                $creditVolume += $amount;
            }

            if (str_contains($typeRaw, 'virement')) {
                ++$virementCount;
            }
            if (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'payment') || str_contains($typeRaw, 'paimenet')) {
                ++$paymentCount;
            }

            $bucket = $this->resolveCategoryBucket((string) ($transaction['categorie'] ?? ''));
            $categoryCounts[$bucket]++;

            if ((bool) ($transaction['is_anomalie'] ?? false)) {
                ++$anomalyCount;
            }
        }

        $avgBalance = $accountCount > 0 ? round($totalBalance / $accountCount, 2) : 0.0;
        $avgAccountAgeDays = $ageDays !== [] ? round(array_sum($ageDays) / count($ageDays), 1) : 0.0;
        $vaultProgress = $vaultGoal > 0 ? round(min(100, ($vaultCurrent / $vaultGoal) * 100), 1) : 0.0;
        $savingsRate = ($totalBalance + $vaultCurrent) > 0 ? round(($vaultCurrent / ($totalBalance + $vaultCurrent)) * 100, 1) : 0.0;
        $debitCreditRatio = round($debitVolume / max($creditVolume, 1.0), 2);
        $averageTransactionAmount = $transactionCount > 0 ? round($totalAmount / $transactionCount, 2) : 0.0;

        $riskScore = 35;
        $riskScore += min(18, $activeAccounts * 6);
        $riskScore += match (true) {
            $totalBalance >= 30000 => 22,
            $totalBalance >= 10000 => 16,
            $totalBalance >= 3000 => 11,
            $totalBalance >= 1000 => 7,
            default => 2,
        };
        $riskScore += match (true) {
            $vaultProgress >= 80 => 14,
            $vaultProgress >= 50 => 10,
            $vaultProgress >= 20 => 5,
            default => 0,
        };
        $riskScore += match (true) {
            $savingsRate >= 35 => 12,
            $savingsRate >= 20 => 8,
            $savingsRate >= 10 => 4,
            default => 0,
        };
        $riskScore += match (true) {
            $avgAccountAgeDays >= 30 => 8,
            $avgAccountAgeDays >= 10 => 4,
            $avgAccountAgeDays > 0 => 2,
            default => 0,
        };
        $riskScore -= $blockedAccounts * 11;
        $riskScore -= $closedAccounts * 4;
        $riskScore -= $anomalyCount * 18;
        if ($transactionCount === 0) {
            $riskScore -= 3;
        }
        if ($debitCreditRatio > 0.75 && $creditVolume > 0) {
            $riskScore -= 6;
        }
        if ($totalBalance < 500) {
            $riskScore -= 6;
        }
        $riskScore = max(8, min(97, (int) round($riskScore)));

        $segmentMeta = $this->resolveSegmentMetadata(
            $anomalyCount > 0 || ($blockedAccounts > 0 && $riskScore < 60) || $riskScore < 45
                ? 'A risque'
                : ($totalBalance >= 15000 && $savingsRate >= 4
                    ? 'Premium'
                    : ($riskScore >= 72 && $vaultCount > 0 && $savingsRate >= 20
                        ? 'Epargnant'
                        : ($riskScore >= 60 ? 'Stable' : 'Fragile')))
        );

        $confidence = (int) max(58, min(95, round(60 + abs($riskScore - 50) / 2 + min(10, $transactionCount * 2))));
        $name = trim(((string) ($user['prenom'] ?? '')).' '.((string) ($user['nom'] ?? '')));
        $name = $name !== '' ? $name : 'Client #'.$userId;

        return [
            'client_id' => $userId,
            'client_name' => $name,
            'client_email' => trim((string) ($user['email'] ?? '')),
            'initials' => $this->initialsFromName($name),
            'account_count' => $accountCount,
            'principal_account_type' => $this->resolvePrimaryTextValue($accountTypes, 'Courant'),
            'oldest_account_date' => $oldestAccountDate ?? '',
            'total_balance' => round($totalBalance, 2),
            'avg_balance' => $avgBalance,
            'active_accounts' => $activeAccounts,
            'blocked_accounts' => $blockedAccounts,
            'closed_accounts' => $closedAccounts,
            'max_withdrawal_limit' => round($maxWithdrawalLimit, 2),
            'max_transfer_limit' => round($maxTransferLimit, 2),
            'vault_count' => $vaultCount,
            'primary_vault_name' => $primaryVaultName,
            'vault_current' => round($vaultCurrent, 2),
            'vault_goal' => round($vaultGoal, 2),
            'vault_date_creation_min' => $vaultDateCreationMin ?? '',
            'vault_date_objectifs_max' => $vaultDateObjectifsMax ?? '',
            'vault_progress' => $vaultProgress,
            'locked_vaults' => $lockedVaults,
            'active_vaults' => $activeVaults,
            'blocked_vaults' => $blockedVaults,
            'savings_rate' => $savingsRate,
            'transaction_count' => $transactionCount,
            'debit_count' => $debitCount,
            'credit_count' => $creditCount,
            'virement_count' => $virementCount,
            'payment_count' => $paymentCount,
            'debit_volume' => round($debitVolume, 2),
            'credit_volume' => round($creditVolume, 2),
            'average_transaction_amount' => $averageTransactionAmount,
            'debit_credit_ratio' => $debitCreditRatio,
            'avg_account_age_days' => $avgAccountAgeDays,
            'anomaly_count' => $anomalyCount,
            'category_counts' => $categoryCounts,
            'anomaly_reason' => $this->buildAnomalyReason($segmentMeta['segment_key'], $blockedAccounts, $anomalyCount, $vaultProgress, $transactionCount),
            'risk_score' => $riskScore,
            'profile_label' => $segmentMeta['profile_label'],
            'segment_key' => $segmentMeta['segment_key'],
            'confidence' => $confidence,
            'badge_tone' => $segmentMeta['badge_tone'],
            'ml_cluster' => null,
            'ml_source' => 'rules',
            'analysis' => $this->buildProfileAnalysis($segmentMeta['profile_label'], $riskScore, $blockedAccounts, $anomalyCount, $vaultProgress, $savingsRate),
            'recommendations' => $this->buildProfileRecommendations($segmentMeta['segment_key'], $blockedAccounts, $anomalyCount, $vaultProgress, $savingsRate),
            'alerts' => $this->buildProfileAlerts($name, $segmentMeta['segment_key'], $blockedAccounts, $anomalyCount, $vaultProgress, $savingsRate, $transactionCount),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $profiles
     * @param array<string, mixed> $predictions
     * @return array<int, array<string, mixed>>
     */
    private function applyMlPredictions(array $profiles, array $predictions): array
    {
        $classificationPredictions = (array) ($predictions['classification'] ?? []);
        $kmeansPredictions = (array) ($predictions['kmeans'] ?? []);
        $anomalyCounts = (array) ($predictions['transaction_anomalies_by_client'] ?? []);

        foreach ($profiles as &$profile) {
            $clientId = (string) ((int) ($profile['client_id'] ?? 0));
            $classification = (array) ($classificationPredictions[$clientId] ?? []);
            $kmeans = (array) ($kmeansPredictions[$clientId] ?? []);
            $resolvedAnomalyCount = array_key_exists($clientId, $anomalyCounts)
                ? (int) $anomalyCounts[$clientId]
                : (int) ($profile['anomaly_count'] ?? 0);

            $predictedLabel = $this->normalizePredictionLabel((string) ($classification['label'] ?? $profile['profile_label']));
            $segmentMeta = $this->resolveSegmentMetadata($predictedLabel);
            $confidence = (int) round((float) ($classification['confidence'] ?? $profile['confidence'] ?? 60));
            $riskScore = $classification !== []
                ? $this->riskScoreFromPrediction(
                    $predictedLabel,
                    $confidence,
                    $resolvedAnomalyCount,
                    (int) ($profile['blocked_accounts'] ?? 0)
                )
                : (int) ($profile['risk_score'] ?? 0);

            $clusterLabel = trim((string) ($kmeans['cluster_label'] ?? ''));
            $analysis = $this->buildProfileAnalysis(
                $segmentMeta['profile_label'],
                $riskScore,
                (int) ($profile['blocked_accounts'] ?? 0),
                $resolvedAnomalyCount,
                (float) ($profile['vault_progress'] ?? 0),
                (float) ($profile['savings_rate'] ?? 0)
            );
            if ($clusterLabel !== '') {
                $analysis .= ' Segment ML: '.$clusterLabel.'.';
            }

            $profile['anomaly_count'] = $resolvedAnomalyCount;
            $profile['anomaly_reason'] = $this->buildAnomalyReason(
                $segmentMeta['segment_key'],
                (int) ($profile['blocked_accounts'] ?? 0),
                $resolvedAnomalyCount,
                (float) ($profile['vault_progress'] ?? 0),
                (int) ($profile['transaction_count'] ?? 0)
            );
            $profile['profile_label'] = $segmentMeta['profile_label'];
            $profile['segment_key'] = $segmentMeta['segment_key'];
            $profile['badge_tone'] = $segmentMeta['badge_tone'];
            $profile['risk_score'] = $riskScore;
            $profile['confidence'] = max(55, min(99, $confidence));
            $profile['ml_cluster'] = $clusterLabel !== '' ? $clusterLabel : null;
            $profile['ml_source'] = $classification !== [] ? 'models' : 'rules';
            $profile['analysis'] = $analysis;
            $profile['recommendations'] = $this->buildProfileRecommendations(
                $segmentMeta['segment_key'],
                (int) ($profile['blocked_accounts'] ?? 0),
                $resolvedAnomalyCount,
                (float) ($profile['vault_progress'] ?? 0),
                (float) ($profile['savings_rate'] ?? 0)
            );
            $profile['alerts'] = $this->buildProfileAlerts(
                (string) ($profile['client_name'] ?? 'Client'),
                $segmentMeta['segment_key'],
                (int) ($profile['blocked_accounts'] ?? 0),
                $resolvedAnomalyCount,
                (float) ($profile['vault_progress'] ?? 0),
                (float) ($profile['savings_rate'] ?? 0),
                (int) ($profile['transaction_count'] ?? 0)
            );
        }
        unset($profile);

        return $profiles;
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function buildKmeansDatasetRow(array $profile): array
    {
        return [
            'client_id' => $profile['client_id'],
            'client_name' => $profile['client_name'],
            'nb_comptes' => $profile['account_count'],
            'typeCompte_principal' => $profile['principal_account_type'],
            'solde_total' => $profile['total_balance'],
            'solde_moyen' => $profile['avg_balance'],
            'dateOuverture_plus_ancienne' => $profile['oldest_account_date'],
            'nb_comptes_actifs' => $profile['active_accounts'],
            'nb_comptes_bloques' => $profile['blocked_accounts'],
            'nb_comptes_fermes' => $profile['closed_accounts'],
            'plafondRetrait_max' => $profile['max_withdrawal_limit'],
            'plafondVirement_max' => $profile['max_transfer_limit'],
            'nb_coffres' => $profile['vault_count'],
            'coffre_objectifMontant_total' => $profile['vault_goal'],
            'coffre_montantActuel_total' => $profile['vault_current'],
            'coffre_estVerrouille_count' => $profile['locked_vaults'],
            'nb_coffres_actifs' => $profile['active_vaults'],
            'vault_progress' => $profile['vault_progress'],
            'savings_rate' => $profile['savings_rate'],
            'nb_transactions' => $profile['transaction_count'],
            'freq_DEBIT' => $profile['debit_count'],
            'freq_CREDIT' => $profile['credit_count'],
            'freq_VIREMENT' => $profile['virement_count'],
            'freq_PAIEMENT' => $profile['payment_count'],
            'montant_moyen_txn' => $profile['average_transaction_amount'],
            'account_age_days' => $profile['avg_account_age_days'],
            'debit_credit_ratio' => $profile['debit_credit_ratio'],
            'segment' => $this->normalizePredictionLabel((string) ($profile['profile_label'] ?? 'Fragile')),
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function buildClassificationDatasetRow(array $profile): array
    {
        $categories = (array) ($profile['category_counts'] ?? []);

        return [
            'client_id' => $profile['client_id'],
            'client_name' => $profile['client_name'],
            'client_email' => $profile['client_email'],
            'nb_comptes' => $profile['account_count'],
            'typeCompte_principal' => $profile['principal_account_type'],
            'solde_total' => $profile['total_balance'],
            'solde_moyen' => $profile['avg_balance'],
            'dateOuverture_plus_ancienne' => $profile['oldest_account_date'],
            'nb_comptes_actifs' => $profile['active_accounts'],
            'nb_comptes_bloques' => $profile['blocked_accounts'],
            'nb_comptes_fermes' => $profile['closed_accounts'],
            'plafondRetrait_max' => $profile['max_withdrawal_limit'],
            'plafondVirement_max' => $profile['max_transfer_limit'],
            'nb_coffres' => $profile['vault_count'],
            'coffre_nom_principal' => $profile['primary_vault_name'],
            'coffre_objectifMontant_total' => $profile['vault_goal'],
            'coffre_montantActuel_total' => $profile['vault_current'],
            'coffre_dateCreation_min' => $profile['vault_date_creation_min'],
            'coffre_dateObjectifs_max' => $profile['vault_date_objectifs_max'],
            'nb_coffres_actifs' => $profile['active_vaults'],
            'nb_coffres_bloques' => $profile['blocked_vaults'],
            'coffre_estVerrouille_count' => $profile['locked_vaults'],
            'vault_progress' => $profile['vault_progress'],
            'savings_rate' => $profile['savings_rate'],
            'nb_transactions' => $profile['transaction_count'],
            'freq_DEBIT' => $profile['debit_count'],
            'freq_CREDIT' => $profile['credit_count'],
            'freq_VIREMENT' => $profile['virement_count'],
            'freq_PAIEMENT' => $profile['payment_count'],
            'cat_alimentation' => (int) ($categories['alimentation'] ?? 0),
            'cat_transport' => (int) ($categories['transport'] ?? 0),
            'cat_education' => (int) ($categories['education'] ?? 0),
            'cat_shopping' => (int) ($categories['shopping'] ?? 0),
            'cat_autres' => (int) ($categories['autres'] ?? 0),
            'montant_moyen_txn' => $profile['average_transaction_amount'],
            'account_age_days' => $profile['avg_account_age_days'],
            'debit_credit_ratio' => $profile['debit_credit_ratio'],
            'anomaly_count' => $profile['anomaly_count'],
            'rf_target' => $this->normalizeLabelForDataset((string) ($profile['profile_label'] ?? 'Fragile')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $profilesById
     * @param array<int, array<string, mixed>> $buckets
     * @return array<int, array<string, mixed>>
     */
    private function buildAnomalyDatasetRows(array $profilesById, array $buckets): array
    {
        $rows = [];

        foreach ($buckets as $userId => $bucket) {
            $profile = $profilesById[$userId] ?? null;
            if ($profile === null) {
                continue;
            }

            foreach ((array) ($bucket['transactions'] ?? []) as $transaction) {
                $amount = abs((float) ($transaction['montant_value'] ?? $transaction['montant'] ?? 0));
                
                $typeRaw = $this->normalizeText((string) ($transaction['typeTransaction'] ?? ''));
                $sensRaw = $this->normalizeText((string) ($transaction['sens'] ?? ''));
                $resolvedSens = $sensRaw;
                if ($resolvedSens !== 'debit' && $resolvedSens !== 'credit') {
                    if (str_contains($typeRaw, 'paiement') || str_contains($typeRaw, 'retrait') || str_contains($typeRaw, 'paimenet')) {
                        $resolvedSens = 'debit';
                    } elseif (str_contains($typeRaw, 'depot') || str_contains($typeRaw, 'versement')) {
                        $resolvedSens = 'credit';
                    } elseif (str_contains($typeRaw, 'virement')) {
                        $dest = trim((string)($transaction['idCompteDestinataire'] ?? ''));
                        $resolvedSens = ($dest !== '' && $dest !== '0') ? 'debit' : 'credit';
                    } else {
                        $resolvedSens = (str_contains($typeRaw, 'debit')) ? 'debit' : 'credit';
                    }
                }

                $rows[] = [
                    'idTransaction' => (int) ($transaction['idTransaction'] ?? 0),
                    'idCompte' => (int) ($transaction['idCompte'] ?? 0),
                    'idUser' => $userId,
                    'client_name' => $profile['client_name'],
                    'client_email' => $profile['client_email'],
                    'categorie' => trim((string) ($transaction['categorie'] ?? 'Autres')),
                    'dateTransaction' => trim((string) ($transaction['dateTransaction'] ?? '')),
                    'montant' => round($amount, 2),
                    'typeTransaction' => trim((string) ($transaction['typeTransaction'] ?? '')),
                    'sens' => $resolvedSens,
                    'soldeAvant' => $this->nullableFloat($transaction['soldeAvant'] ?? null),
                    'soldeApres' => $this->nullableFloat($transaction['soldeApres'] ?? null),
                    'montantPaye' => $this->nullableFloat($transaction['montantPaye_value'] ?? $transaction['montantPaye'] ?? null),
                    'idCompteDestinataire' => $this->nullableInt($transaction['idCompteDestinataire'] ?? null),
                    'nom_dest' => trim((string) ($transaction['nom_dest'] ?? '')),
                    'mail_dest' => trim((string) ($transaction['mail_dest'] ?? '')),
                    'typeCompte' => trim((string) ($transaction['compte_type'] ?? $profile['principal_account_type'] ?? '')),
                    'statutCompte' => trim((string) ($transaction['compte_status'] ?? '')),
                    'plafondRetrait' => round((float) ($transaction['compte_plafond_retrait'] ?? $profile['max_withdrawal_limit'] ?? 0), 2),
                    'plafondVirement' => round((float) ($transaction['compte_plafond_virement'] ?? $profile['max_transfer_limit'] ?? 0), 2),
                    'nb_transactions_client' => $profile['transaction_count'],
                    'freq_DEBIT_client' => $profile['debit_count'],
                    'freq_CREDIT_client' => $profile['credit_count'],
                    'debit_credit_ratio_client' => $profile['debit_credit_ratio'],
                    'montant_moyen_client' => $profile['average_transaction_amount'],
                    'savings_rate_client' => $profile['savings_rate'],
                    'vault_progress_client' => $profile['vault_progress'],
                    'is_anomaly' => (bool) ($transaction['is_anomalie'] ?? false) ? 1 : 0,
                    'anomaly_reason' => $this->buildTransactionAnomalyReason($transaction, $profile),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $transaction
     * @param array<string, mixed> $profile
     */
    private function buildTransactionAnomalyReason(array $transaction, array $profile): string
    {
        if ((bool) ($transaction['is_anomalie'] ?? false)) {
            return 'transaction_pattern';
        }
        if ((int) ($profile['blocked_accounts'] ?? 0) > 0) {
            return 'blocked_accounts';
        }
        if ((float) ($profile['vault_progress'] ?? 0) < 25) {
            return 'low_saving_progress';
        }

        return 'standard_profile';
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, string>
     */
    private function resolveSegmentMetadata(string $label): array
    {
        $normalized = $this->normalizePredictionLabel($label);

        return match ($normalized) {
            'Premium' => ['segment_key' => 'premium', 'profile_label' => 'Premium', 'badge_tone' => 'premium'],
            'Epargnant' => ['segment_key' => 'stable', 'profile_label' => 'Epargnant', 'badge_tone' => 'success'],
            'Stable' => ['segment_key' => 'stable', 'profile_label' => 'Stable', 'badge_tone' => 'info'],
            'A risque' => ['segment_key' => 'risque', 'profile_label' => 'A risque', 'badge_tone' => 'danger'],
            default => ['segment_key' => 'fragile', 'profile_label' => 'Fragile', 'badge_tone' => 'warning'],
        };
    }

    private function riskScoreFromPrediction(string $label, int $confidence, int $anomalyCount, int $blockedAccounts): int
    {
        $base = match ($this->normalizePredictionLabel($label)) {
            'Premium' => 88,
            'Epargnant' => 80,
            'Stable' => 68,
            'Fragile' => 50,
            'A risque' => 32,
            default => 50,
        };

        $score = $base + (int) round(($confidence - 60) / 5);
        $score -= $anomalyCount * 6;
        $score -= $blockedAccounts * 4;

        return max(8, min(97, $score));
    }

    private function normalizePredictionLabel(string $label): string
    {
        $normalized = trim($label);

        return match ($normalized) {
            'Risque' => 'A risque',
            default => $normalized !== '' ? $normalized : 'Fragile',
        };
    }

    private function resolveCategoryBucket(string $category): string
    {
        $normalized = $this->normalizeText($category);

        return match (true) {
            str_contains($normalized, 'aliment') => 'alimentation',
            str_contains($normalized, 'transport') => 'transport',
            str_contains($normalized, 'educ') => 'education',
            str_contains($normalized, 'shop') => 'shopping',
            default => 'autres',
        };
    }

    /**
     * @param array<string, int> $values
     */
    private function resolvePrimaryTextValue(array $values, string $default): string
    {
        if ($values === []) {
            return $default;
        }

        arsort($values);
        $top = array_key_first($values);

        return $top !== null ? (string) $top : $default;
    }

    private function minDateString(?string $current, string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $current;
        }
        if ($current === null || $current === '') {
            return $candidate;
        }

        return strcmp(substr($candidate, 0, 10), substr($current, 0, 10)) < 0 ? $candidate : $current;
    }

    private function maxDateString(?string $current, string $candidate): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $current;
        }
        if ($current === null || $current === '') {
            return $candidate;
        }

        return strcmp(substr($candidate, 0, 10), substr($current, 0, 10)) > 0 ? $candidate : $current;
    }

    /**
     * @param array<string, mixed> $alert
     */
    private function alertPriority(array $alert): int
    {
        return match ((string) ($alert['level'] ?? 'info')) {
            'danger' => 3,
            'warning' => 2,
            default => 1,
        };
    }

    /**
     * @param array<string, int> $segmentCounts
     * @return array<int, array<string, mixed>>
     */
    private function buildSegments(array $segmentCounts): array
    {
        $maxCount = max(1, ...array_values($segmentCounts));

        return [
            [
                'label' => 'Premium',
                'count' => $segmentCounts['premium'],
                'percent' => round(($segmentCounts['premium'] / $maxCount) * 100, 1),
                'color' => '#11b7aa',
            ],
            [
                'label' => 'Stables',
                'count' => $segmentCounts['stable'],
                'percent' => round(($segmentCounts['stable'] / $maxCount) * 100, 1),
                'color' => '#2b7de9',
            ],
            [
                'label' => 'Fragiles',
                'count' => $segmentCounts['fragile'],
                'percent' => round(($segmentCounts['fragile'] / $maxCount) * 100, 1),
                'color' => '#e8a202',
            ],
            [
                'label' => 'Risque',
                'count' => $segmentCounts['risque'],
                'percent' => round(($segmentCounts['risque'] / $maxCount) * 100, 1),
                'color' => '#e53935',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $profiles
     * @return array<string, mixed>
     */
    private function resolveFocusClient(array $profiles): array
    {
        foreach ($profiles as $profile) {
            if (($profile['segment_key'] ?? '') === 'risque') {
                return $profile;
            }
        }

        return $profiles[0];
    }

    /**
     * @param array<string, int> $segmentCounts
     * @param array<int, array<string, mixed>> $alerts
     * @return array<int, array<string, string>>
     */
    private function buildGlobalRecommendations(array $segmentCounts, array $alerts): array
    {
        $recommendations = [];

        if (($segmentCounts['risque'] ?? 0) > 0) {
            $recommendations[] = [
                'icon' => 'fa-triangle-exclamation',
                'text' => 'Contacter les clients a risque en priorite et verifier les comptes bloques ou instables.',
            ];
        }
        if (($segmentCounts['premium'] ?? 0) > 0) {
            $recommendations[] = [
                'icon' => 'fa-crown',
                'text' => 'Proposer des offres premium et des plans d epargne avances aux profils a fort potentiel.',
            ];
        }
        if (($segmentCounts['fragile'] ?? 0) > 0) {
            $recommendations[] = [
                'icon' => 'fa-piggy-bank',
                'text' => 'Encourager les profils fragiles a automatiser leur epargne et a lisser leurs retraits.',
            ];
        }
        if ($alerts !== []) {
            $recommendations[] = [
                'icon' => 'fa-shield-halved',
                'text' => 'Surveiller les alertes detectees par le module Isolation Forest pour renforcer la securite.',
            ];
        }
        if ($recommendations === []) {
            $recommendations[] = [
                'icon' => 'fa-circle-check',
                'text' => 'Le portefeuille client est stable. Maintenir le suivi automatise et actualiser les modeles periodiquement.',
            ];
        }

        return array_slice($recommendations, 0, 4);
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function indexUsers(array $users): array
    {
        $indexed = [];
        foreach ($users as $user) {
            $id = (int) ($user['idUser'] ?? 0);
            if ($id > 0) {
                $indexed[$id] = $user;
            }
        }

        return $indexed;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function resolveClientId(mixed $userId, array $users): ?int
    {
        $resolved = (int) $userId;
        if ($resolved <= 0 || !isset($users[$resolved])) {
            return null;
        }

        $role = strtoupper(trim((string) ($users[$resolved]['role'] ?? '')));

        return $role === 'ROLE_USER' ? $resolved : null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return strtr($normalized, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ÿ' => 'y',
            'Ã©' => 'e',
            'Ã¨' => 'e',
            'Ãª' => 'e',
            'Ã«' => 'e',
            'Ã ' => 'a',
            'Ã¢' => 'a',
            'Ã¤' => 'a',
            'Ã®' => 'i',
            'Ã¯' => 'i',
            'Ã´' => 'o',
            'Ã¶' => 'o',
            'Ã¹' => 'u',
            'Ã»' => 'u',
            'Ã¼' => 'u',
            'Ã§' => 'c',
        ]);
    }

    private function daysSince(string $rawDate): ?int
    {
        $rawDate = trim($rawDate);
        if ($rawDate === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable(substr($rawDate, 0, 10));
            $today = new \DateTimeImmutable('today');
        } catch (\Throwable) {
            return null;
        }

        return max(0, (int) $today->diff($date)->format('%a'));
    }

    private function buildProfileAnalysis(
        string $profileLabel,
        int $riskScore,
        int $blockedAccounts,
        int $anomalyCount,
        float $vaultProgress,
        float $savingsRate
    ): string {
        if ($profileLabel === 'Premium') {
            return sprintf('Score %d/100. Le client affiche une forte capacite financiere et un potentiel eleve pour des offres premium.', $riskScore);
        }
        if ($profileLabel === 'Epargnant') {
            return sprintf('Score %d/100. Le comportement montre une epargne reguliere avec %.1f%% de progression sur les coffres.', $riskScore, $vaultProgress);
        }
        if ($profileLabel === 'Stable') {
            return sprintf('Score %d/100. Le client reste globalement stable avec un taux d epargne de %.1f%%.', $riskScore, $savingsRate);
        }
        if ($profileLabel === 'A risque') {
            return sprintf('Score %d/100. %d compte(s) bloque(s) et %d anomalie(s) demandent une verification prioritaire.', $riskScore, $blockedAccounts, $anomalyCount);
        }

        return sprintf('Score %d/100. Le profil reste fragile et la progression des coffres plafonne a %.1f%%.', $riskScore, $vaultProgress);
    }

    /**
     * @return array<int, string>
     */
    private function buildProfileRecommendations(
        string $segmentKey,
        int $blockedAccounts,
        int $anomalyCount,
        float $vaultProgress,
        float $savingsRate
    ): array {
        $recommendations = [];

        if ($segmentKey === 'risque') {
            $recommendations[] = 'Verifier les operations recentes et securiser les comptes sensibles.';
            if ($blockedAccounts > 0) {
                $recommendations[] = 'Contacter le client pour clarifier la situation des comptes bloques.';
            }
            if ($anomalyCount > 0) {
                $recommendations[] = 'Analyser les transactions atypiques avant toute nouvelle operation critique.';
            }
        } elseif ($segmentKey === 'premium') {
            $recommendations[] = 'Proposer des offres premium, placements et alertes dediees.';
            $recommendations[] = 'Mettre en avant des produits d epargne long terme et gestion patrimoniale.';
        } elseif ($savingsRate >= 20 || $vaultProgress >= 45) {
            $recommendations[] = 'Renforcer les virements automatiques vers les coffres les plus performants.';
            $recommendations[] = 'Suggérer des objectifs d epargne progressifs pour accelerer la croissance.';
        } else {
            $recommendations[] = 'Mettre en place un plan d epargne progressif a faible effort.';
            $recommendations[] = 'Limiter les retraits frequents pour stabiliser la reserve disponible.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Poursuivre le suivi mensuel du profil et actualiser la segmentation.';
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildProfileAlerts(
        string $clientName,
        string $segmentKey,
        int $blockedAccounts,
        int $anomalyCount,
        float $vaultProgress,
        float $savingsRate,
        int $transactionCount
    ): array {
        $alerts = [];

        if ($anomalyCount > 0) {
            $alerts[] = [
                'level' => 'danger',
                'client' => $clientName,
                'title' => 'Activite inhabituelle detectee',
                'text' => sprintf('%s presente %d transaction(s) anormale(s) a verifier rapidement.', $clientName, $anomalyCount),
            ];
        }
        if ($blockedAccounts > 0 && $segmentKey !== 'premium') {
            $alerts[] = [
                'level' => 'warning',
                'client' => $clientName,
                'title' => 'Compte bloque a surveiller',
                'text' => sprintf('%s a %d compte(s) bloque(s), ce qui fragilise sa stabilite globale.', $clientName, $blockedAccounts),
            ];
        }
        if ($vaultProgress < 25 && $savingsRate < 15) {
            $alerts[] = [
                'level' => 'warning',
                'client' => $clientName,
                'title' => 'Diminution de l epargne',
                'text' => sprintf('La progression des coffres de %s reste basse (%.1f%%).', $clientName, $vaultProgress),
            ];
        }
        if ($transactionCount === 0) {
            $alerts[] = [
                'level' => 'info',
                'client' => $clientName,
                'title' => 'Activite transactionnelle faible',
                'text' => sprintf('%s manque encore d historique transactionnel pour une prediction plus fine.', $clientName),
            ];
        }

        return array_slice($alerts, 0, 2);
    }

    private function buildAnomalyReason(
        string $segmentKey,
        int $blockedAccounts,
        int $anomalyCount,
        float $vaultProgress,
        int $transactionCount
    ): string {
        if ($anomalyCount > 0) {
            return 'transaction_pattern';
        }
        if ($blockedAccounts > 0) {
            return 'blocked_accounts';
        }
        if ($vaultProgress < 25) {
            return 'low_saving_progress';
        }
        if ($transactionCount === 0) {
            return 'limited_history';
        }
        if ($segmentKey === 'premium') {
            return 'high_value_profile';
        }

        return 'standard_profile';
    }

    private function normalizeLabelForDataset(string $label): string
    {
        return match ($label) {
            'A risque' => 'Risque',
            default => $label,
        };
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
            }
            if (mb_strlen($letters, 'UTF-8') >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'CL';
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, int> $datasetCounts
     * @param array<string, string> $modelPaths
     * @return array<string, mixed>
     */
    private function buildEmptyPayload(array $datasetCounts, array $modelPaths, string $trainingNotice): array
    {
        return [
            'overview' => [
                'client_count' => 0,
                'stable_count' => 0,
                'risk_count' => 0,
                'alert_count' => 0,
                'average_score' => 0,
            ],
            'table' => [],
            'profiles' => [],
            'focus_client' => null,
            'segments' => [],
            'distribution' => [
                ['label' => 'Stables', 'count' => 0, 'color' => '#2b7de9'],
                ['label' => 'Epargnants', 'count' => 0, 'color' => '#11b7aa'],
                ['label' => 'A risque', 'count' => 0, 'color' => '#e53935'],
            ],
            'alerts' => [],
            'recommendations' => [
                [
                    'icon' => 'fa-circle-info',
                    'text' => 'Aucune donnee client exploitable pour le moment.',
                ],
            ],
            'dataset_counts' => $datasetCounts,
            'model_paths' => $modelPaths,
            'training_notice' => $trainingNotice,
        ];
    }
}
