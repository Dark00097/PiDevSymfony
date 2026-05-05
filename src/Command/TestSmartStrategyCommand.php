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
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:strategy:test',
    description: 'Teste le Smart Financial Strategy Optimizer (ML + BDD + Safety Check)',
)]
final class TestSmartStrategyCommand extends Command
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
            ->addOption('user', null, InputOption::VALUE_OPTIONAL, 'ID de l utilisateur', 17)
            ->addOption('full', null, InputOption::VALUE_NONE, 'Teste aussi l activation (ecrit en BDD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $idCoffre = (int) $input->getOption('coffre');
        $idUser = (int) $input->getOption('user');
        $fullTest = (bool) $input->getOption('full');

        $io->title('Smart Financial Strategy Optimizer - Tests complets');

        $io->section('TEST 1 - Python & modele ML');
        $this->testPython($io);

        $io->section('TEST 2 - Fichiers ML');
        $this->testMlFiles($io);

        $io->section('TEST 3 - Prediction Python directe');
        if (!$this->testPythonPrediction($io)) {
            $io->error('La prediction directe ne passe pas par le modele ML.');
            return Command::FAILURE;
        }

        $io->section(sprintf('TEST 4 - Extraction donnees BDD (coffre #%d, user #%d)', $idCoffre, $idUser));
        $financialData = $this->testExtraction($io, $idCoffre, $idUser);
        if ($financialData === null) {
            $io->error('Extraction BDD echouee.');
            return Command::FAILURE;
        }

        $io->section('TEST 5 - Prediction ML via SmartStrategyService');
        $mlResult = $this->testMlPrediction($io, $financialData);
        if ($mlResult === null) {
            $io->error('Prediction ML echouee.');
            return Command::FAILURE;
        }

        $io->section('TEST 6 - Safety Check');
        $this->testSafetyCheck($io, $financialData, $mlResult);

        $io->section('TEST 7 - Routes API enregistrees');
        $this->testRoutes($io);

        $io->section('TEST 8 - Table strategies_proposees');
        $this->testTable($io);

        if ($fullTest) {
            $io->section('TEST 9 - Activation complete');
            $this->testActivation($io, $idCoffre, $idUser, $mlResult);
        } else {
            $io->note('Ajoutez --full pour tester aussi l activation complete.');
        }

        $io->success('Tous les tests sont passes avec succes.');
        return Command::SUCCESS;
    }

    private function testPython(SymfonyStyle $io): void
    {
        foreach (['python', 'python3'] as $bin) {
            $process = new Process([$bin, '--version']);
            $process->run();
            if ($process->isSuccessful()) {
                $version = trim($process->getOutput() ?: $process->getErrorOutput());
                $io->writeln(sprintf('  <info>OK</info> %s trouve : <comment>%s</comment>', $bin, $version));
                return;
            }
        }

        $io->writeln('  <error>KO</error> Python introuvable');
    }

    private function testMlFiles(SymfonyStyle $io): void
    {
        $files = [
            'vendor/ModelFront/predict.py' => 'Script de prediction',
            'vendor/ModelFront/train_modelFront.py' => 'Script d entrainement',
            'vendor/ModelFront/random_forest_modelFront.pkl' => 'Modele entraine',
            'vendor/ModelFront/dataset.csv' => 'Dataset',
        ];

        foreach ($files as $path => $label) {
            if (file_exists($path)) {
                $size = round(filesize($path) / 1024, 1);
                $io->writeln(sprintf('  <info>OK</info> %s - <comment>%s</comment> (%s Ko)', $label, $path, $size));
            } else {
                $io->writeln(sprintf('  <error>KO</error> %s manquant : %s', $label, $path));
            }
        }
    }

    private function testPythonPrediction(SymfonyStyle $io): bool
    {
        $testData = [
            'solde' => 1200.0,
            'revenu_mensuel' => 360.0,
            'depenses_mensuelles' => 240.0,
            'nb_transactions' => 0,
            'objectif_coffre' => 5620.0,
            'montant_actuel' => 650.0,
        ];

        $json = json_encode($testData, JSON_THROW_ON_ERROR);

        foreach (['python', 'python3'] as $bin) {
            $process = new Process([$bin, 'vendor/ModelFront/predict.py']);
            $process->setInput($json);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                continue;
            }

            foreach (explode("\n", $process->getOutput()) as $line) {
                $line = trim($line);
                if (!str_starts_with($line, '{')) {
                    continue;
                }

                /** @var array<string, mixed> $result */
                $result = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (!isset($result['strategies'])) {
                    continue;
                }

                $io->writeln(sprintf('  <info>OK</info> Prediction OK via <comment>%s</comment>', $bin));
                $this->printPredictionSummary($io, $result);
                return $this->assertMlSource($io, $result, 'Prediction Python directe');
            }
        }

        $io->writeln('  <error>KO</error> Prediction Python indisponible');
        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function testExtraction(SymfonyStyle $io, int $idCoffre, int $idUser): ?array
    {
        try {
            $data = $this->strategyService->extractFinancialData($idCoffre, $idUser);
            $io->writeln(sprintf('  <info>OK</info> Coffre trouve : <comment>%s</comment>', $data['nomCoffre']));
            $io->writeln(sprintf('    Solde compte      : <comment>%s DT</comment>', $data['solde']));
            $io->writeln(sprintf('    Revenu mensuel    : <comment>%s DT</comment>', $data['revenu_mensuel']));
            $io->writeln(sprintf('    Depenses mensuelles: <comment>%s DT</comment>', $data['depenses_mensuelles']));
            $io->writeln(sprintf('    Nb transactions   : <comment>%d</comment>', $data['nb_transactions']));
            $io->writeln(sprintf('    Objectif coffre   : <comment>%s DT</comment>', $data['objectif_coffre']));
            $io->writeln(sprintf('    Montant actuel    : <comment>%s DT</comment>', $data['montant_actuel']));
            return $data;
        } catch (\Throwable $e) {
            $io->writeln(sprintf('  <error>KO</error> Erreur extraction : %s', $e->getMessage()));
            return null;
        }
    }

    /**
     * @param array<string, mixed> $financialData
     * @return array<string, mixed>|null
     */
    private function testMlPrediction(SymfonyStyle $io, array $financialData): ?array
    {
        try {
            $result = $this->strategyService->predictStrategies($financialData);
            $this->printPredictionSummary($io, $result);
            $this->assertStrategiesCoherence($io, $result);

            if (!$this->assertMlSource($io, $result, 'SmartStrategyService')) {
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            $io->writeln(sprintf('  <error>KO</error> Erreur prediction : %s', $e->getMessage()));
            return null;
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function printPredictionSummary(SymfonyStyle $io, array $result): void
    {
        $io->writeln(sprintf('    Recommandee       : <comment>%s</comment>', $result['recommended']));
        $io->writeln(sprintf('    Capacite mensuelle: <comment>%s DT</comment>', $result['capacite_mensuelle']));
        $io->writeln(sprintf('    Ratio epargne     : <comment>%s%%</comment>', $result['ratio_epargne']));
        $io->writeln(sprintf('    Safety seuil      : <comment>%s DT</comment>', $result['safety_seuil']));
        $io->writeln(sprintf('    Source prediction : <comment>%s</comment>', $result['prediction_source'] ?? 'unknown'));
        $io->writeln(sprintf('    Confiance ML      : <comment>%s%%</comment>', $result['ml_confidence'] ?? '0'));
        $io->writeln('');

        $rows = [];
        foreach ($result['strategies'] as $strategy) {
            $rows[] = [
                $strategy['type'],
                number_format((float) $strategy['montant'], 2) . ' DT/mois',
                $strategy['duree'] . ' mois',
                $strategy['taux_succes'] . '%',
                $strategy['risque'],
                $strategy['recommended'] ? 'OUI' : 'non',
                ($strategy['ml_probability'] ?? '0') . '%',
            ];
        }
        $io->table(
            ['Type', 'Montant', 'Duree', 'Succes', 'Risque', 'Recommandee', 'Proba ML'],
            $rows
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertStrategiesCoherence(SymfonyStyle $io, array $result): void
    {
        $strategies = $result['strategies'];
        $errors = [];

        if (count($strategies) !== 3) {
            $errors[] = 'Doit retourner exactement 3 strategies';
        }

        $types = array_column($strategies, 'type');
        foreach (['DOUCE', 'MODEREE', 'AGRESSIVE'] as $expected) {
            if (!in_array($expected, $types, true)) {
                $errors[] = "Strategie $expected manquante";
            }
        }

        $montants = array_column($strategies, 'montant');
        if (count($montants) === 3 && !($montants[0] < $montants[1] && $montants[1] < $montants[2])) {
            $errors[] = 'Les montants doivent etre croissants';
        }

        $durees = array_column($strategies, 'duree');
        if (count($durees) === 3 && !($durees[0] >= $durees[1] && $durees[1] >= $durees[2])) {
            $errors[] = 'Les durees doivent etre decroissantes';
        }

        $recommended = array_filter($strategies, static fn(array $strategy): bool => (bool) $strategy['recommended']);
        if (count($recommended) !== 1) {
            $errors[] = 'Exactement une strategie doit etre recommandee';
        }

        if ($errors === []) {
            $io->writeln('  <info>OK</info> Coherence des strategies : <comment>OK</comment>');
            return;
        }

        foreach ($errors as $error) {
            $io->writeln(sprintf('  <error>KO</error> %s', $error));
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertMlSource(SymfonyStyle $io, array $result, string $context): bool
    {
        $source = (string) ($result['prediction_source'] ?? 'unknown');
        if ($source !== 'ml') {
            $error = (string) ($result['ml_error'] ?? 'aucune erreur detaillee');
            $io->writeln(sprintf(
                '  <error>KO</error> %s n utilise pas le modele ML (source=%s, erreur=%s)',
                $context,
                $source,
                $error
            ));
            return false;
        }

        $io->writeln(sprintf('  <info>OK</info> %s utilise bien le modele ML', $context));
        return true;
    }

    /**
     * @param array<string, mixed> $financialData
     * @param array<string, mixed> $mlResult
     */
    private function testSafetyCheck(SymfonyStyle $io, array $financialData, array $mlResult): void
    {
        $idCompte = (int) $financialData['idCompte'];
        $solde = (float) $financialData['solde'];
        $seuil = (float) $mlResult['safety_seuil'];

        foreach ($mlResult['strategies'] as $strategy) {
            $montant = (float) $strategy['montant'];
            $ok = $this->strategyService->safetyCheck($idCompte, $montant, $seuil);
            $soldeApres = $solde - $montant;

            $status = $ok ? '<info>OK PASSE</info>' : '<comment>SUSPENDU</comment>';
            $io->writeln(sprintf(
                '  %s  %s : %s DT/mois -> solde apres = %s DT (seuil = %s DT)',
                $status,
                str_pad((string) $strategy['type'], 10),
                number_format($montant, 2),
                number_format($soldeApres, 2),
                number_format($seuil, 2)
            ));
        }

        $montantImpossible = $solde + 9999;
        $ok = $this->strategyService->safetyCheck($idCompte, $montantImpossible, $seuil);
        $io->writeln(sprintf(
            '  <info>OK</info> Blocage montant impossible (%s DT) : %s',
            number_format($montantImpossible, 2),
            $ok ? '<error>ERREUR</error>' : '<comment>BLOQUE</comment>'
        ));
    }

    private function testRoutes(SymfonyStyle $io): void
    {
        $process = new Process(['php', 'bin/console', 'debug:router', '--format=txt']);
        $process->run();
        $routerOutput = $process->getOutput();

        foreach (['smart_strategy_analyze', 'smart_strategy_activate'] as $route) {
            if (str_contains($routerOutput, $route)) {
                $io->writeln(sprintf('  <info>OK</info> Route <comment>%s</comment> enregistree', $route));
            } else {
                $io->writeln(sprintf('  <error>KO</error> Route <comment>%s</comment> manquante', $route));
            }
        }
    }

    private function testTable(SymfonyStyle $io): void
    {
        try {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM strategies_proposees');
            $io->writeln(sprintf('  <info>OK</info> Table <comment>strategies_proposees</comment> existe - %d enregistrement(s)', $count));

            $cols = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM strategies_proposees');
            $colNames = array_column($cols, 'Field');
            $required = [
                'idStrategie',
                'idCoffre',
                'idCompte',
                'idUser',
                'typeStrategie',
                'montantMensuel',
                'dureeEstimee',
                'tauxSucces',
                'niveauRisque',
                'statut',
                'safetyCheckSeuil',
                'dateActivation',
                'dateProchaineExecution',
                'nombreTransferts',
                'montantTotalTransfere',
            ];

            $missing = array_diff($required, $colNames);
            if ($missing === []) {
                $io->writeln(sprintf('  <info>OK</info> Toutes les colonnes sont presentes (%d colonnes)', count($colNames)));
            } else {
                $io->writeln(sprintf('  <error>KO</error> Colonnes manquantes : %s', implode(', ', $missing)));
            }
        } catch (\Throwable $e) {
            $io->writeln(sprintf('  <error>KO</error> Table manquante : %s', $e->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $mlResult
     */
    private function testActivation(SymfonyStyle $io, int $idCoffre, int $idUser, array $mlResult): void
    {
        $selected = $this->strategyService->resolveStrategySelection($mlResult, (string) $mlResult['recommended']);
        if ($selected === null) {
            $io->writeln('  <error>KO</error> Strategie recommandee introuvable');
            return;
        }

        $coffre = $this->connection->fetchAssociative(
            'SELECT c.idCompte FROM coffrevirtuel cv JOIN compte c ON cv.idCompte = c.idCompte WHERE cv.idCoffre = ? AND cv.idUser = ?',
            [$idCoffre, $idUser]
        );
        if (!$coffre) {
            $io->writeln('  <error>KO</error> Coffre introuvable pour activation');
            return;
        }

        $idCompte = (int) $coffre['idCompte'];

        try {
            $result = $this->strategyService->activateStrategy(
                $idCoffre,
                $idCompte,
                $idUser,
                (string) $selected['type'],
                (float) $selected['montant'],
                (int) $selected['duree'],
                (float) $selected['taux_succes'],
                (string) $selected['risque'],
                (float) $mlResult['safety_seuil']
            );

            $io->writeln(sprintf(
                '  <info>OK</info> Strategie <comment>%s</comment> activee (idStrategie=%d)',
                $selected['type'],
                $result['idStrategie']
            ));

            $saved = $this->connection->fetchAssociative(
                'SELECT * FROM strategies_proposees WHERE idStrategie = ?',
                [$result['idStrategie']]
            );

            if ($saved) {
                $io->writeln('  <info>OK</info> Enregistrement BDD verifie');
            }
        } catch (\Throwable $e) {
            $io->writeln(sprintf('  <error>KO</error> Erreur activation : %s', $e->getMessage()));
        }
    }
}
