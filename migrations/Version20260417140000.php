<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les champs nomDestinataire et emailDestinataire
 */
final class Version20260417140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs nomDestinataire et emailDestinataire à la table transactions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions ADD nomDestinataire VARCHAR(255) DEFAULT NULL AFTER idCompteDestinataire');
        $this->addSql('ALTER TABLE transactions ADD emailDestinataire VARCHAR(255) DEFAULT NULL AFTER nomDestinataire');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transactions DROP nomDestinataire');
        $this->addSql('ALTER TABLE transactions DROP emailDestinataire');
    }
}
