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
    name: 'app:strategy:execute-transfers',
    description: 'Execute les transferts automatiques des strategies d epargne actives',
)]
final class ExecuteStrategyTransfersCommand extends Command
{
    public function __construct(
        private readonly SmartStrategyService $strategyService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'refresh-model',
            null,
            InputOption::VALUE_NONE,
            'Reconstruit le dataset et reentraine le modele apres execution.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Smart Financial Strategy Optimizer - Transferts automatiques');

        $results = $this->strategyService->executeScheduledTransfers((bool) $input->getOption('refresh-model'));

        $io->success(sprintf(
            '%d transfert(s) execute(s), %d suspendu(s), %d erreur(s).',
            $results['executed'],
            $results['suspended'],
            $results['errors']
        ));

        if ($results['refresh'] !== null) {
            $dataset = $results['refresh']['dataset'];
            $training = $results['refresh']['training'];
            $io->section('Dataset & modele rafraichis');
            $io->writeln(sprintf('Rows dataset : <comment>%d</comment>', $dataset['rows']));
            $io->writeln(sprintf('Accuracy ML  : <comment>%s%%</comment>', $training['accuracy'] ?? 'n/a'));
        }

        if (!empty($results['refresh_error'])) {
            $io->warning('Le refresh dataset/modele a echoue : ' . $results['refresh_error']);
        }

        if ($results['suspended'] > 0) {
            $io->warning('Certains transferts ont ete suspendus (solde insuffisant).');
        }

        return Command::SUCCESS;
    }
}
