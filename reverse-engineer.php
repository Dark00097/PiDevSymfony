<?php

declare(strict_types=1);

use App\Kernel;
use App\Service\DatabaseReverseEngineer;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

// Load .env variables for standalone script execution (php reverse-engineer.php).
if (!isset($_SERVER['APP_ENV']) && !isset($_ENV['APP_ENV'])) {
    (new Dotenv())->bootEnv(__DIR__ . '/.env');
}

/**
 * Method 2 (script approach):
 * - Boot Symfony so .env and service container are loaded.
 * - Use Doctrine DBAL to introspect the current schema dynamically.
 * - Generate entity metadata/classes so make:entity --regenerate can refresh methods if needed.
 */
$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$debug = ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1') !== '0';

$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? null;
if ($databaseUrl === null || $databaseUrl === '') {
    throw new RuntimeException('DATABASE_URL is missing. Define it in .env or environment variables.');
}

$dsnParser = new DsnParser([
    'mysql' => 'pdo_mysql',
    'mariadb' => 'pdo_mysql',
    'postgres' => 'pdo_pgsql',
    'pgsql' => 'pdo_pgsql',
    'sqlite' => 'pdo_sqlite',
    'sqlite3' => 'pdo_sqlite',
    'mssql' => 'pdo_sqlsrv',
    'sqlsrv' => 'pdo_sqlsrv',
]);
$connection = DriverManager::getConnection($dsnParser->parse($databaseUrl));
$schemaManager = $connection->createSchemaManager();
$tables = $schemaManager->listTables();

echo sprintf("Connected to database: %s\n", $connection->getDatabase() ?? 'unknown');
echo sprintf("Detected %d table(s).\n", count($tables));

// Prepare metadata summary from the live schema for workshop traceability.
$metadata = [];
foreach ($tables as $table) {
    $tableMetadata = [
        'table' => $table->getName(),
        'columns' => [],
        'foreign_keys' => [],
    ];

    foreach ($table->getColumns() as $column) {
        $tableMetadata['columns'][] = [
            'name' => $column->getName(),
            'type' => $column->getType()::class,
            'nullable' => !$column->getNotnull(),
            'length' => $column->getLength(),
            'precision' => $column->getPrecision(),
            'scale' => $column->getScale(),
        ];
    }

    foreach ($table->getForeignKeys() as $foreignKey) {
        $tableMetadata['foreign_keys'][] = [
            'local_columns' => $foreignKey->getLocalColumns(),
            'foreign_table' => $foreignKey->getForeignTableName(),
            'foreign_columns' => $foreignKey->getForeignColumns(),
        ];
    }

    $metadata[] = $tableMetadata;
}

$kernel = new Kernel($env, (bool) $debug);
$reverseEngineer = new DatabaseReverseEngineer($connection, $kernel);
$report = $reverseEngineer->generate(withMethods: false);

$metadataDir = __DIR__ . '/var/reverse-engineering';
if (!is_dir($metadataDir)) {
    mkdir($metadataDir, 0777, true);
}
file_put_contents(
    $metadataDir . '/schema-metadata.json',
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo sprintf("Generated %d entity file(s) and %d repository file(s).\n", count($report['entities']), count($report['repositories']));
echo "Metadata file: var/reverse-engineering/schema-metadata.json\n";

if ($report['warnings'] !== []) {
    echo "Warnings:\n";
    foreach ($report['warnings'] as $warning) {
        echo "- {$warning}\n";
    }
}

echo "Now run: php bin/console make:entity --regenerate\n";
