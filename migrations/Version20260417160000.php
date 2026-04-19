<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sentiment column to reclamation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reclamation ADD COLUMN sentiment VARCHAR(20) DEFAULT 'neutral' AFTER description");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reclamation DROP COLUMN sentiment');
    }
}
