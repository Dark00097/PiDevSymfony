<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BankingMlAssistantService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(name: 'app:banking-ml:export', description: 'Exports ML datasets for banking profiles into vendor/Model.')]
final class ExportBankingMlDatasetsCommand extends Command
{
    public function __construct(
        private readonly BankingMlAssistantService $assistantService,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->kernel->getProjectDir();
        $baseDir = $projectDir.'/vendor/Model';
        $datasets = $this->assistantService->buildTrainingDatasets();

        $this->ensureDirectory($baseDir.'/kmeans');
        $this->ensureDirectory($baseDir.'/isolation');
        $this->ensureDirectory($baseDir.'/randomforest');

        $this->writeCsv($baseDir.'/kmeans/kmeans.csv', $datasets['kmeans']);
        $this->writeCsv($baseDir.'/isolation/anomalies.csv', $datasets['anomalies']);
        $this->writeCsv($baseDir.'/randomforest/classification.csv', $datasets['classification']);

        $this->touchFile($baseDir.'/kmeans/kmeans_model.pkl');
        $this->touchFile($baseDir.'/isolation/isolation_model.pkl');
        $this->touchFile($baseDir.'/randomforest/rf_model.pkl');

        $io->success([
            'Datasets ML exportes avec succes.',
            sprintf('K-Means: %d ligne(s)', count($datasets['kmeans'])),
            sprintf('Isolation: %d ligne(s)', count($datasets['anomalies'])),
            sprintf('Random Forest: %d ligne(s)', count($datasets['classification'])),
            'Fichiers ecrits dans vendor/Model.',
        ]);

        return Command::SUCCESS;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Impossible d ecrire le fichier %s.', $path));
        }

        $headers = $rows !== [] ? array_keys($rows[0]) : [];
        if ($headers !== []) {
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                $ordered = [];
                foreach ($headers as $header) {
                    $ordered[] = $row[$header] ?? '';
                }
                fputcsv($handle, $ordered);
            }
        }

        fclose($handle);
    }

    private function touchFile(string $path): void
    {
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }
    }
}
