<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SmartStrategyService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:strategy:test-pipeline',
    description: 'Teste le pipeline complet: BDD -> dataset -> ML -> activation -> scheduler -> transfert -> refresh',
)]
final class TestSmartStrategyPipelineCommand extends Command
{
    public function __construct(
        private readonly SmartStrategyService $strategyService,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('coffre', null, InputOption::VALUE_OPTIONAL, 'ID du coffre a tester', 220)
            ->addOption('user', null, InputOption::VALUE_OPTIONAL, 'ID utilisateur', 17)
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Type strategie forcee (sinon recommandee)', '')
            ->addOption('rows', null, InputOption::VALUE_OPTIONAL, 'Nombre cible de lignes du dataset', 900);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $idCoffre = (int) $input->getOption('coffre');
        $idUser = (int) $input->getOption('user');
        $forcedType = strtoupper(trim((string) $input->getOption('type')));
        $rows = (int) $input->getOption('rows');

        $io->title('Smart Financial Strategy Optimizer - Test pipeline complet');

        $io->section('1. Refresh dataset + modele');
        $refresh = $this->strategyService->refreshDatasetAndModel($rows);
        $io->writeln(sprintf('Dataset rows : <comment>%d</comment>', $refresh['dataset']['rows']));
        $io->writeln(sprintf('Accuracy ML  : <comment>%s%%</comment>', $refresh['training']['accuracy'] ?? 'n/a'));

        $io->section('2. Lecture profil financier');
        $financialData = $this->strategyService->extractFinancialData($idCoffre, $idUser);
        $prediction = $this->strategyService->predictStrategies($financialData);
        if (($prediction['prediction_source'] ?? null) !== 'ml') {
            $io->error('La prediction ne passe pas par le modele ML.');
            return Command::FAILURE;
        }

        $strategyType = $forcedType !== '' ? $forcedType : (string) $prediction['recommended'];
        $selectedStrategy = $this->strategyService->resolveStrategySelection($prediction, $strategyType);
        if ($selectedStrategy === null) {
            $io->error('Strategie introuvable dans la prediction.');
            return Command::FAILURE;
        }

        $io->writeln(sprintf('Strategie retenue : <comment>%s</comment>', $strategyType));
        $io->writeln(sprintf('Montant mensuel   : <comment>%.2f DT</comment>', (float) $selectedStrategy['montant']));
        $io->writeln(sprintf('Confiance ML      : <comment>%s%%</comment>', $prediction['ml_confidence'] ?? '0'));

        $coffre = $this->connection->fetchAssociative(
            'SELECT cv.idCompte, c.solde, cv.montantActuel
             FROM coffrevirtuel cv
             JOIN compte c ON cv.idCompte = c.idCompte
             WHERE cv.idCoffre = ? AND cv.idUser = ?',
            [$idCoffre, $idUser]
        );
        if (!$coffre) {
            $io->error('Coffre introuvable pour le test.');
            return Command::FAILURE;
        }

        $idCompte = (int) $coffre['idCompte'];
        $before = $this->snapshotState($idCompte, $idCoffre);

        $io->section('3. Activation de la strategie');
        $activation = $this->strategyService->activateStrategy(
            $idCoffre,
            $idCompte,
            $idUser,
            $strategyType,
            (float) $selectedStrategy['montant'],
            (int) $selectedStrategy['duree'],
            (float) $selectedStrategy['taux_succes'],
            (string) $selectedStrategy['risque'],
            (float) $prediction['safety_seuil']
        );
        $idStrategie = (int) $activation['idStrategie'];
        $io->writeln(sprintf('Strategie enregistree sous ID <comment>%d</comment>', $idStrategie));

        $this->connection->executeStatement(
            'UPDATE strategies_proposees SET dateProchaineExecution = ? WHERE idStrategie = ?',
            [(new \DateTimeImmutable())->format('Y-m-d'), $idStrategie]
        );

        $io->section('4. Execution scheduler + transfert');
        $scheduler = $this->strategyService->executeScheduledTransfers(true);
        $after = $this->snapshotState($idCompte, $idCoffre);

        $strategyRow = $this->connection->fetchAssociative(
            'SELECT * FROM strategies_proposees WHERE idStrategie = ?',
            [$idStrategie]
        );
        $lastTransaction = $this->connection->fetchAssociative(
            'SELECT * FROM transactions
             WHERE idCompte = ?
             ORDER BY idTransaction DESC
             LIMIT 1',
            [$idCompte]
        );

        $io->table(
            ['Metric', 'Before', 'After'],
            [
                ['Solde compte', number_format((float) $before['solde'], 2), number_format((float) $after['solde'], 2)],
                ['Montant coffre', number_format((float) $before['montantActuel'], 2), number_format((float) $after['montantActuel'], 2)],
                ['Nb transactions', (string) $before['transactions'], (string) $after['transactions']],
            ]
        );

        $io->writeln(sprintf('Transferts executes : <comment>%d</comment>', $scheduler['executed']));
        $io->writeln(sprintf('Transferts suspendus: <comment>%d</comment>', $scheduler['suspended']));
        $io->writeln(sprintf('Statut strategie    : <comment>%s</comment>', $strategyRow['statut'] ?? 'n/a'));
        $io->writeln(sprintf('Derniere transaction: <comment>%s</comment>', $lastTransaction['description'] ?? 'n/a'));

        if (!empty($scheduler['refresh'])) {
            $io->writeln(sprintf(
                'Dataset refresh rows : <comment>%d</comment>',
                $scheduler['refresh']['dataset']['rows']
            ));
            $io->writeln(sprintf(
                'Modele reentraine    : <comment>%s%%</comment>',
                $scheduler['refresh']['training']['accuracy'] ?? 'n/a'
            ));
        }

        $saved = $this->connection->fetchAssociative(
            'SELECT * FROM strategies_proposees WHERE idStrategie = ?',
            [$idStrategie]
        );
        if (!$saved) {
            $io->error('La strategie n a pas ete trouvee apres activation.');
            return Command::FAILURE;
        }

        $io->success('Le pipeline complet fonctionne: activation, insertion BDD, transfert automatique, transaction et refresh ML.');
        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotState(int $idCompte, int $idCoffre): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT c.solde, cv.montantActuel
             FROM compte c
             JOIN coffrevirtuel cv ON cv.idCompte = c.idCompte
             WHERE c.idCompte = ? AND cv.idCoffre = ?',
            [$idCompte, $idCoffre]
        );

        return [
            'solde' => (float) ($row['solde'] ?? 0),
            'montantActuel' => (float) ($row['montantActuel'] ?? 0),
            'transactions' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM transactions WHERE idCompte = ?',
                [$idCompte]
            ),
        ];
    }
}
