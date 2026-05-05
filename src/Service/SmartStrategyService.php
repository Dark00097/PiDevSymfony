<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Process;

final class SmartStrategyService
{
    private const PYTHON_SCRIPT = __DIR__ . '/../../vendor/ModelFront/predict.py';
    private const TRAIN_SCRIPT = __DIR__ . '/../../vendor/ModelFront/train_modelFront.py';
    private const DATASET_PATH = __DIR__ . '/../../vendor/ModelFront/dataset.csv';
    private const SAFETY_SEUIL_MIN = 50.0;
    private const SAFETY_SEUIL_PCT = 0.05;
    private const STRATEGY_TYPES = ['DOUCE', 'MODEREE', 'AGRESSIVE'];

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBankingSecurity $security,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFinancialData(int $idCoffre, int $idUser): array
    {
        $coffre = $this->connection->fetchAssociative(
            'SELECT cv.*, c.solde, c.idCompte, c.plafondRetrait, c.plafondVirement
             FROM coffrevirtuel cv
             JOIN compte c ON cv.idCompte = c.idCompte
             WHERE cv.idCoffre = ? AND cv.idUser = ?
             LIMIT 1',
            [$idCoffre, $idUser]
        );

        if (!$coffre) {
            throw new \RuntimeException('Coffre introuvable ou acces refuse.');
        }

        $idCompte = (int) ($coffre['idCompte'] ?? 0);
        $transactions = $this->connection->fetchAllAssociative(
            "SELECT * FROM transactions
             WHERE idCompte = ?
               AND dateTransaction >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             ORDER BY idTransaction DESC
             LIMIT 200",
            [$idCompte]
        );

        $revenuMensuel = 0.0;
        $depensesMensuelles = 0.0;
        $nbTransactions = count($transactions);

        foreach ($transactions as $tx) {
            $montant = $this->security->decryptAmount((string) ($tx['montant'] ?? '')) ?? 0.0;
            $type = strtolower(trim((string) ($tx['typeTransaction'] ?? '')));

            if (str_contains($type, 'depot') || str_contains($type, 'versement') || str_contains($type, 'credit')) {
                $revenuMensuel += $montant;
                continue;
            }

            if (str_contains($type, 'paiement') || str_contains($type, 'retrait') || str_contains($type, 'debit')) {
                $depensesMensuelles += $montant;
                continue;
            }

            if (str_contains($type, 'virement')) {
                $dest = (string) ($tx['idCompteDestinataire'] ?? '');
                if ($dest === '' || $dest === '0' || $dest === (string) $idCompte) {
                    $revenuMensuel += $montant;
                } else {
                    $depensesMensuelles += $montant;
                }
            }
        }

        $revenuMensuel = round($revenuMensuel / 6, 2);
        $depensesMensuelles = round($depensesMensuelles / 6, 2);

        if ($revenuMensuel <= 0) {
            $solde = (float) ($coffre['solde'] ?? 0);
            $revenuMensuel = round($solde * 0.30, 2);
            $depensesMensuelles = round($solde * 0.20, 2);
        }

        return [
            'solde' => (float) ($coffre['solde'] ?? 0),
            'revenu_mensuel' => $revenuMensuel,
            'depenses_mensuelles' => $depensesMensuelles,
            'nb_transactions' => $nbTransactions,
            'objectif_coffre' => (float) ($coffre['objectifMontant'] ?? 1000),
            'montant_actuel' => (float) ($coffre['montantActuel'] ?? 0),
            'idCompte' => $idCompte,
            'nomCoffre' => (string) ($coffre['nom'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $financialData
     * @return array<string, mixed>
     */
    public function predictStrategies(array $financialData): array
    {
        $scriptPath = realpath(self::PYTHON_SCRIPT);
        if ($scriptPath === false || !is_file($scriptPath)) {
            return $this->fallbackStrategies($financialData);
        }

        $jsonInput = json_encode($financialData, JSON_THROW_ON_ERROR);

        foreach (['python', 'python3'] as $pythonBin) {
            $process = new Process([$pythonBin, $scriptPath]);
            $process->setInput($jsonInput);
            $process->setTimeout(30);

            try {
                $process->run();
                if (!$process->isSuccessful()) {
                    continue;
                }

                foreach (explode("\n", trim($process->getOutput())) as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, '{')) {
                        continue;
                    }

                    $result = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($result['strategies'])) {
                        $result['prediction_source'] ??= 'ml';
                        return $result;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->fallbackStrategies($financialData);
    }

    /**
     * @param array<string, mixed> $predictionResult
     * @return array<string, mixed>|null
     */
    public function resolveStrategySelection(array $predictionResult, string $typeStrategie): ?array
    {
        foreach (($predictionResult['strategies'] ?? []) as $strategy) {
            if (($strategy['type'] ?? null) === strtoupper($typeStrategie)) {
                return $strategy;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateDatasetFromDatabase(int $targetRows = 900): array
    {
        $sources = $this->connection->fetchAllAssociative(
            'SELECT cv.idCoffre, cv.idCompte, cv.idUser
             FROM coffrevirtuel cv
             JOIN compte c ON cv.idCompte = c.idCompte
             WHERE cv.idCompte IS NOT NULL
               AND cv.idUser IS NOT NULL
             ORDER BY cv.idCoffre ASC'
        );

        $baseRows = [];
        $historyRows = 0;
        foreach ($sources as $source) {
            $idCoffre = (int) ($source['idCoffre'] ?? 0);
            $idUser = (int) ($source['idUser'] ?? 0);

            if ($idCoffre <= 0 || $idUser <= 0) {
                continue;
            }

            try {
                $financialData = $this->extractFinancialData($idCoffre, $idUser);
            } catch (\Throwable) {
                continue;
            }

            $latestStrategy = $this->connection->fetchAssociative(
                'SELECT typeStrategie
                 FROM strategies_proposees
                 WHERE idCoffre = ?
                 ORDER BY dateActivation DESC
                 LIMIT 1',
                [$idCoffre]
            );

            $labelSource = 'heuristic';
            if ($latestStrategy && in_array(strtoupper((string) $latestStrategy['typeStrategie']), self::STRATEGY_TYPES, true)) {
                $label = strtoupper((string) $latestStrategy['typeStrategie']);
                $labelSource = 'strategy_history';
                $historyRows++;
            } else {
                $label = $this->inferStrategyLabel($financialData);
            }

            $baseRows[] = $this->buildDatasetRow(
                $financialData,
                $label,
                $labelSource,
                [
                    'idCoffre' => $idCoffre,
                    'idCompte' => (int) ($source['idCompte'] ?? 0),
                    'idUser' => $idUser,
                ]
            );
        }

        if ($baseRows === []) {
            throw new \RuntimeException('Aucune ligne exploitable n a ete trouvee dans la base pour construire le dataset.');
        }

        $baseRows = array_merge($baseRows, $this->ensureLabelCoverage($baseRows));
        $rows = $this->augmentDatasetRows($baseRows, max($targetRows, count($baseRows)));
        $this->writeDatasetCsv($rows);

        return [
            'path' => realpath(self::DATASET_PATH) ?: self::DATASET_PATH,
            'rows' => count($rows),
            'base_rows' => count($baseRows),
            'history_rows' => $historyRows,
            'label_distribution' => $this->labelDistribution($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrainModel(): array
    {
        $trainScript = realpath(self::TRAIN_SCRIPT);
        if ($trainScript === false || !is_file($trainScript)) {
            throw new \RuntimeException('Script d entrainement introuvable.');
        }

        $errors = [];
        foreach (['python', 'python3'] as $pythonBin) {
            $process = new Process([$pythonBin, $trainScript]);
            $process->setTimeout(120);

            try {
                $process->run();
                if (!$process->isSuccessful()) {
                    $errors[] = trim($process->getErrorOutput() ?: $process->getOutput());
                    continue;
                }

                foreach (explode("\n", trim($process->getOutput())) as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, '{')) {
                        continue;
                    }

                    $result = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $result['python'] = $pythonBin;
                    return $result;
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new \RuntimeException('Impossible de reentrainer le modele : ' . implode(' | ', array_filter($errors)));
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshDatasetAndModel(int $targetRows = 900): array
    {
        return [
            'dataset' => $this->generateDatasetFromDatabase($targetRows),
            'training' => $this->retrainModel(),
        ];
    }

    public function safetyCheck(int $idCompte, float $montantTransfert, float $seuil): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT solde FROM compte WHERE idCompte = ? LIMIT 1',
            [$idCompte]
        );

        if (!$row) {
            return false;
        }

        $solde = (float) ($row['solde'] ?? 0);
        return ($solde - $montantTransfert) >= $seuil;
    }

    /**
     * @return array<string, mixed>
     */
    public function activateStrategy(
        int $idCoffre,
        int $idCompte,
        int $idUser,
        string $typeStrategie,
        float $montantMensuel,
        int $dureeEstimee,
        float $tauxSucces,
        string $niveauRisque,
        float $safetySeuil
    ): array {
        if ($idCoffre <= 0 || $idCompte <= 0 || $idUser <= 0) {
            throw new \InvalidArgumentException('Identifiants invalides pour activation de strategie.');
        }

        $typeStrategie = strtoupper(trim($typeStrategie));
        if (!in_array($typeStrategie, self::STRATEGY_TYPES, true)) {
            throw new \InvalidArgumentException('Type de strategie invalide.');
        }

        if ($montantMensuel <= 0) {
            throw new \InvalidArgumentException('Le montant mensuel doit etre strictement positif.');
        }

        if ($dureeEstimee <= 0) {
            throw new \InvalidArgumentException('La duree estimee doit etre strictement positive.');
        }

        $safetySeuil = max($safetySeuil, self::SAFETY_SEUIL_MIN);

        $coffreExists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM coffrevirtuel WHERE idCoffre = ? AND idCompte = ? AND idUser = ?',
            [$idCoffre, $idCompte, $idUser]
        );
        if ($coffreExists <= 0) {
            throw new \RuntimeException('Coffre introuvable ou incoherent avec le compte/utilisateur.');
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement(
                "UPDATE strategies_proposees
                 SET statut = 'terminee'
                 WHERE idCoffre = ?
                   AND statut = 'active'",
                [$idCoffre]
            );

            $now = new \DateTimeImmutable();
            $prochainTransfert = $now->modify('+1 month');

            $this->connection->insert('strategies_proposees', [
                'idCoffre' => $idCoffre,
                'idCompte' => $idCompte,
                'idUser' => $idUser,
                'typeStrategie' => $typeStrategie,
                'montantMensuel' => round($montantMensuel, 2),
                'dureeEstimee' => $dureeEstimee,
                'tauxSucces' => round($tauxSucces, 2),
                'niveauRisque' => $niveauRisque,
                'statut' => 'active',
                'safetyCheckSeuil' => round($safetySeuil, 2),
                'dateActivation' => $now->format('Y-m-d H:i:s'),
                'dateProchaineExecution' => $prochainTransfert->format('Y-m-d'),
                'nombreTransferts' => 0,
                'montantTotalTransfere' => 0.00,
            ]);

            $idStrategie = (int) $this->connection->lastInsertId();
            if ($idStrategie <= 0) {
                throw new \RuntimeException('Echec insertion strategie en base.');
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $this->notificationService->createNotification(
            $idUser,
            null,
            $idUser,
            'STRATEGY_ACTIVATED',
            'Strategie d epargne activee',
            sprintf(
                'La strategie %s (%.2f DT/mois) a ete activee pour votre coffre. Le prochain transfert aura lieu le %s.',
                strtoupper($typeStrategie),
                $montantMensuel,
                $prochainTransfert->format('d/m/Y')
            ),
            false
        );

        return [
            'idStrategie' => $idStrategie,
            'statut' => 'active',
            'dateProchaineExecution' => $prochainTransfert->format('d/m/Y'),
            'message' => sprintf(
                'La strategie %s (%.2f DT/mois) est maintenant active. Le prochain transfert aura lieu le %s apres verification du Safety Check.',
                ucfirst(strtolower($typeStrategie)),
                $montantMensuel,
                $prochainTransfert->format('d/m/Y')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executeScheduledTransfers(bool $refreshModelAfterExecution = false): array
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $strategies = $this->connection->fetchAllAssociative(
            "SELECT * FROM strategies_proposees
             WHERE statut IN ('active', 'suspendue')
               AND dateProchaineExecution <= ?",
            [$today]
        );

        $results = [
            'executed' => 0,
            'suspended' => 0,
            'errors' => 0,
            'refresh' => null,
            'refresh_error' => null,
        ];

        foreach ($strategies as $strategy) {
            try {
                $this->executeTransfer($strategy, $results);
            } catch (\Throwable $e) {
                $results['errors']++;
            }
        }

        if ($refreshModelAfterExecution && ($results['executed'] > 0 || $results['suspended'] > 0)) {
            try {
                $results['refresh'] = $this->refreshDatasetAndModel();
            } catch (\Throwable $e) {
                $results['refresh_error'] = $e->getMessage();
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActiveStrategy(int $idCoffre): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM strategies_proposees
             WHERE idCoffre = ? AND statut IN ('active', 'suspendue')
             ORDER BY dateActivation DESC
             LIMIT 1",
            [$idCoffre]
        );

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserStrategies(int $idUser): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT sp.*, cv.nom AS nomCoffre, c.numeroCompte
             FROM strategies_proposees sp
             JOIN coffrevirtuel cv ON sp.idCoffre = cv.idCoffre
             JOIN compte c ON sp.idCompte = c.idCompte
             WHERE sp.idUser = ?
             ORDER BY sp.dateActivation DESC",
            [$idUser]
        );
    }

    /**
     * @param array<string, mixed> $financialData
     * @return array<string, mixed>
     */
    private function fallbackStrategies(array $financialData): array
    {
        $solde = (float) ($financialData['solde'] ?? 0);
        $revenu = (float) ($financialData['revenu_mensuel'] ?? 0);
        $depenses = (float) ($financialData['depenses_mensuelles'] ?? 0);
        $objectif = (float) ($financialData['objectif_coffre'] ?? 1000);
        $actuel = (float) ($financialData['montant_actuel'] ?? 0);

        $capacite = max($revenu - $depenses, 0);
        $ratio = $revenu > 0 ? $capacite / $revenu : 0;
        $restant = max($objectif - $actuel, 0);
        $safetySeuil = max($solde * self::SAFETY_SEUIL_PCT, self::SAFETY_SEUIL_MIN);

        $douce = max(round($capacite * 0.15, 0), 10);
        $moderee = max(round($capacite * 0.35, 0), 20);
        $agressive = max(round($capacite * 0.65, 0), 30);

        $dureeDouce = $douce > 0 ? min((int) ceil($restant / $douce), 60) : 24;
        $dureeModeree = $moderee > 0 ? min((int) ceil($restant / $moderee), 36) : 12;
        $dureeAgressive = $agressive > 0 ? min((int) ceil($restant / $agressive), 24) : 6;
        $recommended = $ratio >= 0.30 ? 'AGRESSIVE' : ($ratio >= 0.15 ? 'MODEREE' : 'DOUCE');

        return [
            'solde_compte' => round($solde, 2),
            'capacite_mensuelle' => round($capacite, 2),
            'ratio_epargne' => round($ratio * 100, 1),
            'safety_seuil' => round($safetySeuil, 2),
            'recommended' => $recommended,
            'prediction_source' => 'fallback_php',
            'ml_confidence' => 0.0,
            'ml_probabilities' => [
                'DOUCE' => $recommended === 'DOUCE' ? 100.0 : 0.0,
                'MODEREE' => $recommended === 'MODEREE' ? 100.0 : 0.0,
                'AGRESSIVE' => $recommended === 'AGRESSIVE' ? 100.0 : 0.0,
            ],
            'ml_error' => 'Python indisponible ou prediction ML echouee.',
            'strategies' => [
                [
                    'type' => 'DOUCE',
                    'label' => 'Douce',
                    'montant' => $douce,
                    'duree' => $dureeDouce,
                    'taux_succes' => 98.0,
                    'risque' => 'Tres faible',
                    'risque_color' => '#22c55e',
                    'recommended' => $recommended === 'DOUCE',
                ],
                [
                    'type' => 'MODEREE',
                    'label' => 'Moderee',
                    'montant' => $moderee,
                    'duree' => $dureeModeree,
                    'taux_succes' => 87.0,
                    'risque' => 'Faible',
                    'risque_color' => '#3b82f6',
                    'recommended' => $recommended === 'MODEREE',
                ],
                [
                    'type' => 'AGRESSIVE',
                    'label' => 'Agressive',
                    'montant' => $agressive,
                    'duree' => $dureeAgressive,
                    'taux_succes' => 64.0,
                    'risque' => 'Modere',
                    'risque_color' => '#f97316',
                    'recommended' => $recommended === 'AGRESSIVE',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $financialData
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildDatasetRow(array $financialData, string $label, string $labelSource, array $context = []): array
    {
        $solde = round((float) ($financialData['solde'] ?? 0), 2);
        $revenu = round((float) ($financialData['revenu_mensuel'] ?? 0), 2);
        $depenses = round((float) ($financialData['depenses_mensuelles'] ?? 0), 2);
        $capacite = round(max($revenu - $depenses, 0), 2);
        $ratio = round($revenu > 0 ? $capacite / $revenu : 0.0, 6);
        $objectif = round((float) ($financialData['objectif_coffre'] ?? 1000), 2);
        $montantActuel = round((float) ($financialData['montant_actuel'] ?? 0), 2);
        $progression = round($objectif > 0 ? min($montantActuel / $objectif, 1.0) : 0.0, 6);
        $montantRestant = max($objectif - $montantActuel, 0.0);
        $moisRestants = (int) $this->clamp(
            (int) ceil($montantRestant / max($capacite * 0.35, 20)),
            1,
            36
        );

        return [
            'solde' => $solde,
            'revenu_mensuel' => $revenu,
            'depenses_mensuelles' => $depenses,
            'capacite_epargne' => $capacite,
            'ratio_epargne' => $ratio,
            'nb_transactions' => max((int) ($financialData['nb_transactions'] ?? 0), 0),
            'objectif_coffre' => $objectif,
            'montant_actuel' => $montantActuel,
            'progression' => $progression,
            'mois_restants' => $moisRestants,
            'strategie' => strtoupper($label),
            'label_source' => $labelSource,
            'idCoffre' => (int) ($context['idCoffre'] ?? 0),
            'idCompte' => (int) ($context['idCompte'] ?? 0),
            'idUser' => (int) ($context['idUser'] ?? 0),
            'snapshot_date' => (new \DateTimeImmutable())->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string, mixed> $financialData
     */
    private function inferStrategyLabel(array $financialData): string
    {
        $solde = (float) ($financialData['solde'] ?? 0);
        $revenu = (float) ($financialData['revenu_mensuel'] ?? 0);
        $depenses = (float) ($financialData['depenses_mensuelles'] ?? 0);
        $objectif = (float) ($financialData['objectif_coffre'] ?? 1000);
        $montantActuel = (float) ($financialData['montant_actuel'] ?? 0);

        $capacite = max($revenu - $depenses, 0);
        $ratio = $revenu > 0 ? $capacite / $revenu : 0.0;
        $progression = $objectif > 0 ? min($montantActuel / $objectif, 1.0) : 0.0;
        $goalGap = max($objectif - $montantActuel, 0.0);
        $pressure = $goalGap / max($capacite, 1.0);
        $bufferRatio = ($solde - max($solde * self::SAFETY_SEUIL_PCT, self::SAFETY_SEUIL_MIN)) / max($solde, 1.0);

        $score = (
            $ratio * 0.50
            + min($capacite / 2000.0, 1.0) * 0.25
            + min($solde / 15000.0, 1.0) * 0.10
            + $progression * 0.10
            + $bufferRatio * 0.05
            - min($pressure / 48.0, 1.0) * 0.12
        );

        if ($score >= 0.48 && $capacite >= 180) {
            return 'AGRESSIVE';
        }

        if ($score >= 0.26 && $capacite >= 70) {
            return 'MODEREE';
        }

        return 'DOUCE';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function ensureLabelCoverage(array $rows): array
    {
        $present = array_values(array_unique(array_column($rows, 'strategie')));
        $missing = array_values(array_diff(self::STRATEGY_TYPES, $present));
        if ($missing === []) {
            return [];
        }

        $template = $rows[0];
        $extraRows = [];
        foreach ($missing as $label) {
            $extraRows[] = $this->synthesizeCoverageRow($template, $label);
        }

        return $extraRows;
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private function synthesizeCoverageRow(array $template, string $label): array
    {
        $objectif = max((float) ($template['objectif_coffre'] ?? 4000), 1000.0);

        if ($label === 'DOUCE') {
            $revenu = 1200.0;
            $depenses = 1080.0;
            $solde = 850.0;
            $montantActuel = $objectif * 0.22;
            $nbTransactions = 18;
        } elseif ($label === 'MODEREE') {
            $revenu = 2400.0;
            $depenses = 1820.0;
            $solde = 2600.0;
            $montantActuel = $objectif * 0.35;
            $nbTransactions = 24;
        } else {
            $revenu = 4200.0;
            $depenses = 2500.0;
            $solde = 6800.0;
            $montantActuel = $objectif * 0.46;
            $nbTransactions = 31;
        }

        return $this->buildDatasetRow(
            [
                'solde' => $solde,
                'revenu_mensuel' => $revenu,
                'depenses_mensuelles' => $depenses,
                'nb_transactions' => $nbTransactions,
                'objectif_coffre' => $objectif,
                'montant_actuel' => $montantActuel,
            ],
            $label,
            'coverage_seed'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $baseRows
     * @return array<int, array<string, mixed>>
     */
    private function augmentDatasetRows(array $baseRows, int $targetRows): array
    {
        $rows = $baseRows;
        $countBase = count($baseRows);
        if ($countBase === 0) {
            return [];
        }

        $index = 0;
        while (count($rows) < $targetRows) {
            $seed = $baseRows[$index % $countBase];
            $rows[] = $this->mutateDatasetRow($seed);
            $index++;
        }

        return array_slice($rows, 0, $targetRows);
    }

    /**
     * @param array<string, mixed> $seed
     * @return array<string, mixed>
     */
    private function mutateDatasetRow(array $seed): array
    {
        $label = strtoupper((string) ($seed['strategie'] ?? 'MODEREE'));
        $revenu = max((float) ($seed['revenu_mensuel'] ?? 1000) * $this->randomFloat(0.88, 1.16), 350.0);
        $depenses = max((float) ($seed['depenses_mensuelles'] ?? 700) * $this->randomFloat(0.84, 1.18), 120.0);
        $solde = max((float) ($seed['solde'] ?? 1000) * $this->randomFloat(0.82, 1.20), 200.0);
        $objectif = max((float) ($seed['objectif_coffre'] ?? 3000) * $this->randomFloat(0.85, 1.18), 500.0);
        $progression = $this->clamp((float) ($seed['progression'] ?? 0.3) * $this->randomFloat(0.72, 1.24), 0.01, 0.98);

        if ($label === 'DOUCE') {
            $depenses = max($depenses, $revenu * $this->randomFloat(0.80, 0.93));
        } elseif ($label === 'MODEREE') {
            $depenses = $this->clamp($depenses, $revenu * 0.52, $revenu * 0.78);
        } else {
            $depenses = min($depenses, $revenu * $this->randomFloat(0.28, 0.58));
        }

        $capacite = max($revenu - $depenses, 0.0);
        if ($label === 'DOUCE' && $capacite > 160) {
            $depenses = $revenu - 160;
            $capacite = 160;
        }

        if ($label === 'MODEREE' && $capacite < 70) {
            $depenses = max($revenu - 95, 50.0);
            $capacite = max($revenu - $depenses, 0.0);
        }

        if ($label === 'AGRESSIVE' && $capacite < 180) {
            $depenses = max($revenu - 220, 50.0);
            $capacite = max($revenu - $depenses, 0.0);
        }

        $montantActuel = min($objectif * $progression, $objectif);
        $nbTransactions = max(0, (int) round((float) ($seed['nb_transactions'] ?? 10) + mt_rand(-6, 6)));

        return $this->buildDatasetRow(
            [
                'solde' => round($solde, 2),
                'revenu_mensuel' => round($revenu, 2),
                'depenses_mensuelles' => round($depenses, 2),
                'nb_transactions' => $nbTransactions,
                'objectif_coffre' => round($objectif, 2),
                'montant_actuel' => round($montantActuel, 2),
            ],
            $label,
            'augmented:' . (string) ($seed['label_source'] ?? 'db'),
            [
                'idCoffre' => (int) ($seed['idCoffre'] ?? 0),
                'idCompte' => (int) ($seed['idCompte'] ?? 0),
                'idUser' => (int) ($seed['idUser'] ?? 0),
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeDatasetCsv(array $rows): void
    {
        $handle = fopen(self::DATASET_PATH, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d ecrire le dataset CSV.');
        }

        $header = [
            'solde',
            'revenu_mensuel',
            'depenses_mensuelles',
            'capacite_epargne',
            'ratio_epargne',
            'nb_transactions',
            'objectif_coffre',
            'montant_actuel',
            'progression',
            'mois_restants',
            'strategie',
            'label_source',
            'idCoffre',
            'idCompte',
            'idUser',
            'snapshot_date',
        ];

        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['solde'],
                $row['revenu_mensuel'],
                $row['depenses_mensuelles'],
                $row['capacite_epargne'],
                $row['ratio_epargne'],
                $row['nb_transactions'],
                $row['objectif_coffre'],
                $row['montant_actuel'],
                $row['progression'],
                $row['mois_restants'],
                $row['strategie'],
                $row['label_source'],
                $row['idCoffre'],
                $row['idCompte'],
                $row['idUser'],
                $row['snapshot_date'],
            ]);
        }

        fclose($handle);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function labelDistribution(array $rows): array
    {
        $distribution = ['DOUCE' => 0, 'MODEREE' => 0, 'AGRESSIVE' => 0];
        foreach ($rows as $row) {
            $label = strtoupper((string) ($row['strategie'] ?? ''));
            if (isset($distribution[$label])) {
                $distribution[$label]++;
            }
        }

        return $distribution;
    }

    /**
     * @param array<string, mixed> $strategy
     * @param array<string, mixed> $results
     */
    private function executeTransfer(array $strategy, array &$results): void
    {
        $idStrategie = (int) ($strategy['idStrategie'] ?? 0);
        $idCoffre = (int) ($strategy['idCoffre'] ?? 0);
        $idCompte = (int) ($strategy['idCompte'] ?? 0);
        $idUser = (int) ($strategy['idUser'] ?? 0);
        $montant = (float) ($strategy['montantMensuel'] ?? 0);
        $seuil = (float) ($strategy['safetyCheckSeuil'] ?? self::SAFETY_SEUIL_MIN);
        $duree = (int) ($strategy['dureeEstimee'] ?? 12);
        $nbTransferts = (int) ($strategy['nombreTransferts'] ?? 0);
        $totalTransfere = (float) ($strategy['montantTotalTransfere'] ?? 0);

        if ($idStrategie <= 0 || $idCoffre <= 0 || $idCompte <= 0 || $idUser <= 0 || $montant <= 0) {
            $results['errors']++;
            return;
        }

        if (!$this->safetyCheck($idCompte, $montant, $seuil)) {
            $this->connection->executeStatement(
                "UPDATE strategies_proposees SET statut = 'suspendue' WHERE idStrategie = ?",
                [$idStrategie]
            );

            $this->notificationService->createNotification(
                $idUser,
                null,
                $idUser,
                'STRATEGY_SUSPENDED',
                'Transfert suspendu - Solde insuffisant',
                sprintf(
                    'Le transfert de %.2f DT vers votre coffre a ete suspendu. Solde insuffisant (seuil de securite : %.2f DT).',
                    $montant,
                    $seuil
                ),
                false
            );

            $results['suspended']++;
            return;
        }

        $now = new \DateTimeImmutable();
        $this->connection->beginTransaction();
        try {
            $compteRow = $this->connection->fetchAssociative(
                'SELECT solde FROM compte WHERE idCompte = ? LIMIT 1',
                [$idCompte]
            );
            if (!$compteRow) {
                throw new \RuntimeException('Compte introuvable pour transfert automatique.');
            }

            $coffreRow = $this->connection->fetchAssociative(
                'SELECT montantActuel, objectifMontant FROM coffrevirtuel WHERE idCoffre = ? LIMIT 1',
                [$idCoffre]
            );
            if (!$coffreRow) {
                throw new \RuntimeException('Coffre introuvable pour transfert automatique.');
            }

            $soldeCourant = (float) ($compteRow['solde'] ?? 0);
            $soldeApres = round($soldeCourant - $montant, 2);
            if ($soldeApres < $seuil) {
                $this->connection->executeStatement(
                    "UPDATE strategies_proposees SET statut = 'suspendue' WHERE idStrategie = ?",
                    [$idStrategie]
                );
                $this->connection->commit();
                $results['suspended']++;
                return;
            }

            $montantActuelCoffre = (float) ($coffreRow['montantActuel'] ?? 0);
            $objectifCoffre = (float) ($coffreRow['objectifMontant'] ?? 0);
            $nouveauMontantCoffre = min($montantActuelCoffre + $montant, $objectifCoffre);

            $this->connection->executeStatement(
                'UPDATE compte SET solde = ? WHERE idCompte = ?',
                [$soldeApres, $idCompte]
            );
            $this->connection->executeStatement(
                'UPDATE coffrevirtuel SET montantActuel = ? WHERE idCoffre = ?',
                [$nouveauMontantCoffre, $idCoffre]
            );

            $this->connection->insert('transactions', [
                'categorie' => 'Epargne automatique',
                'dateTransaction' => $now->format('Y-m-d'),
                'montant' => (string) round($montant, 3),
                'typeTransaction' => 'VIREMENT',
                'soldeApres' => (string) $soldeApres,
                'description' => sprintf('Transfert automatique strategie %s vers coffre #%d', $strategy['typeStrategie'], $idCoffre),
                'idCompte' => $idCompte,
                'idUser' => $idUser,
                'idCompteDestinataire' => (string) $idCompte,
            ]);

            $nbTransferts++;
            $totalTransfere += $montant;
            $prochainTransfert = $now->modify('+1 month');
            $statutFinal = ($nbTransferts >= $duree || $nouveauMontantCoffre >= $objectifCoffre) ? 'terminee' : 'active';

            $this->connection->executeStatement(
                "UPDATE strategies_proposees
                 SET nombreTransferts = ?,
                     montantTotalTransfere = ?,
                     dateProchaineExecution = ?,
                     statut = ?
                 WHERE idStrategie = ?",
                [
                    $nbTransferts,
                    $totalTransfere,
                    $prochainTransfert->format('Y-m-d'),
                    $statutFinal,
                    $idStrategie,
                ]
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $this->notificationService->createNotification(
            $idUser,
            null,
            $idUser,
            'STRATEGY_TRANSFER',
            'Transfert automatique effectue',
            sprintf(
                '%.2f DT ont ete transferes vers votre coffre (transfert %d/%d). Prochain transfert : %s.',
                $montant,
                $nbTransferts,
                $duree,
                $prochainTransfert->format('d/m/Y')
            ),
            false
        );

        $results['executed']++;
    }

    private function clamp(float|int $value, float|int $minimum, float|int $maximum): float
    {
        return max((float) $minimum, min((float) $maximum, (float) $value));
    }

    private function randomFloat(float $minimum, float $maximum): float
    {
        return $minimum + (mt_rand() / mt_getrandmax()) * ($maximum - $minimum);
    }
}
