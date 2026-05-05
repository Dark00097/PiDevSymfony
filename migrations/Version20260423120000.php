<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute statutDocument (en_attente|valide|refuse) dans garantiecredit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE garantiecredit ADD statutDocument VARCHAR(20) NOT NULL DEFAULT 'en_attente'");
        $this->addSql("
            UPDATE garantiecredit
            SET statutDocument = CASE
                WHEN LOWER(COALESCE(statutVerificationDocument, 'en_attente')) = 'valide' THEN 'valide'
                WHEN LOWER(COALESCE(statutVerificationDocument, 'en_attente')) IN ('rejete', 'refuse') THEN 'refuse'
                ELSE 'en_attente'
            END
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE garantiecredit DROP COLUMN statutDocument');
    }
}

