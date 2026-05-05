<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : création de la table StrategiesProposees
 * pour le Smart Financial Strategy Optimizer (ML Random Forest)
 */
final class Version20260501120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table StrategiesProposees pour le Smart Financial Strategy Optimizer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `strategies_proposees` (
                `idStrategie`          INT(11)        NOT NULL AUTO_INCREMENT,
                `idCoffre`             INT(11)        NOT NULL,
                `idCompte`             INT(11)        NOT NULL,
                `idUser`               INT(11)        NOT NULL,
                `typeStrategie`        VARCHAR(20)    NOT NULL COMMENT 'DOUCE | MODEREE | AGRESSIVE',
                `montantMensuel`       DECIMAL(12,2)  NOT NULL,
                `dureeEstimee`         INT(11)        NOT NULL COMMENT 'en mois',
                `tauxSucces`           DECIMAL(5,2)   NOT NULL COMMENT 'pourcentage 0-100',
                `niveauRisque`         VARCHAR(20)    NOT NULL COMMENT 'Très faible | Faible | Modéré | Élevé',
                `statut`               VARCHAR(20)    NOT NULL DEFAULT 'active' COMMENT 'active | suspendue | terminee',
                `safetyCheckSeuil`     DECIMAL(12,2)  NOT NULL DEFAULT 50.00 COMMENT 'solde minimum avant suspension',
                `dateActivation`       DATETIME       NOT NULL,
                `dateProchaineExecution` DATE         DEFAULT NULL,
                `nombreTransferts`     INT(11)        NOT NULL DEFAULT 0,
                `montantTotalTransfere` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `createdAt`            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updatedAt`            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`idStrategie`),
                KEY `idx_coffre`  (`idCoffre`),
                KEY `idx_compte`  (`idCompte`),
                KEY `idx_user`    (`idUser`),
                KEY `idx_statut`  (`statut`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Stratégies d épargne générées par le ML Random Forest'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `strategies_proposees`');
    }
}
