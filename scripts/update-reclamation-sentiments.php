<?php

/**
 * Script pour mettre à jour les sentiments de toutes les réclamations existantes
 * Usage: php scripts/update-reclamation-sentiments.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// Configuration de la base de données
$host = $_ENV['DATABASE_HOST'] ?? 'localhost';
$port = $_ENV['DATABASE_PORT'] ?? '3306';
$dbname = $_ENV['DATABASE_NAME'] ?? 'projetpidev';
$user = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✓ Connexion à la base de données établie\n\n";

    // Récupérer toutes les réclamations
    $stmt = $pdo->query("SELECT idReclamation, description, sentiment FROM reclamation");
    $reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Nombre de réclamations trouvées: " . count($reclamations) . "\n\n";

    // Charger le service d'analyse de sentiment
    require_once __DIR__ . '/../src/Service/SentimentAnalysisService.php';
    $sentimentService = new App\Service\SentimentAnalysisService();

    $updated = 0;
    $skipped = 0;

    foreach ($reclamations as $rec) {
        $id = $rec['idReclamation'];
        $description = $rec['description'] ?? '';
        $currentSentiment = $rec['sentiment'] ?? 'neutral';

        if (empty($description)) {
            echo "  ⊘ Réclamation #$id: description vide, ignorée\n";
            $skipped++;
            continue;
        }

        // Analyser le sentiment
        $analysis = $sentimentService->analyzeSentiment($description);
        $newSentiment = $analysis['sentiment'];
        $emoji = $analysis['emoji'];
        $confidence = $analysis['confidence'];

        // Mettre à jour si le sentiment a changé
        if ($newSentiment !== $currentSentiment) {
            $updateStmt = $pdo->prepare("UPDATE reclamation SET sentiment = ? WHERE idReclamation = ?");
            $updateStmt->execute([$newSentiment, $id]);

            echo "  ✓ Réclamation #$id: $currentSentiment → $newSentiment $emoji (confiance: " . ($confidence * 100) . "%)\n";
            echo "    Description: " . substr($description, 0, 60) . "...\n";
            $updated++;
        } else {
            echo "  = Réclamation #$id: sentiment inchangé ($currentSentiment $emoji)\n";
            $skipped++;
        }
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Résumé:\n";
    echo "  • Réclamations mises à jour: $updated\n";
    echo "  • Réclamations ignorées: $skipped\n";
    echo "  • Total: " . count($reclamations) . "\n";
    echo "═══════════════════════════════════════════════════════════\n";

} catch (PDOException $e) {
    echo "✗ Erreur de connexion: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
