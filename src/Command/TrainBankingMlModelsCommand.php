<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BankingMlModelService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:banking-ml:train', description: 'Trains local ML models from the current CSV datasets in vendor/Model.')]
final class TrainBankingMlModelsCommand extends Command
{
    public function __construct(private readonly BankingMlModelService $modelService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->modelService->trainAll();

        if (!($result['success'] ?? false)) {
            $io->error([
                'Entrainement ML echoue.',
                (string) ($result['error'] ?? 'Erreur inconnue.'),
                (string) ($result['training_notice'] ?? ''),
            ]);

            return Command::FAILURE;
        }

        $messages = [
            'Entrainement ML termine avec succes.',
            (string) ($result['training_notice'] ?? ''),
        ];

        foreach ((array) ($result['output'] ?? []) as $name => $line) {
            if (trim((string) $line) !== '') {
                $messages[] = sprintf('%s: %s', ucfirst((string) $name), trim((string) $line));
            }
        }

        $io->success($messages);

        return Command::SUCCESS;
    }
}
