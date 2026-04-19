<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415143923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY `fk_cashback_partenaire`');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY `fk_cashback_transaction`');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY `fk_cashback_partenaire`');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY `fk_cashback_transaction`');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT FK_7EF962A8B00BBD99 FOREIGN KEY (idPartenaire) REFERENCES partenaire (idPartenaire)');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT FK_7EF962A8326AFAA9 FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction)');
        $this->addSql('DROP INDEX fk_cashback_partenaire ON cashback');
        $this->addSql('CREATE INDEX IDX_7EF962A8B00BBD99 ON cashback (idPartenaire)');
        $this->addSql('DROP INDEX fk_cashback_transaction ON cashback');
        $this->addSql('CREATE INDEX IDX_7EF962A8326AFAA9 ON cashback (idTransaction)');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT `fk_cashback_partenaire` FOREIGN KEY (idPartenaire) REFERENCES partenaire (idPartenaire) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT `fk_cashback_transaction` FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY `fk_cashback_entries_partenaire`');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY `fk_cashback_entries_user`');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY `fk_cashback_entries_partenaire`');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY `fk_cashback_entries_user`');
        $this->addSql('ALTER TABLE cashback_entries CHANGE created_at created_at DATETIME NOT NULL, CHANGE bonus_decision bonus_decision VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT FK_484EE5326B3CA4B FOREIGN KEY (id_user) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT FK_484EE532977523A4 FOREIGN KEY (id_partenaire) REFERENCES partenaire (idPartenaire)');
        $this->addSql('DROP INDEX fk_cashback_entries_user ON cashback_entries');
        $this->addSql('CREATE INDEX IDX_484EE5326B3CA4B ON cashback_entries (id_user)');
        $this->addSql('DROP INDEX fk_cashback_entries_partenaire ON cashback_entries');
        $this->addSql('CREATE INDEX IDX_484EE532977523A4 ON cashback_entries (id_partenaire)');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT `fk_cashback_entries_partenaire` FOREIGN KEY (id_partenaire) REFERENCES partenaire (idPartenaire) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT `fk_cashback_entries_user` FOREIGN KEY (id_user) REFERENCES users (idUser) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY `fk_coffre_compte`');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY `fk_coffre_user`');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY `fk_coffre_compte`');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY `fk_coffre_user`');
        $this->addSql('ALTER TABLE coffrevirtuel CHANGE montantActuel montantActuel NUMERIC(12, 2) NOT NULL, CHANGE estVerrouille estVerrouille TINYINT NOT NULL');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT FK_DF54966ACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT FK_DF54966FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('DROP INDEX fk_coffre_compte ON coffrevirtuel');
        $this->addSql('CREATE INDEX IDX_DF54966ACE7FAFA ON coffrevirtuel (idCompte)');
        $this->addSql('DROP INDEX fk_coffre_user ON coffrevirtuel');
        $this->addSql('CREATE INDEX IDX_DF54966FE6E88D7 ON coffrevirtuel (idUser)');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT `fk_coffre_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT `fk_coffre_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE compte DROP FOREIGN KEY `fk_compte_user`');
        $this->addSql('DROP INDEX numeroCompte ON compte');
        $this->addSql('ALTER TABLE compte DROP FOREIGN KEY `fk_compte_user`');
        $this->addSql('ALTER TABLE compte CHANGE solde solde NUMERIC(12, 2) NOT NULL');
        $this->addSql('ALTER TABLE compte ADD CONSTRAINT FK_CFF65260FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('DROP INDEX fk_compte_user ON compte');
        $this->addSql('CREATE INDEX IDX_CFF65260FE6E88D7 ON compte (idUser)');
        $this->addSql('ALTER TABLE compte ADD CONSTRAINT `fk_compte_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY `fk_credit_compte`');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY `fk_credit_compte`');
        $this->addSql('ALTER TABLE credit DROP statut, CHANGE montantAccorde montantAccorde DOUBLE PRECISION NOT NULL, CHANGE salaire salaire DOUBLE PRECISION DEFAULT NULL, CHANGE typeContrat typeContrat VARCHAR(50) NOT NULL, CHANGE ancienneteAnnees ancienneteAnnees INT NOT NULL');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('DROP INDEX fk_credit_compte ON credit');
        $this->addSql('CREATE INDEX IDX_1CC16EFEACE7FAFA ON credit (idCompte)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT `fk_credit_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE garantiecredit DROP FOREIGN KEY `fk_garantie_credit`');
        $this->addSql('ALTER TABLE garantiecredit DROP FOREIGN KEY `fk_garantie_credit`');
        $this->addSql('ALTER TABLE garantiecredit CHANGE idUser idUser INT NOT NULL');
        $this->addSql('ALTER TABLE garantiecredit ADD CONSTRAINT FK_6BADB8F37FD0C664 FOREIGN KEY (idCredit) REFERENCES credit (idCredit)');
        $this->addSql('DROP INDEX fk_garantie_credit ON garantiecredit');
        $this->addSql('CREATE INDEX IDX_6BADB8F37FD0C664 ON garantiecredit (idCredit)');
        $this->addSql('ALTER TABLE garantiecredit ADD CONSTRAINT `fk_garantie_credit` FOREIGN KEY (idCredit) REFERENCES credit (idCredit) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_notifications_read ON notifications');
        $this->addSql('DROP INDEX idx_notifications_recipient_user ON notifications');
        $this->addSql('DROP INDEX idx_notifications_recipient_role ON notifications');
        $this->addSql('DROP INDEX idx_notifications_created ON notifications');
        $this->addSql('ALTER TABLE notifications CHANGE type type VARCHAR(40) NOT NULL, CHANGE is_read is_read TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE partenaire CHANGE status status VARCHAR(30) NOT NULL, CHANGE rating rating DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_reclamation_transaction`');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_reclamation_user`');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_reclamation_transaction`');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_reclamation_user`');
        $this->addSql('ALTER TABLE reclamation CHANGE is_inappropriate is_inappropriate TINYINT NOT NULL, CHANGE is_blurred is_blurred TINYINT NOT NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404326AFAA9 FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction)');
        $this->addSql('DROP INDEX fk_reclamation_user ON reclamation');
        $this->addSql('CREATE INDEX IDX_CE606404FE6E88D7 ON reclamation (idUser)');
        $this->addSql('DROP INDEX fk_reclamation_transaction ON reclamation');
        $this->addSql('CREATE INDEX IDX_CE606404326AFAA9 ON reclamation (idTransaction)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_reclamation_transaction` FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE roue_fortune_points DROP FOREIGN KEY `fk_roue_user`');
        $this->addSql('ALTER TABLE roue_fortune_points CHANGE totalPoints totalPoints INT NOT NULL, CHANGE pointsGagnes pointsGagnes INT NOT NULL');
        $this->addSql('ALTER TABLE roue_fortune_points ADD CONSTRAINT FK_4AECAE5EFE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE surplus_notifications DROP FOREIGN KEY `fk_surplus_user`');
        $this->addSql('ALTER TABLE surplus_notifications CHANGE dateCreation dateCreation DATETIME DEFAULT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (moisAffiche)');
        $this->addSql('ALTER TABLE surplus_notifications ADD CONSTRAINT FK_7985EDBAFE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY `fk_transaction_compte`');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY `fk_transaction_user`');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY `fk_transaction_compte`');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY `fk_transaction_user`');
        $this->addSql('ALTER TABLE transactions ADD idCompteDestinataire INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CF4E07A07 FOREIGN KEY (idCompteDestinataire) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CFE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CF4E07A07 ON transactions (idCompteDestinataire)');
        $this->addSql('DROP INDEX fk_transaction_compte ON transactions');
        $this->addSql('CREATE INDEX IDX_EAA81A4CACE7FAFA ON transactions (idCompte)');
        $this->addSql('DROP INDEX fk_transaction_user ON transactions');
        $this->addSql('CREATE INDEX IDX_EAA81A4CFE6E88D7 ON transactions (idUser)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT `fk_transaction_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT `fk_transaction_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_user_activity_created ON user_activity_log');
        $this->addSql('DROP INDEX idx_user_activity_user ON user_activity_log');
        $this->addSql('ALTER TABLE user_activity_log CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX uq_users_email ON users');
        $this->addSql('DROP INDEX idx_users_role ON users');
        $this->addSql('ALTER TABLE users CHANGE role role VARCHAR(20) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE account_opened_from account_opened_from VARCHAR(180) NOT NULL, CHANGE biometric_enabled biometric_enabled TINYINT NOT NULL, CHANGE account_opened_location account_opened_location VARCHAR(200) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY FK_7EF962A8B00BBD99');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY FK_7EF962A8326AFAA9');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY FK_7EF962A8B00BBD99');
        $this->addSql('ALTER TABLE cashback DROP FOREIGN KEY FK_7EF962A8326AFAA9');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT `fk_cashback_partenaire` FOREIGN KEY (idPartenaire) REFERENCES partenaire (idPartenaire) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT `fk_cashback_transaction` FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_7ef962a8b00bbd99 ON cashback');
        $this->addSql('CREATE INDEX fk_cashback_partenaire ON cashback (idPartenaire)');
        $this->addSql('DROP INDEX idx_7ef962a8326afaa9 ON cashback');
        $this->addSql('CREATE INDEX fk_cashback_transaction ON cashback (idTransaction)');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT FK_7EF962A8B00BBD99 FOREIGN KEY (idPartenaire) REFERENCES partenaire (idPartenaire)');
        $this->addSql('ALTER TABLE cashback ADD CONSTRAINT FK_7EF962A8326AFAA9 FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction)');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY FK_484EE5326B3CA4B');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY FK_484EE532977523A4');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY FK_484EE5326B3CA4B');
        $this->addSql('ALTER TABLE cashback_entries DROP FOREIGN KEY FK_484EE532977523A4');
        $this->addSql('ALTER TABLE cashback_entries CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE bonus_decision bonus_decision VARCHAR(20) DEFAULT \'Pending\' NOT NULL');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT `fk_cashback_entries_partenaire` FOREIGN KEY (id_partenaire) REFERENCES partenaire (idPartenaire) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT `fk_cashback_entries_user` FOREIGN KEY (id_user) REFERENCES users (idUser) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_484ee5326b3ca4b ON cashback_entries');
        $this->addSql('CREATE INDEX fk_cashback_entries_user ON cashback_entries (id_user)');
        $this->addSql('DROP INDEX idx_484ee532977523a4 ON cashback_entries');
        $this->addSql('CREATE INDEX fk_cashback_entries_partenaire ON cashback_entries (id_partenaire)');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT FK_484EE5326B3CA4B FOREIGN KEY (id_user) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE cashback_entries ADD CONSTRAINT FK_484EE532977523A4 FOREIGN KEY (id_partenaire) REFERENCES partenaire (idPartenaire)');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY FK_DF54966ACE7FAFA');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY FK_DF54966FE6E88D7');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY FK_DF54966ACE7FAFA');
        $this->addSql('ALTER TABLE coffrevirtuel DROP FOREIGN KEY FK_DF54966FE6E88D7');
        $this->addSql('ALTER TABLE coffrevirtuel CHANGE montantActuel montantActuel NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, CHANGE estVerrouille estVerrouille TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT `fk_coffre_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT `fk_coffre_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_df54966ace7fafa ON coffrevirtuel');
        $this->addSql('CREATE INDEX fk_coffre_compte ON coffrevirtuel (idCompte)');
        $this->addSql('DROP INDEX idx_df54966fe6e88d7 ON coffrevirtuel');
        $this->addSql('CREATE INDEX fk_coffre_user ON coffrevirtuel (idUser)');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT FK_DF54966ACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE coffrevirtuel ADD CONSTRAINT FK_DF54966FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE compte DROP FOREIGN KEY FK_CFF65260FE6E88D7');
        $this->addSql('ALTER TABLE compte DROP FOREIGN KEY FK_CFF65260FE6E88D7');
        $this->addSql('ALTER TABLE compte CHANGE solde solde NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql('ALTER TABLE compte ADD CONSTRAINT `fk_compte_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX numeroCompte ON compte (numeroCompte)');
        $this->addSql('DROP INDEX idx_cff65260fe6e88d7 ON compte');
        $this->addSql('CREATE INDEX fk_compte_user ON compte (idUser)');
        $this->addSql('ALTER TABLE compte ADD CONSTRAINT FK_CFF65260FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEACE7FAFA');
        $this->addSql('ALTER TABLE credit DROP FOREIGN KEY FK_1CC16EFEACE7FAFA');
        $this->addSql('ALTER TABLE credit ADD statut VARCHAR(30) NOT NULL, CHANGE montantAccorde montantAccorde DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE salaire salaire DOUBLE PRECISION DEFAULT \'0\', CHANGE typeContrat typeContrat VARCHAR(50) DEFAULT \'\' NOT NULL, CHANGE ancienneteAnnees ancienneteAnnees INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT `fk_credit_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_1cc16efeace7fafa ON credit');
        $this->addSql('CREATE INDEX fk_credit_compte ON credit (idCompte)');
        $this->addSql('ALTER TABLE credit ADD CONSTRAINT FK_1CC16EFEACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE garantiecredit DROP FOREIGN KEY FK_6BADB8F37FD0C664');
        $this->addSql('ALTER TABLE garantiecredit DROP FOREIGN KEY FK_6BADB8F37FD0C664');
        $this->addSql('ALTER TABLE garantiecredit CHANGE idUser idUser INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE garantiecredit ADD CONSTRAINT `fk_garantie_credit` FOREIGN KEY (idCredit) REFERENCES credit (idCredit) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_6badb8f37fd0c664 ON garantiecredit');
        $this->addSql('CREATE INDEX fk_garantie_credit ON garantiecredit (idCredit)');
        $this->addSql('ALTER TABLE garantiecredit ADD CONSTRAINT FK_6BADB8F37FD0C664 FOREIGN KEY (idCredit) REFERENCES credit (idCredit)');
        $this->addSql('ALTER TABLE notifications CHANGE type type VARCHAR(40) DEFAULT \'INFO\' NOT NULL, CHANGE is_read is_read TINYINT DEFAULT 0 NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_notifications_read ON notifications (is_read)');
        $this->addSql('CREATE INDEX idx_notifications_recipient_user ON notifications (recipient_user_id)');
        $this->addSql('CREATE INDEX idx_notifications_recipient_role ON notifications (recipient_role)');
        $this->addSql('CREATE INDEX idx_notifications_created ON notifications (created_at)');
        $this->addSql('ALTER TABLE partenaire CHANGE status status VARCHAR(30) DEFAULT \'Actif\' NOT NULL, CHANGE rating rating DOUBLE PRECISION DEFAULT \'4\' NOT NULL');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404FE6E88D7');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404326AFAA9');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404FE6E88D7');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404326AFAA9');
        $this->addSql('ALTER TABLE reclamation CHANGE is_inappropriate is_inappropriate TINYINT DEFAULT 0 NOT NULL, CHANGE is_blurred is_blurred TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_reclamation_transaction` FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_ce606404fe6e88d7 ON reclamation');
        $this->addSql('CREATE INDEX fk_reclamation_user ON reclamation (idUser)');
        $this->addSql('DROP INDEX idx_ce606404326afaa9 ON reclamation');
        $this->addSql('CREATE INDEX fk_reclamation_transaction ON reclamation (idTransaction)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404FE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404326AFAA9 FOREIGN KEY (idTransaction) REFERENCES transactions (idTransaction)');
        $this->addSql('ALTER TABLE roue_fortune_points DROP FOREIGN KEY FK_4AECAE5EFE6E88D7');
        $this->addSql('ALTER TABLE roue_fortune_points CHANGE totalPoints totalPoints INT DEFAULT 0 NOT NULL, CHANGE pointsGagnes pointsGagnes INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE roue_fortune_points ADD CONSTRAINT `fk_roue_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE surplus_notifications DROP FOREIGN KEY FK_7985EDBAFE6E88D7');
        $this->addSql('ALTER TABLE surplus_notifications CHANGE dateCreation dateCreation DATETIME DEFAULT CURRENT_TIMESTAMP, DROP PRIMARY KEY, ADD PRIMARY KEY (idUser, moisAffiche)');
        $this->addSql('ALTER TABLE surplus_notifications ADD CONSTRAINT `fk_surplus_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CACE7FAFA');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CF4E07A07');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CFE6E88D7');
        $this->addSql('DROP INDEX IDX_EAA81A4CF4E07A07 ON transactions');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CACE7FAFA');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CFE6E88D7');
        $this->addSql('ALTER TABLE transactions DROP idCompteDestinataire');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT `fk_transaction_compte` FOREIGN KEY (idCompte) REFERENCES compte (idCompte) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT `fk_transaction_user` FOREIGN KEY (idUser) REFERENCES users (idUser) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_eaa81a4cace7fafa ON transactions');
        $this->addSql('CREATE INDEX fk_transaction_compte ON transactions (idCompte)');
        $this->addSql('DROP INDEX idx_eaa81a4cfe6e88d7 ON transactions');
        $this->addSql('CREATE INDEX fk_transaction_user ON transactions (idUser)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CACE7FAFA FOREIGN KEY (idCompte) REFERENCES compte (idCompte)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CFE6E88D7 FOREIGN KEY (idUser) REFERENCES users (idUser)');
        $this->addSql('ALTER TABLE users CHANGE role role VARCHAR(20) DEFAULT \'ROLE_USER\' NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE account_opened_from account_opened_from VARCHAR(180) DEFAULT \'Unknown device\' NOT NULL, CHANGE biometric_enabled biometric_enabled TINYINT DEFAULT 0 NOT NULL, CHANGE account_opened_location account_opened_location VARCHAR(200) DEFAULT \'Unknown location\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_users_email ON users (email)');
        $this->addSql('CREATE INDEX idx_users_role ON users (role)');
        $this->addSql('ALTER TABLE user_activity_log CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_user_activity_created ON user_activity_log (created_at)');
        $this->addSql('CREATE INDEX idx_user_activity_user ON user_activity_log (idUser)');
    }
}
