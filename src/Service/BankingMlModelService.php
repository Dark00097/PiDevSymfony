<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

final class BankingMlModelService
{
    private const DATASET_PATHS = [
        'kmeans'         => 'vendor/Model/kmeans/kmeans.csv',
        'anomalies'      => 'vendor/Model/isolation/anomalies.csv',
        'classification' => 'vendor/Model/randomforest/classification.csv',
    ];

    private const MODEL_PATHS = [
        'kmeans'       => 'vendor/Model/kmeans/kmeans_model.pkl',
        'isolation'    => 'vendor/Model/isolation/isolation_model.pkl',
        'randomforest' => 'vendor/Model/randomforest/rf_model.pkl',
    ];

    private const TRAINING_SCRIPTS = [
        'kmeans'       => 'vendor/Model/kmeans/train_kmeans.py',
        'isolation'    => 'vendor/Model/isolation/train_isolation.py',
        'randomforest' => 'vendor/Model/randomforest/train_rf.py',
    ];

    private const PREDICT_SCRIPT = 'vendor/Model/predict_banking_models.py';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getModelPaths(): array
    {
        return self::MODEL_PATHS;
    }

    /**
     * @param array<string, int>|null $fallbackCounts
     * @return array<string, int>
     */
    public function getDatasetCounts(?array $fallbackCounts = null): array
    {
        $counts = [];
        foreach (self::DATASET_PATHS as $name => $relativePath) {
            $counts[$name] = $this->countCsvRows($this->absolutePath($relativePath));
            if ($counts[$name] === 0 && $fallbackCounts !== null) {
                $counts[$name] = (int) ($fallbackCounts[$name] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $python          = $this->resolvePythonBinary();
        $pythonAvailable = $this->commandIsAvailable([$python, '--version']);
        $models          = [];

        foreach (self::MODEL_PATHS as $name => $relativePath) {
            $absolutePath  = $this->absolutePath($relativePath);
            $models[$name] = [
                'path'          => $relativePath,
                'absolute_path' => $absolutePath,
                'trained'       => is_file($absolutePath) && filesize($absolutePath) > 0,
            ];
        }

        $modelsReady = array_reduce(
            $models,
            static fn (bool $carry, array $state): bool => $carry && (bool) ($state['trained'] ?? false),
            true
        );

        $status = [
            'python_available' => $pythonAvailable,
            'python_binary'    => $python,
            'models'           => $models,
            'models_ready'     => $modelsReady,
            'available'        => $pythonAvailable && $modelsReady,
            'error'            => null,
        ];

        $status['training_notice'] = $this->buildNotice($status);

        return $status;
    }

    /**
     * FIX PRINCIPAL : predict() est maintenant appelé UNIQUEMENT quand le
     * panel IA est actif (géré dans AccountsController).
     * Cette méthode log tout dans var/log/dev.log via Symfony Logger.
     *
     * @param array<int, array<string, mixed>> $classificationRows
     * @param array<int, array<string, mixed>> $kmeansRows
     * @param array<int, array<string, mixed>> $anomalyRows
     * @return array<string, mixed>
     */
    public function predict(array $classificationRows, array $kmeansRows, array $anomalyRows): array
    {
        // FIX: log immédiatement pour que Get-Content dev.log voie l'activité
        $this->logInfo('[Banking ML][predict] Appel predict() déclenché depuis le panel IA.', [
            'classification_rows' => count($classificationRows),
            'kmeans_rows'         => count($kmeansRows),
            'anomaly_rows'        => count($anomalyRows),
        ]);

        $status = $this->refreshModelsIfNeeded();
        if (!($status['available'] ?? false)) {
            $this->logWarning('[Banking ML][predict] Modèles non disponibles — prédiction annulée.', [
                'error' => $status['error'] ?? 'inconnu',
            ]);

            return array_merge($status, [
                'classification'                  => [],
                'kmeans'                          => [],
                'transaction_anomalies'           => [],
                'transaction_anomalies_by_client' => [],
            ]);
        }

        $cacheDir = $this->kernel->getProjectDir() . '/var/cache/banking_ml';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $inputPath  = tempnam($cacheDir, 'ml_input_');
        $outputPath = tempnam($cacheDir, 'ml_output_');
        if ($inputPath === false || $outputPath === false) {
            $status['available']       = false;
            $status['error']           = 'Impossible de préparer les fichiers temporaires pour la prédiction ML.';
            $status['training_notice'] = $this->buildNotice($status);

            $this->logError('[Banking ML][predict] Impossible de créer les fichiers temporaires.');

            return array_merge($status, [
                'classification'                  => [],
                'kmeans'                          => [],
                'transaction_anomalies'           => [],
                'transaction_anomalies_by_client' => [],
            ]);
        }

        file_put_contents($inputPath, (string) json_encode([
            'classification_rows' => array_values($classificationRows),
            'kmeans_rows'         => array_values($kmeansRows),
            'anomaly_rows'        => array_values($anomalyRows),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->logInfo('[Banking ML][predict] Lancement du script Python predict.', [
            'script' => self::PREDICT_SCRIPT,
            'python' => $status['python_binary'],
            'input'  => $inputPath,
            'output' => $outputPath,
        ]);

        $process = new Process([
            (string) $status['python_binary'],
            $this->absolutePath(self::PREDICT_SCRIPT),
            '--input',
            $inputPath,
            '--output',
            $outputPath,
        ], $this->kernel->getProjectDir(), null, null, 180);

        $parsed = [];

        try {
            $process->mustRun(function (string $type, string $buffer): void {
                $this->emitPythonLog('predict', $buffer, $type);
            });

            $outputContent = file_get_contents($outputPath);
            if ($outputContent === false || trim($outputContent) === '') {
                throw new \RuntimeException('Le script Python n\'a produit aucune sortie dans le fichier de résultat.');
            }

            $parsed = json_decode($outputContent, true, 512, JSON_THROW_ON_ERROR);

            $this->logInfo('[Banking ML][predict] Prédiction terminée avec succès.', [
                'clients_classification' => count((array) ($parsed['classification'] ?? [])),
                'clients_kmeans'         => count((array) ($parsed['kmeans'] ?? [])),
                'anomalies_detected'     => count((array) ($parsed['transaction_anomalies'] ?? [])),
            ]);
        } catch (\Throwable $exception) {
            $stderr                    = trim($process->getErrorOutput());
            $status['available']       = false;
            $status['error']           = $stderr !== '' ? $stderr : $exception->getMessage();
            $status['training_notice'] = $this->buildNotice($status);

            $this->logError('[Banking ML][predict] Erreur prédiction Python.', [
                'error'  => $status['error'],
                'stderr' => $stderr,
                'stdout' => trim($process->getOutput()),
            ]);

            $parsed = [
                'classification'                  => [],
                'kmeans'                          => [],
                'transaction_anomalies'           => [],
                'transaction_anomalies_by_client' => [],
            ];
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }

        if (($status['available'] ?? false) === true) {
            $status['training_notice'] = $this->buildNotice($status);
        }

        return array_merge($status, $parsed);
    }

    /**
     * @return array<string, mixed>
     */
    public function trainAll(): array
    {
        $status = $this->getStatus();
        if (!($status['python_available'] ?? false)) {
            return [
                'success'         => false,
                'output'          => [],
                'error'           => 'Python est introuvable. Configurez ML_PYTHON_BIN ou ajoutez Python au PATH.',
                'training_notice' => $this->buildNotice($status),
            ];
        }

        $output = [];
        foreach (self::TRAINING_SCRIPTS as $name => $relativeScript) {
            $this->logInfo(sprintf('[Banking ML][train:%s] Lancement entrainement.', $name));

            $process = new Process([
                (string) $status['python_binary'],
                $this->absolutePath($relativeScript),
            ], $this->kernel->getProjectDir(), null, null, 300);

            try {
                $process->mustRun(function (string $type, string $buffer) use ($name): void {
                    $this->emitPythonLog('train:' . $name, $buffer, $type);
                });
                $output[$name] = trim($process->getOutput());
                $this->logInfo(sprintf('[Banking ML][train:%s] Entrainement terminé.', $name), [
                    'output' => $output[$name],
                ]);
            } catch (\Throwable) {
                $stderr = trim($process->getErrorOutput());
                $this->logError(sprintf('[Banking ML][train:%s] Echec entrainement.', $name), [
                    'stderr' => $stderr,
                ]);

                return [
                    'success'         => false,
                    'output'          => $output,
                    'error'           => $stderr !== '' ? $stderr : sprintf('L\'entrainement %s a échoué.', $name),
                    'training_notice' => 'Les modèles n\'ont pas pu être entrainés. Installez Python, pandas, joblib et scikit-learn puis relancez la commande.',
                ];
            }
        }

        $freshStatus = $this->getStatus();

        return [
            'success'         => true,
            'output'          => $output,
            'error'           => null,
            'training_notice' => $this->buildNotice($freshStatus),
            'model_paths'     => $this->getModelPaths(),
        ];
    }

    /**
     * FIX: Try python3 first (Linux/macOS), then python (Windows),
     * then fall back to env variable ML_PYTHON_BIN.
     */
    private function resolvePythonBinary(): string
    {
        $fromEnv = trim((string) ($_SERVER['ML_PYTHON_BIN'] ?? getenv('ML_PYTHON_BIN') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        foreach (['python3', 'python'] as $candidate) {
            if ($this->commandIsAvailable([$candidate, '--version'])) {
                return $candidate;
            }
        }

        return 'python3';
    }

    /**
     * @param array<int, string> $command
     */
    private function commandIsAvailable(array $command): bool
    {
        try {
            $process = new Process($command, $this->kernel->getProjectDir(), null, null, 15);
            $process->mustRun();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->kernel->getProjectDir() . '/' . str_replace('\\', '/', $relativePath);
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshModelsIfNeeded(): array
    {
        $status = $this->getStatus();
        if (!($status['python_available'] ?? false)) {
            return $status;
        }

        if (($status['models_ready'] ?? false) && !$this->modelsNeedRetraining()) {
            return $status;
        }

        $this->logInfo('[Banking ML] Modèles absents ou périmés — re-entrainement automatique.');

        $training  = $this->trainAll();
        $refreshed = $this->getStatus();
        if (!($training['success'] ?? false)) {
            $refreshed['available']       = false;
            $refreshed['error']           = (string) ($training['error'] ?? 'Echec de l\'entrainement automatique des modèles.');
            $refreshed['training_notice'] = $this->buildNotice($refreshed);

            return $refreshed;
        }

        return $refreshed;
    }

    private function modelsNeedRetraining(): bool
    {
        foreach (self::MODEL_PATHS as $name => $modelRelativePath) {
            $modelPath = $this->absolutePath($modelRelativePath);
            if (!is_file($modelPath) || filesize($modelPath) === 0) {
                return true;
            }

            $datasetKey = match ($name) {
                'isolation'    => 'anomalies',
                'randomforest' => 'classification',
                default        => 'kmeans',
            };
            $datasetPath = $this->absolutePath(self::DATASET_PATHS[$datasetKey]);

            if (!is_file($datasetPath)) {
                return true;
            }

            $datasetMtime = filemtime($datasetPath);
            $modelMtime   = filemtime($modelPath);
            if ($datasetMtime === false || $modelMtime === false || $datasetMtime > $modelMtime) {
                return true;
            }
        }

        return false;
    }

    private function countCsvRows(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        $rows      = 0;
        $hasHeader = false;
        while (($line = fgetcsv($handle)) !== false) {
            if ($hasHeader === false) {
                $hasHeader = true;
                continue;
            }

            if ($line !== [null] && $line !== false) {
                ++$rows;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function buildNotice(array $status): string
    {
        if (($status['available'] ?? false) === true) {
            return 'Modèles ML actifs. Les prédictions admin utilisent les fichiers .csv entrainés localement avec K-Means, Isolation Forest et Random Forest.';
        }

        if (!($status['python_available'] ?? false)) {
            return 'Mode heuristique actif. Python est introuvable. Configurez ML_PYTHON_BIN puis lancez `php bin/console app:banking-ml:train`.';
        }

        if (!($status['models_ready'] ?? false)) {
            return 'Mode heuristique actif. Les fichiers .pkl ne sont pas encore entrainés. Lancez `php bin/console app:banking-ml:train` pour entrainer les modèles depuis les CSV actuels.';
        }

        $error = trim((string) ($status['error'] ?? ''));
        if ($error !== '') {
            return 'Mode heuristique actif. L\'inférence Python a échoué : ' . $error;
        }

        return 'Mode heuristique actif. Les modèles ML ne sont pas encore exploitables.';
    }

    /**
     * FIX: emitPythonLog utilise le logger Symfony → visible dans var/log/dev.log
     * et dans : Get-Content var\log\dev.log -Wait -Tail 50 | Select-String "Banking ML"
     */
    private function emitPythonLog(string $context, string $buffer, string $type): void
    {
        $trimmed = trim($buffer);
        if ($trimmed === '') {
            return;
        }

        $tag = sprintf('[Banking ML][%s]', $context);

        if ($type === Process::ERR) {
            $this->logWarning($tag . ' [stderr] ' . $trimmed);
        } else {
            $this->logInfo($tag . ' [stdout] ' . $trimmed);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
        $this->appendBankingMlLog('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
        $this->appendBankingMlLog('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
        $this->appendBankingMlLog('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function appendBankingMlLog(string $level, string $message, array $context = []): void
    {
        $logDirectory = $this->kernel->getProjectDir() . '/var/log';
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0777, true);
        }

        $contextSuffix = '';
        if ($context !== []) {
            try {
                $contextSuffix = ' ' . (string) json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $contextSuffix = ' [context_unserializable]';
            }
        }

        $line = sprintf(
            "[%s] %s %s%s%s",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $level,
            $message,
            $contextSuffix,
            PHP_EOL
        );

        @file_put_contents($logDirectory . '/banking_ml.log', $line, FILE_APPEND | LOCK_EX);
    }
}
