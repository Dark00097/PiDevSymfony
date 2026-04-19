<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419121040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le chiffrement de montant et montantPaye — passage de VARCHAR à DECIMAL(12,3)';
    }

    public function up(Schema $schema): void
    {
        // Convertir les valeurs chiffrées existantes en NULL (elles ne sont plus lisibles en clair)
        // puis changer le type de colonne
        $this->addSql('UPDATE transactions SET montant = NULL, montantPaye = NULL');
        $this->addSql('ALTER TABLE transactions CHANGE montant montant NUMERIC(12, 3) DEFAULT NULL, CHANGE montantPaye montantPaye NUMERIC(12, 3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions CHANGE montant montant VARCHAR(255) DEFAULT NULL, CHANGE montantPaye montantPaye VARCHAR(255) DEFAULT NULL');
    }
}
