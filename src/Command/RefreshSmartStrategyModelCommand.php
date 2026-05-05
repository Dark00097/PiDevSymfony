<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SmartStrategyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:strategy:refresh-model',
    description: 'Construit dataset.csv depuis la base puis reentraine le modele Random Forest',
)]
final class RefreshSmartStrategyModelCommand extends Command
{
    public function __construct(
        private readonly SmartStrategyService $strategyService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'rows',
            null,
            InputOption::VALUE_OPTIONAL,
            'Nombre cible de lignes dans le dataset apres augmentation',
            900
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Smart Financial Strategy Optimizer - Refresh dataset & modele');

        $result = $this->strategyService->refreshDatasetAndModel((int) $input->getOption('rows'));
        $dataset = $result['dataset'];
        $training = $result['training'];

        $io->table(
            ['Metric', 'Value'],
            [
                ['Dataset path', (string) $dataset['path']],
                ['Rows dataset', (string) $dataset['rows']],
                ['Base rows', (string) $dataset['base_rows']],
                ['Rows labels historiques', (string) $dataset['history_rows']],
                ['DOUCE', (string) ($dataset['label_distribution']['DOUCE'] ?? 0)],
                ['MODEREE', (string) ($dataset['label_distribution']['MODEREE'] ?? 0)],
                ['AGRESSIVE', (string) ($dataset['label_distribution']['AGRESSIVE'] ?? 0)],
                ['Accuracy ML', (string) ($training['accuracy'] ?? 'n/a') . '%'],
            ]
        );

        $io->success('Le dataset et le modele ont ete mis a jour.');
        return Command::SUCCESS;
    }
}
