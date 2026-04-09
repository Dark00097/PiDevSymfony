<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DatabaseReverseEngineer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate:entities',
    description: 'Reverse-engineer Doctrine entities/repositories from the current database schema.',
)]
final class GenerateEntitiesCommand extends Command
{
    public function __construct(
        private readonly DatabaseReverseEngineer $reverseEngineer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Reads schema dynamically and writes entities/repositories in src/.
            $report = $this->reverseEngineer->generate(withMethods: true);
        } catch (\Throwable $exception) {
            $io->error('Reverse engineering failed: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Generated %d entities and %d repositories.',
            count($report['entities']),
            count($report['repositories'])
        ));

        if ($report['entities'] !== []) {
            $io->section('Entities');
            $io->listing($report['entities']);
        }

        if ($report['repositories'] !== []) {
            $io->section('Repositories');
            $io->listing($report['repositories']);
        }

        if ($report['skipped_join_tables'] !== []) {
            $io->section('Join tables detected (mapped as ManyToMany)');
            $io->listing($report['skipped_join_tables']);
        }

        if ($report['warnings'] !== []) {
            $io->warning('Some schema parts were skipped or simplified:');
            $io->listing($report['warnings']);
        }

        $io->text('Next: run doctrine:migrations:diff then doctrine:migrations:migrate.');

        return Command::SUCCESS;
    }
}
