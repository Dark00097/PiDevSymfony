<?php

/**
 * Script de migration pour mettre à jour les types de transactions existants
 * vers les nouveaux types standardisés : DEPOT, RETRAIT, VIREMENT, PAIEMENT
 * 
 * Usage: php migrations/migrate_transaction_types.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// Configuration de la base de données
$databaseUrl = $_ENV['DATABASE_URL'] ?? '';

// Parser l'URL de la base de données
if (preg_match('/mysql:\/\/([^:]+):([^@]*)@([^:]+):(\d+)\/([^?]+)/', $databaseUrl, $matches)) {
    $user = $matches[1];
    $password = $matches[2];
    $host = $matches[3];
    $port = $matches[4];
    $dbname = $matches[5];
} else {
    echo "✗ Erreur: Impossible de parser DATABASE_URL\n";
    exit(1);
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ Connexion à la base de données réussie\n";

    // Mapping des anciens types vers les nouveaux
    $typeMapping = [
        'versement' => 'DEPOT',
        'credit' => 'DEPOT',
        'depot' => 'DEPOT',
        'entree' => 'DEPOT',
        'entrée' => 'DEPOT',
        
        'debit' => 'RETRAIT',
        'retrait' => 'RETRAIT',
        'sortie' => 'RETRAIT',
        
        'virement' => 'VIREMENT',
        'transfer' => 'VIREMENT',
        
        'paiement' => 'PAIEMENT',
        'payment' => 'PAIEMENT',
    ];

    // Récupérer toutes les transactions
    $stmt = $pdo->query('SELECT idTransaction, typeTransaction FROM transactions');
    $transactions = $stmt->fetchAll();

    echo "✓ " . count($transactions) . " transactions trouvées\n";

    $updated = 0;
    $unchanged = 0;

    $pdo->beginTransaction();

    foreach ($transactions as $transaction) {
        $oldType = trim($transaction['typeTransaction']);
        $normalizedOldType = strtolower($oldType);
        
        // Déterminer le nouveau type
        $newType = null;
        foreach ($typeMapping as $pattern => $standardType) {
            if (str_contains($normalizedOldType, $pattern)) {
                $newType = $standardType;
                break;
            }
        }

        // Si aucun mapping trouvé, utiliser DEPOT par défaut
        if ($newType === null) {
            $newType = 'DEPOT';
        }

        // Mettre à jour si le type a changé
        if ($oldType !== $newType) {
            $updateStmt = $pdo->prepare('UPDATE transactions SET typeTransaction = ? WHERE idTransaction = ?');
            $updateStmt->execute([$newType, $transaction['idTransaction']]);
            $updated++;
            echo "  → Transaction #{$transaction['idTransaction']}: '{$oldType}' → '{$newType}'\n";
        } else {
            $unchanged++;
        }
    }

    $pdo->commit();

    echo "\n✓ Migration terminée avec succès!\n";
    echo "  - Transactions mises à jour: {$updated}\n";
    echo "  - Transactions inchangées: {$unchanged}\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
