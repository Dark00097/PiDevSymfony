<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-currency support to transactions table';
    }

    public function up(Schema $schema): void
    {
        // Ajouter les colonnes pour le support multi-devises
        $this->addSql("ALTER TABLE transactions ADD COLUMN original_amount DECIMAL(12,3) NULL AFTER montant");
        $this->addSql("ALTER TABLE transactions ADD COLUMN original_currency VARCHAR(3) DEFAULT 'TND' AFTER original_amount");
        $this->addSql("ALTER TABLE transactions ADD COLUMN exchange_rate DECIMAL(10,6) NULL AFTER original_currency");
        $this->addSql("ALTER TABLE transactions ADD COLUMN conversion_fee DECIMAL(12,3) DEFAULT 0 AFTER exchange_rate");
        
        // Mettre à jour les transactions existantes
        $this->addSql("UPDATE transactions SET original_amount = montant, original_currency = 'TND', exchange_rate = 1.0 WHERE original_amount IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP COLUMN original_amount');
        $this->addSql('ALTER TABLE transactions DROP COLUMN original_currency');
        $this->addSql('ALTER TABLE transactions DROP COLUMN exchange_rate');
        $this->addSql('ALTER TABLE transactions DROP COLUMN conversion_fee');
    }
}
