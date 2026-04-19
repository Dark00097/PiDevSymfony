<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour modifier le champ compteDestinataire de INT vers VARCHAR(255)
 */
final class Version20260417130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modifie le champ idCompteDestinataire de INT (relation) vers VARCHAR(255) (string)';
    }

    public function up(Schema $schema): void
    {
        // Supprimer la contrainte de clé étrangère si elle existe
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY IF EXISTS FK_EAA81A4CF4E07A07');
        
        // Supprimer l'index si il existe
        $this->addSql('DROP INDEX IF EXISTS IDX_EAA81A4CF4E07A07 ON transactions');
        
        // Modifier le type de la colonne de INT vers VARCHAR(255)
        $this->addSql('ALTER TABLE transactions MODIFY idCompteDestinataire VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Attention : le rollback peut perdre des données si des numéros de compte non numériques ont été stockés
        $this->addSql('ALTER TABLE transactions MODIFY idCompteDestinataire INT DEFAULT NULL');
        
        // Recréer la contrainte de clé étrangère
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CF4E07A07 FOREIGN KEY (idCompteDestinataire) REFERENCES compte (idCompte)');
        
        // Recréer l'index
        $this->addSql('CREATE INDEX IDX_EAA81A4CF4E07A07 ON transactions (idCompteDestinataire)');
    }
}
