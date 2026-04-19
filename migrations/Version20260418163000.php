<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add OpenStreetMap-compatible address verification fields to garantiecredit.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql("ALTER TABLE garantiecredit
            ADD adresseComplete VARCHAR(255) DEFAULT NULL,
            ADD ville VARCHAR(120) DEFAULT NULL,
            ADD codePostal VARCHAR(30) DEFAULT NULL,
            ADD pays VARCHAR(120) DEFAULT NULL,
            ADD latitude DOUBLE PRECISION DEFAULT NULL,
            ADD longitude DOUBLE PRECISION DEFAULT NULL,
            ADD statutVerificationAdresse VARCHAR(30) NOT NULL DEFAULT 'A verifier'");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql('ALTER TABLE garantiecredit
            DROP adresseComplete,
            DROP ville,
            DROP codePostal,
            DROP pays,
            DROP latitude,
            DROP longitude,
            DROP statutVerificationAdresse');
    }
}
