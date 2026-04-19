<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour supprimer la colonne statutTransaction
 */
final class Version20260417150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la colonne statutTransaction de la table transactions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP COLUMN statutTransaction');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions ADD statutTransaction VARCHAR(30) NOT NULL DEFAULT \'Validée\' AFTER typeTransaction');
    }
}
