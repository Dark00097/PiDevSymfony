<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add credit_garantie_link table to allow one garantie to be linked to multiple credits.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_garantie_link (
            id INT AUTO_INCREMENT NOT NULL,
            idCredit INT NOT NULL,
            idGarantie INT NOT NULL,
            linkedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX IDX_CREDIT_GARANTIE_LINK_CREDIT (idCredit),
            INDEX IDX_CREDIT_GARANTIE_LINK_GARANTIE (idGarantie),
            UNIQUE INDEX UNIQ_CREDIT_GARANTIE_LINK_PAIR (idCredit, idGarantie),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credit_garantie_link');
    }
}
