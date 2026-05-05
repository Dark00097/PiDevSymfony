-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 02 mai 2026 à 13:35
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `projetpidev`
--

-- --------------------------------------------------------

--
-- Structure de la table `cashback`
--

CREATE TABLE `cashback` (
  `idCashback` int(11) NOT NULL,
  `idPartenaire` int(11) NOT NULL,
  `idTransaction` int(11) NOT NULL,
  `montantAchat` double NOT NULL,
  `tauxApplique` double NOT NULL,
  `montantCashback` double NOT NULL,
  `dateAchat` varchar(20) NOT NULL,
  `dateCredit` varchar(20) DEFAULT NULL,
  `dateExpiration` varchar(20) DEFAULT NULL,
  `statut` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cashback_entries`
--

CREATE TABLE `cashback_entries` (
  `id_cashback` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_partenaire` int(11) DEFAULT NULL,
  `partenaire_nom` varchar(120) NOT NULL,
  `montant_achat` double NOT NULL,
  `taux_applique` double NOT NULL,
  `montant_cashback` double NOT NULL,
  `date_achat` date NOT NULL,
  `date_credit` date DEFAULT NULL,
  `date_expiration` date DEFAULT NULL,
  `statut` varchar(30) NOT NULL,
  `transaction_ref` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_rating` double DEFAULT NULL,
  `user_rating_comment` varchar(255) DEFAULT NULL,
  `bonus_decision` varchar(20) NOT NULL DEFAULT 'Pending',
  `bonus_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cashback_entries`
--

INSERT INTO `cashback_entries` (`id_cashback`, `id_user`, `id_partenaire`, `partenaire_nom`, `montant_achat`, `taux_applique`, `montant_cashback`, `date_achat`, `date_credit`, `date_expiration`, `statut`, `transaction_ref`, `created_at`, `user_rating`, `user_rating_comment`, `bonus_decision`, `bonus_note`) VALUES
(1, 3, NULL, 'Zara', 120, 3, 3.6, '2026-02-27', '2026-02-27', '2026-03-14', 'Expire', '', '2026-02-19 21:21:40', NULL, NULL, 'Pending', NULL),
(2, 2, 1, 'Geant', 10, 5, 0.5, '2026-02-20', NULL, '2026-08-20', 'En attente', '', '2026-02-19 21:30:12', NULL, NULL, 'Pending', NULL),
(3, 2, 1, 'Geant', 10, 5, 0.5, '2026-02-27', NULL, '2026-08-27', 'En attente', '', '2026-02-19 21:39:41', NULL, NULL, 'Pending', NULL),
(5, 3, 1, 'Geant', 100, 2, 12, '2026-03-02', NULL, '2026-09-02', 'Valide', 'ADMIN_REWARD +5.00 (Bonus fidelite) | ADMIN_REWARD +5.00 (Bonus fidelite)', '2026-03-02 00:37:58', 5, '', 'Pending', 'Bonus fidelite'),
(6, 3, 1, 'Geant', 5, 1, 0.05, '2026-03-26', NULL, '2026-09-26', 'En attente', '', '2026-03-04 19:25:55', 5, 'd', 'Pending', NULL),
(7, 3, 1, 'Geant', 9, 1, 0.09, '2026-03-26', NULL, '2026-09-26', 'En attente', '', '2026-03-04 19:28:59', NULL, NULL, 'Pending', NULL),
(8, 3, 1, 'Geant', 2, 1, 0.02, '2026-03-19', NULL, '2026-09-19', 'En attente', '', '2026-03-04 20:21:08', NULL, NULL, 'Pending', NULL),
(10, 3, 2, 'carrefour', 5, 1, 0.05, '2026-03-20', NULL, '2026-09-20', 'En attente', '', '2026-03-04 20:38:56', NULL, NULL, 'Pending', NULL),
(11, 10, 2, 'hiugyug', 1200, 3, 36, '2026-04-07', NULL, NULL, 'En attente', '', '2026-04-07 10:40:23', NULL, NULL, 'Pending', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `coffrevirtuel`
--

CREATE TABLE `coffrevirtuel` (
  `idCoffre` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `objectifMontant` decimal(12,2) NOT NULL,
  `montantActuel` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dateCreation` varchar(20) NOT NULL,
  `dateObjectifs` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `estVerrouille` tinyint(1) NOT NULL DEFAULT 1,
  `idCompte` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `coffrevirtuel`
--

INSERT INTO `coffrevirtuel` (`idCoffre`, `nom`, `objectifMontant`, `montantActuel`, `dateCreation`, `dateObjectifs`, `status`, `estVerrouille`, `idCompte`, `idUser`) VALUES
(67, 'aza', 800.20, 142.00, '2026-04-08', '2026-04-10', 'Actif', 0, 125, 8),
(75, 'test2', 452.00, 20.00, '2026-04-08', '2026-07-13', 'Actif', 0, 81, 9),
(84, 'comptejdidversion1', 8400.00, 8400.00, '2026-04-11', '2026-04-25', 'Actif', 1, 140, 12),
(85, 'modifier12', 5400.00, 5000.00, '2026-04-11', '2026-10-18', 'Actif', 1, 81, 9),
(86, 'ModifierCompte100', 6500.00, 6500.00, '2026-04-11', '2026-05-01', 'Fermé', 1, 142, 9),
(87, 'mouhamed1', 8950.00, 984.99, '2026-04-11', '2026-04-18', 'Actif', 0, 145, 13),
(89, 'verifier', 8500.00, 5600.00, '2026-04-12', '2026-04-23', 'Actif', 0, 152, 12),
(90, 'Coffre pour le Futur', 3000.00, 0.00, '2026-04-12', '2026-04-23', 'Actif', 0, 142, 9),
(92, 'Projet de Rêve', 4035.00, 30.00, '2026-04-12', '2026-04-24', 'Actif', 0, 81, 9),
(93, 'Clef du succès projet', 11000.00, 7800.00, '2026-04-14', NULL, 'Actif', 0, 153, 13),
(94, 'Vacances été 202612', 7800.00, 4200.00, '2026-04-14', '2026-04-30', 'Fermé', 0, 153, 13),
(96, 'Coffre Investissement', 8000.00, 1200.00, '2026-04-19', '2026-04-25', 'Actif', 0, 142, 9),
(98, 'Fonds de loisir', 1120.00, 0.00, '2026-04-16', '2026-04-24', 'Actif', 0, 143, 9),
(201, 'Voyage', 5000.00, 3200.00, '2026-04-10', '2026-05-05', 'Actif', 0, 101, 15),
(202, 'Maison', 20000.00, 20000.00, '2026-04-19', '2026-07-15', 'Actif', 1, 101, 1),
(203, 'Voiture', 15000.00, 8000.00, '2026-04-18 17:03:45', '2026-10-01', 'Actif', 0, 102, 15),
(204, 'Études', 10000.00, 10000.00, '2026-04-19', '2026-09-01', 'Actif', 1, 102, 1),
(205, 'Urgence', 7000.00, 2100.00, '2026-04-23', '2026-06-01', 'Actif', 0, 103, 15),
(206, 'Amélioration Pro', 1680.00, 530.00, '2026-04-19', '2026-04-25', 'Actif', 1, 143, 9),
(207, 'Securite 1 mois', 1300.00, 1300.00, '2026-04-20', '2026-04-22', 'Actif', 1, 165, 16),
(208, 'Rimene1', 5600.00, 1660.00, '2026-04-20', '2026-04-30', 'Actif', 0, 165, 16),
(209, 'Coffre Rimene 2', 45000.00, 25000.00, '2026-04-20', '2026-04-30', 'Bloqué', 0, 167, 16),
(210, 'Coffre Bien-être', 1500.00, 1500.00, '2026-04-20', '2026-04-25', 'Actif', 1, 167, 16),
(213, 'Coffre d\'Urgence', 7000.00, 7000.00, '2026-04-22', '2026-05-01', 'Fermé', 1, 175, 16),
(214, 'Avenir Soigné', 18000.00, 17020.00, '2026-04-23', '2026-04-25', 'Actif', 0, 103, 15),
(215, 'Coffre Épargne Voyage', 10000.00, 7000.00, '2026-04-23', '2026-04-26', 'Actif', 0, 179, 18),
(216, 'Coffre Retraite', 30000.00, 30000.00, '2026-04-23', '2026-05-02', 'Actif', 1, 179, 18),
(217, 'Kodra 1', 7500.00, 6000.00, '2026-04-23', '2026-05-09', 'Actif', 0, 178, 18),
(218, 'Securite Financiere', 1000.00, 620.00, '2026-04-23', '2026-05-04', 'Actif', 0, 178, 18),
(219, 'Projet de Rêve', 600.00, 600.00, '2026-05-01', '2026-06-07', 'Actif', 1, 180, 17),
(220, 'offf', 5620.00, 751.00, '2026-05-01', '2026-05-28', 'Actif', 0, 180, 17);

-- --------------------------------------------------------

--
-- Structure de la table `compte`
--

CREATE TABLE `compte` (
  `idCompte` int(11) NOT NULL,
  `numeroCompte` varchar(30) NOT NULL,
  `solde` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dateOuverture` varchar(10) NOT NULL,
  `statutCompte` varchar(20) NOT NULL,
  `plafondRetrait` decimal(12,2) NOT NULL,
  `plafondVirement` decimal(12,2) NOT NULL,
  `typeCompte` varchar(20) NOT NULL,
  `idUser` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `compte`
--

INSERT INTO `compte` (`idCompte`, `numeroCompte`, `solde`, `dateOuverture`, `statutCompte`, `plafondRetrait`, `plafondVirement`, `typeCompte`, `idUser`) VALUES
(81, 'CB-101', 100.00, '2026-02-19', 'Actif', 12.30, 12.85, 'Épargne', 9),
(101, 'CPT-101', 1000.00, '2026-04-18', 'Actif', 2000.00, 10000.00, 'Epargne', 15),
(102, 'CB-104', 5000.00, '2026-04-18', 'Actif', 500.00, 500.00, 'Professionnel', 15),
(103, 'CB-103', 7986.00, '2026-04-18', 'Actif', 120.00, 120.00, 'Courant', 15),
(122, 'CB-201', 200.00, '2026-04-08', 'Actif', 120.00, 450.20, 'Courant', 1),
(125, 'CB-27', 410.00, '2026-04-08', 'Bloqué', 50.00, 40.00, 'Épargne', 8),
(126, 'CB-051', 4800.00, '2026-04-08', 'Fermé', 2.19, 50.00, 'Professionnel', 1),
(135, 'CB-258', 500.00, '2026-04-09', 'Fermé', 120.00, 120.00, 'Courant', 1),
(140, 'CB-203', 7800.00, '2026-04-11', 'Fermé', 840.00, 800.00, 'Épargne', 12),
(141, 'CB-800', 9520.00, '2026-04-11', 'Bloqué', 78.00, 45.00, 'Professionnel', 12),
(142, 'CB-100', 4500.00, '2026-04-11', 'Actif', 840.00, 510.00, 'Courant', 9),
(143, 'CB-102', 2500.00, '2026-04-11', 'Actif', 520.00, 840.00, 'Professionnel', 9),
(145, 'CB-854', 840.00, '2026-04-11', 'Actif', 40.00, 49.98, 'Épargne', 13),
(149, 'CB-011', 500.00, '2026-04-11', 'Actif', 20.00, 20.00, 'Épargne', 13),
(152, 'CB-331', 40000.00, '2026-04-11', 'Actif', 50.00, 780.00, 'Épargne', 12),
(153, 'CB-500', 550.20, '2026-04-12', 'Actif', 10.00, 500.10, 'Professionnel', 13),
(165, 'CB-501', 2250.00, '2026-04-20', 'Actif', 250.00, 650.00, 'Courant', 16),
(167, 'CB-650', 8090.00, '2026-04-20', 'Actif', 120.00, 620.00, 'Épargne', 16),
(175, 'CB-230', 3500.00, '2026-04-22', 'Actif', 20.00, 500.60, 'Professionnel', 16),
(177, 'CB-311', 5200.50, '2026-04-23', 'Actif', 20.00, 150.30, 'Professionnel', 15),
(178, 'CB-620', 4520.20, '2026-04-23', 'Actif', 120.00, 140.00, 'Épargne', 18),
(179, 'CB-840', 7500.00, '2026-04-23', 'Actif', 450.00, 780.47, 'Professionnel', 18),
(180, 'CB-150', 1099.00, '2026-04-30', 'Actif', 152.00, 140.00, 'Courant', 17),
(181, 'CB-212', 9800.00, '2026-05-01', 'Actif', 450.00, 840.20, 'Professionnel', 17);

-- --------------------------------------------------------

--
-- Structure de la table `credit`
--

CREATE TABLE `credit` (
  `idCredit` int(11) NOT NULL,
  `idCompte` int(11) NOT NULL,
  `typeCredit` varchar(50) NOT NULL,
  `montantDemande` double NOT NULL,
  `autofinancement` double DEFAULT NULL,
  `duree` int(11) NOT NULL,
  `tauxInteret` double NOT NULL,
  `mensualite` double NOT NULL,
  `montantAccorde` double NOT NULL DEFAULT 0,
  `dateDemande` varchar(20) NOT NULL,
  `statut` varchar(30) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `salaire` double DEFAULT 0,
  `typeContrat` varchar(50) NOT NULL DEFAULT '',
  `ancienneteAnnees` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `credit_garantie_link`
--

CREATE TABLE `credit_garantie_link` (
  `id` int(11) NOT NULL,
  `idCredit` int(11) NOT NULL,
  `idGarantie` int(11) NOT NULL,
  `linkedAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260415143923', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260417130000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260417140000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260417150000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260417160000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260418010000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260418163000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260418170000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260419121040', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260423120000', '2026-04-23 13:17:00', 0),
('DoctrineMigrations\\Version20260501120000', '2026-05-01 23:48:20', 259);

-- --------------------------------------------------------

--
-- Structure de la table `garantiecredit`
--

CREATE TABLE `garantiecredit` (
  `idGarantie` int(11) NOT NULL,
  `idCredit` int(11) DEFAULT NULL,
  `typeGarantie` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `adresseBien` varchar(255) DEFAULT NULL,
  `adresseComplete` varchar(255) DEFAULT NULL,
  `ville` varchar(120) DEFAULT NULL,
  `codePostal` varchar(30) DEFAULT NULL,
  `pays` varchar(120) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `statutVerificationAdresse` varchar(30) NOT NULL DEFAULT 'A verifier',
  `valeurEstimee` double NOT NULL,
  `valeurRetenue` double NOT NULL,
  `documentJustificatif` varchar(255) DEFAULT NULL,
  `documentUrl` varchar(255) DEFAULT NULL,
  `documentPublicId` varchar(255) DEFAULT NULL,
  `documentMimeType` varchar(120) DEFAULT NULL,
  `documentUploadedAt` varchar(30) DEFAULT NULL,
  `statutVerificationDocument` varchar(30) NOT NULL DEFAULT 'en_attente',
  `statutDocument` varchar(20) NOT NULL DEFAULT 'en_attente',
  `remarqueAdmin` varchar(500) DEFAULT NULL,
  `dateEvaluation` varchar(20) NOT NULL,
  `nomGarant` varchar(100) DEFAULT NULL,
  `statut` varchar(30) NOT NULL,
  `idUser` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mobile_access_tokens`
--

CREATE TABLE `mobile_access_tokens` (
  `idMobileAccessToken` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_prefix` varchar(16) NOT NULL,
  `device_id` varchar(140) NOT NULL,
  `device_name` varchar(140) NOT NULL DEFAULT 'Mobile device',
  `platform` varchar(40) DEFAULT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `idNotification` int(11) NOT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `recipient_role` varchar(20) DEFAULT NULL,
  `related_user_id` int(11) DEFAULT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'INFO',
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`idNotification`, `recipient_user_id`, `recipient_role`, `related_user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, NULL, 'ROLE_ADMIN', 6, 'USER_SIGNUP_PENDING', 'New user pending approval', 'User - mokhtar (dohinom812@kaoing.com) created an account and is waiting for admin review.', 1, '2026-03-01 19:42:01'),
(2, NULL, 'ROLE_ADMIN', 7, 'USER_SIGNUP_PENDING', 'New user pending approval', 'User - moncef (maciwox300@kaoing.com) created an account and is waiting for admin review.', 1, '2026-03-01 19:56:59'),
(3, 7, NULL, 7, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 1, '2026-03-01 19:57:39'),
(4, NULL, 'ROLE_ADMIN', 8, 'USER_SIGNUP_PENDING', 'New user pending approval', 'User - azzedine (hodor18057@netoiu.com) created an account and is waiting for admin review.', 1, '2026-03-01 20:12:43'),
(5, 8, NULL, 8, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 1, '2026-03-01 20:13:06'),
(6, 3, NULL, 3, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 1, '2026-03-01 20:25:08'),
(7, NULL, 'ROLE_ADMIN', 3, 'CASHBACK_SUBMITTED', 'New cashback submitted', 'User #3 submitted a cashback request at partner \"Geant\" for purchase amount 9.00 DT.', 1, '2026-03-04 19:28:59'),
(8, NULL, 'ROLE_ADMIN', 3, 'CASHBACK_RATING', 'New cashback rating submitted', 'User #3 rated partner \"Geant\" with 5.0/5. Comment: d', 1, '2026-03-04 19:31:21'),
(9, 3, NULL, 3, 'CASHBACK_RATING', 'Rating sent', 'Your rating 5.0/5 for partner \"Geant\" was sent to admin. Your comment: d', 1, '2026-03-04 19:31:21'),
(10, 3, NULL, 3, 'CASHBACK_REWARD', 'Reward received', 'Admin granted you +5.00 DT for partner \"Geant\". Note: Bonus fidelite', 1, '2026-03-04 19:33:26'),
(11, NULL, 'ROLE_ADMIN', 3, 'CASHBACK_REWARD', 'Cashback reward granted', 'Admin granted +5.00 DT to user #3 for partner \"Geant\". Note: Bonus fidelite', 1, '2026-03-04 19:33:26'),
(12, NULL, 'ROLE_ADMIN', 3, 'CASHBACK_RATING', 'New cashback rating submitted', 'User #3 rated partner \"Geant\" with 5.0/5.', 1, '2026-03-04 20:04:55'),
(13, 3, NULL, 3, 'CASHBACK_RATING', 'Rating sent', 'Your rating 5.0/5 for partner \"Geant\" was sent to admin.', 1, '2026-03-04 20:04:55'),
(14, 10, NULL, 10, 'CREDIT_ADDED', '✅ Demande de crédit soumise', 'Votre demande de crédit Education (500 TND) a été soumise avec succès. Référence : #2.', 1, '2026-03-05 10:50:24'),
(15, NULL, 'ROLE_ADMIN', 10, 'CREDIT_ADDED', 'Nouvelle demande de crédit', 'Brahim Labidi a soumis une demande de crédit Education pour 500 TND. Référence : #2.', 1, '2026-03-05 10:50:24'),
(16, 9, NULL, 9, 'COMPTE_DECLINED', 'Bank account request declined', 'Your bank account request for CB-052 has been declined.', 0, '2026-04-01 19:04:32'),
(17, NULL, 'ROLE_ADMIN', 10, 'USER_SIGNUP_PENDING', 'New user pending approval', 'User saidane eya (saidaneeya3@gmail.com) created an account and is waiting for admin review.', 1, '2026-03-05 19:58:32'),
(18, 10, NULL, 10, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 1, '2026-03-05 20:00:21'),
(19, 10, NULL, 10, 'CREDIT_ADDED', '✅ Demande de crédit soumise', 'Votre demande de crédit Hypotheque (100000 TND) a été soumise avec succès. Référence : #3.', 1, '2026-03-05 21:02:00'),
(20, NULL, 'ROLE_ADMIN', 10, 'CREDIT_ADDED', 'Nouvelle demande de crédit', 'saidane eya a soumis une demande de crédit Hypotheque pour 100000 TND. Référence : #3.', 1, '2026-03-05 21:02:00'),
(21, 10, NULL, 10, 'CREDIT_ADDED', '✅ Demande de crédit soumise', 'Votre demande de crédit Professionnel (70000 TND) a été soumise avec succès. Référence : #4.', 1, '2026-03-05 23:47:07'),
(22, NULL, 'ROLE_ADMIN', 10, 'CREDIT_ADDED', 'Nouvelle demande de crédit', 'saidane eya a soumis une demande de crédit Professionnel pour 70000 TND. Référence : #4.', 1, '2026-03-05 23:47:07'),
(23, 10, NULL, 10, 'CREDIT_UPDATED', '📝 Crédit mis à jour', 'Votre dossier de crédit Professionnel (référence #4) a été mis à jour avec succès.', 1, '2026-03-06 10:30:06'),
(24, NULL, 'ROLE_ADMIN', 10, 'CREDIT_UPDATED', 'Crédit modifié', 'saidane eya a modifié son dossier de crédit #4 (Professionnel, 70000 TND).', 1, '2026-03-06 10:30:06'),
(25, 10, NULL, 10, 'CREDIT_UPDATED', '📝 Crédit mis à jour', 'Votre dossier de crédit Professionnel (référence #4) a été mis à jour avec succès.', 1, '2026-03-06 11:10:17'),
(26, NULL, 'ROLE_ADMIN', 10, 'CREDIT_UPDATED', 'Crédit modifié', 'saidane eya a modifié son dossier de crédit #4 (Professionnel, 60000 TND).', 1, '2026-03-06 11:10:17'),
(27, 10, NULL, 10, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #4 (Professionnel) a ete mis a jour. Montant: 60000.00 DT. Statut: Accepte.', 0, '2026-04-06 22:14:11'),
(28, NULL, 'ROLE_ADMIN', 10, 'CREDIT_UPDATE_ADMIN', 'Credit mis a jour par admin', 'Le credit #4 (Professionnel) de l\'utilisateur #10 a ete modifie par un admin. Montant: 60000.00 DT. Statut: Accepte.', 1, '2026-04-06 22:14:15'),
(29, 10, NULL, 10, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #209 (Titre vehicule) a ete enregistree pour le credit #4 (Professionnel).', 0, '2026-04-06 22:33:20'),
(30, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_CREATE_ADMIN', 'Garantie creee par admin', 'La garantie #209 (Titre vehicule) pour le credit #4 a ete creee par un admin. Utilisateur: #10.', 1, '2026-04-06 22:33:22'),
(31, 10, NULL, 10, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #210 (Titre vehicule) a ete enregistree pour le credit #4 (Professionnel).', 0, '2026-04-06 22:33:24'),
(32, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_CREATE_ADMIN', 'Garantie creee par admin', 'La garantie #210 (Titre vehicule) pour le credit #4 a ete creee par un admin. Utilisateur: #10.', 1, '2026-04-06 22:33:26'),
(33, 10, NULL, 10, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #211 (Titre vehicule) a ete enregistree pour le credit #4 (Professionnel).', 0, '2026-04-06 22:33:28'),
(34, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_CREATE_ADMIN', 'Garantie creee par admin', 'La garantie #211 (Titre vehicule) pour le credit #4 a ete creee par un admin. Utilisateur: #10.', 1, '2026-04-06 22:33:29'),
(35, 10, NULL, 10, 'GARANTIE_DELETE', 'Garantie supprimee', 'La garantie #3 (Titre vehicule) liee au credit #4 (Professionnel) a ete supprimee.', 0, '2026-04-06 23:06:25'),
(36, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_DELETE_ADMIN', 'Garantie supprimee par admin', 'La garantie #3 (Titre vehicule) liee au credit #4 a ete supprimee par un admin. Utilisateur: #10.', 1, '2026-04-06 23:06:27'),
(37, 10, NULL, 10, 'GARANTIE_DELETE', 'Garantie supprimee', 'La garantie #4 (Titre vehicule) liee au credit #4 (Professionnel) a ete supprimee.', 0, '2026-04-06 23:06:28'),
(38, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_DELETE_ADMIN', 'Garantie supprimee par admin', 'La garantie #4 (Titre vehicule) liee au credit #4 a ete supprimee par un admin. Utilisateur: #10.', 1, '2026-04-06 23:06:30'),
(39, 10, NULL, 10, 'GARANTIE_DELETE', 'Garantie supprimee', 'La garantie #5 (Titre vehicule) liee au credit #4 (Professionnel) a ete supprimee.', 0, '2026-04-06 23:06:31'),
(40, NULL, 'ROLE_ADMIN', 10, 'GARANTIE_DELETE_ADMIN', 'Garantie supprimee par admin', 'La garantie #5 (Titre vehicule) liee au credit #4 a ete supprimee par un admin. Utilisateur: #10.', 1, '2026-04-06 23:06:32'),
(41, 10, NULL, 10, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #218 (Titre vehicule) a ete enregistree pour le credit #4 (Professionnel).', 0, '2026-04-07 00:08:34'),
(42, NULL, 'ROLE_ADMIN', 10, 'CASHBACK_SUBMITTED', 'New cashback submitted', 'User #10 submitted a cashback request at partner \"hiugyug\" for purchase amount 1200.00 DT.', 1, '2026-04-07 10:40:23'),
(43, 10, NULL, 10, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #4 (Professionnel) a ete mis a jour. Montant: 60000.00 DT. Statut: Accepte.', 0, '2026-04-07 13:10:52'),
(44, 10, NULL, 10, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #4 (Professionnel) a ete mis a jour. Montant: 60000.00 DT. Statut: Accepte.', 0, '2026-04-07 13:15:53'),
(45, 10, NULL, 10, 'CREDIT_CREATE', 'Credit enregistre', 'Votre credit #5 (Immobilier) a ete enregistre. Montant: 100000.00 DT. Statut: En attente.', 0, '2026-04-07 13:16:28'),
(46, 10, NULL, 10, 'ACCOUNT_STATUS', 'Account banned', 'Your account was banned by admin.', 0, '2026-04-07 13:17:08'),
(47, 10, NULL, 10, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #5 (Immobilier) a ete mis a jour. Montant: 100000.00 DT. Statut: En cours.', 0, '2026-04-07 13:34:14'),
(48, 10, NULL, 10, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 0, '2026-04-07 14:21:42'),
(49, 9, NULL, 9, 'CREDIT_CREATE', 'Credit enregistre', 'Votre credit #6 (Immobilier) a ete enregistre. Montant: 14200.00 DT. Statut: En attente.', 0, '2026-04-07 21:13:35'),
(50, 9, NULL, 9, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #6 (Etudes) a ete mis a jour. Montant: 14200.00 DT. Statut: En attente.', 0, '2026-04-07 21:13:57'),
(51, 9, NULL, 9, 'CREDIT_CREATE', 'Credit enregistre', 'Votre credit #7 (Personnel) a ete enregistre. Montant: 600.00 DT. Statut: En attente.', 0, '2026-04-09 13:21:00'),
(52, 9, NULL, 9, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #7 (Consommation) a ete mis a jour. Montant: 600.00 DT. Statut: En attente.', 0, '2026-04-09 13:22:17'),
(53, 9, NULL, 9, 'CREDIT_CREATE', 'Credit enregistre', 'Votre credit #8 (Etudes) a ete enregistre. Montant: 500.00 DT. Statut: En attente.', 0, '2026-04-09 13:44:17'),
(54, 9, NULL, 9, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #8 (Immobilier) a ete mis a jour. Montant: 500.00 DT. Statut: En attente.', 0, '2026-04-09 13:44:39'),
(55, 9, NULL, 9, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #8 (Travaux) a ete mis a jour. Montant: 500.00 DT. Statut: En attente.', 0, '2026-04-09 14:29:00'),
(56, 9, NULL, 9, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #364 (Garantie bancaire) a ete enregistree pour le credit #8 (Travaux).', 0, '2026-04-09 14:29:40'),
(57, 9, NULL, 9, 'CREDIT_CREATE', 'Credit enregistre', 'Votre credit #9 (Professionnel) a ete enregistre. Montant: 750.00 DT. Statut: En attente.', 0, '2026-04-09 14:39:19'),
(58, 9, NULL, 9, 'CREDIT_UPDATE', 'Credit mis a jour', 'Votre credit #7 (Consommation) a ete mis a jour. Montant: 9000.00 DT. Statut: En attente.', 0, '2026-04-09 14:39:44'),
(59, 9, NULL, 9, 'CREDIT_DELETE', 'Credit supprime', 'Le credit #8 (Travaux) a ete supprime. Montant: 500.00 DT.', 0, '2026-04-09 14:39:53'),
(60, 9, NULL, 9, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #368 (Nantissement) a ete enregistree pour le credit #9 (Professionnel).', 0, '2026-04-09 14:40:25'),
(61, 9, NULL, 9, 'GARANTIE_CREATE', 'Garantie enregistree', 'Votre garantie #369 (Garantie bancaire) a ete enregistree pour le credit #7 (Consommation).', 0, '2026-04-09 14:41:20'),
(62, 11, NULL, 11, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 0, '2026-04-09 16:25:39'),
(63, 11, NULL, 11, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 0, '2026-04-09 16:30:45'),
(64, 12, NULL, 12, 'ACCOUNT_STATUS', 'Account approved', 'Your account is now ACTIVE and ready to use.', 0, '2026-04-11 11:36:57'),
(65, 13, NULL, 13, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Votre compte Professionnel (N° CB-952) a été accepté et est maintenant actif.', 0, '2026-04-11 15:09:19'),
(66, 13, NULL, 13, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Votre compte Épargne (N° CB-011) a été accepté et est maintenant actif.', 0, '2026-04-11 15:10:57'),
(67, 13, NULL, 13, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Votre demande de compte Professionnel (N° CB-010) a été refusée et supprimée.', 0, '2026-04-11 15:11:08'),
(68, NULL, 'ROLE_ADMIN', 13, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Mouhamed Labidi a soumis une demande de création de compte Professionnel (N° CB-500). En attente de validation.', 0, '2026-04-12 08:01:08'),
(69, 13, NULL, 13, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Votre compte Professionnel (N° CB-500) a été accepté et est maintenant actif.', 0, '2026-04-12 08:07:23'),
(70, NULL, 'ROLE_ADMIN', 12, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client rahma labidi a soumis une demande de création de compte Courant (N° CB-205). En attente de validation.', 0, '2026-04-12 08:45:14'),
(71, 13, NULL, 13, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CB-500.', 0, '2026-04-12 15:37:20'),
(72, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Professionnel (N° CB-987). En attente de validation.', 0, '2026-04-14 19:47:31'),
(73, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-14 21:30:18'),
(74, 12, NULL, 12, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-14 21:30:40'),
(75, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-205). En attente de validation.', 0, '2026-04-14 21:50:56'),
(76, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-14 21:51:56'),
(77, 9, NULL, 9, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CB-102.', 0, '2026-04-15 09:36:16'),
(78, 9, NULL, 9, 'VAULT_GOAL_EXTEND', 'Objectif prolonge', 'La date objectif du coffre test2 a ete prolongee de +3 mois jusqu au 2026-07-13.', 0, '2026-04-15 21:18:27'),
(79, 9, NULL, 9, 'VAULT_GOAL_TRANSFER', 'Objectif transfere', '950.00 DT du coffre koko ont ete transferes vers le compte CB-100.', 0, '2026-04-15 21:18:53'),
(80, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-025). En attente de validation.', 0, '2026-04-15 21:30:23'),
(81, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-15 21:31:07'),
(82, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-257). En attente de validation.', 0, '2026-04-15 21:34:23'),
(83, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-16 11:04:04'),
(84, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Épargne (N° CB-851). En attente de validation.', 0, '2026-04-16 11:08:42'),
(85, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-16 13:31:21'),
(86, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-123). En attente de validation.', 0, '2026-04-16 13:33:26'),
(87, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-17 12:20:43'),
(88, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-123). En attente de validation.', 0, '2026-04-17 13:30:58'),
(91, 15, NULL, 15, 'VAULT_GOAL_TRANSFER', 'Objectif transfere', '7000.00 DT du coffre Urgence ont ete transferes vers le compte CPT-103.', 0, '2026-04-18 19:23:46'),
(93, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-230). En attente de validation.', 0, '2026-04-18 19:38:05'),
(94, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-18 19:38:56'),
(95, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-18 19:39:03'),
(100, 9, NULL, 9, 'VAULT_GOAL_TRANSFER', 'Objectif transfere', '1120.00 DT du coffre Fonds de loisir ont ete transferes vers le compte CB-102.', 0, '2026-04-19 09:39:21'),
(101, 9, NULL, 9, 'VAULT_GOAL_EXTEND', 'Objectif prolonge', 'La date objectif du coffre modifier12 a ete prolongee de +6 mois jusqu au 2026-10-18.', 0, '2026-04-19 09:39:53'),
(102, 15, NULL, 15, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CPT-103.', 0, '2026-04-19 14:56:37'),
(103, 15, NULL, 15, 'VAULT_GOAL_TRANSFER', 'Objectif transfere', '10000.00 DT du coffre Études ont ete transferes vers le compte CPT-101.', 0, '2026-04-19 14:59:14'),
(104, 15, NULL, 15, 'VAULT_GOAL_EXTEND', 'Objectif prolonge', 'La date objectif du coffre Maison a ete prolongee de +3 mois jusqu au 2026-07-15.', 0, '2026-04-19 14:59:33'),
(105, NULL, 'ROLE_ADMIN', 15, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client AbdeAziz Labidi a soumis une demande de création de compte Épargne (N° CB-205). En attente de validation.', 0, '2026-04-19 19:46:28'),
(106, 15, NULL, 15, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-19 19:46:59'),
(107, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-204). En attente de validation.', 0, '2026-04-19 19:52:43'),
(108, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-19 19:53:06'),
(110, NULL, 'ROLE_ADMIN', 16, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Rimene Boussidi a soumis une demande de création de compte Professionnel (N° CB-120). En attente de validation.', 0, '2026-04-20 08:29:23'),
(111, 16, NULL, 16, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-20 08:32:14'),
(112, NULL, 'ROLE_ADMIN', 16, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Rimene Boussidi a soumis une demande de création de compte Courant (N° CB-300). En attente de validation.', 0, '2026-04-20 18:31:21'),
(113, 16, NULL, 16, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-20 18:42:28'),
(114, NULL, 'ROLE_ADMIN', 16, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Rimene Boussidi a soumis une demande de création de compte Courant (N° CB-450). En attente de validation.', 0, '2026-04-20 19:40:07'),
(115, NULL, 'ROLE_ADMIN', 13, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Mouhamed Labidi a soumis une demande de création de compte Professionnel (N° CB-320). En attente de validation.', 0, '2026-04-20 19:58:02'),
(116, 16, NULL, 16, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-20 19:59:26'),
(117, 13, NULL, 13, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-20 19:59:33'),
(118, NULL, 'ROLE_ADMIN', 9, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Labidi Bouthayna a soumis une demande de création de compte Courant (N° CB-220). En attente de validation.', 0, '2026-04-20 20:00:28'),
(119, 9, NULL, 9, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-20 20:01:59'),
(120, 16, NULL, 16, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CB-650.', 0, '2026-04-20 21:03:08'),
(121, 9, NULL, 9, 'AI_ANALYSIS', 'Analyse IA de votre compte', '### Analyse de votre profil financier par NEXORA AI\n\n**Segment :** Epargnant\n**Score de risque :** 68/100\n\n#### Analyse détaillée\nScore 68/100. Le comportement montre une epargne reguliere avec 52.0% de progression sur les coffres. Segment ML: Epargnants actifs.\n\n#### Alertes de sécurité & fraude\n- **Activite inhabituelle detectee :** Labidi Bouthayna presente 2 transaction(s) anormale(s) a verifier rapidement.\n\n#### Recommandations personnalisées\n- Renforcer les virements automatiques vers les coffres les plus performants.\n- Suggérer des objectifs d epargne progressifs pour accelerer la croissance.\n\nCes recommandations sont générées automatiquement pour vous aider à mieux gérer votre situation financière.', 0, '2026-04-21 14:08:32'),
(122, NULL, 'ROLE_ADMIN', 17, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Adam Guedich a soumis une demande de création de compte Professionnel (N° CB-003). En attente de validation.', 0, '2026-04-21 16:08:17'),
(123, 17, NULL, 17, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-21 16:09:20'),
(124, 17, NULL, 17, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CB-003.', 0, '2026-04-21 16:35:40'),
(125, 17, NULL, 17, 'AI_ANALYSIS', 'Analyse IA de votre compte', '### Analyse de votre profil financier par NEXORA AI\n\n**Segment :** A risque\n**Score de risque :** 8/100\n\n#### Analyse détaillée\nScore 8/100. 0 compte(s) bloque(s) et 6 anomalie(s) demandent une verification prioritaire. Segment ML: Epargnants actifs.\n\n#### Alertes de sécurité & fraude\n- **Activite inhabituelle detectee :** Adam Guedich presente 6 transaction(s) anormale(s) a verifier rapidement.\n\n#### Recommandations personnalisées\n- Verifier les operations recentes et securiser les comptes sensibles.\n- Analyser les transactions atypiques avant toute nouvelle operation critique.\n\nCes recommandations sont générées automatiquement pour vous aider à mieux gérer votre situation financière.', 0, '2026-04-21 16:45:29'),
(126, NULL, 'ROLE_ADMIN', 16, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Rimene Boussidi a soumis une demande de création de compte Épargne (N° CB-012). En attente de validation.', 0, '2026-04-22 21:25:07'),
(127, NULL, 'ROLE_ADMIN', 16, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Rimene Boussidi a soumis une demande de création de compte Courant (N° CB-230). En attente de validation.', 0, '2026-04-22 22:48:08'),
(128, 16, NULL, 16, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-22 22:51:57'),
(129, 16, NULL, 16, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-22 22:52:05'),
(130, NULL, 'ROLE_ADMIN', 15, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client AbdeAziz Labidi a soumis une demande de création de compte Courant (N° CB-321). En attente de validation.', 0, '2026-04-23 04:51:30'),
(131, NULL, 'ROLE_ADMIN', 15, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client AbdeAziz Labidi a soumis une demande de création de compte Professionnel (N° CB-311). En attente de validation.', 0, '2026-04-23 05:08:16'),
(132, 15, NULL, 15, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-23 05:09:51'),
(133, 15, NULL, 15, 'ACCOUNT_VALIDATION', 'Compte bancaire refusé', 'Le compte est refusé. Veuillez contacter l’administration.', 0, '2026-04-23 05:09:59'),
(134, 15, NULL, 15, 'WHEEL_BONUS', 'Bonus roue credite', 'Le bonus de 50 DT de la roue a ete ajoute au compte CB-311.', 0, '2026-04-23 05:47:57'),
(135, 15, NULL, 15, 'VAULT_GOAL_TRANSFER', 'Objectif transfere', '5000.00 DT du coffre Voyage ont ete transferes vers le compte CB-311.', 0, '2026-04-23 05:49:38'),
(136, 15, NULL, 15, 'VAULT_GOAL_EXTEND', 'Objectif prolonge', 'La date objectif du coffre Voyage a ete prolongee de +3 mois jusqu au 2026-05-05.', 0, '2026-04-23 05:51:18'),
(137, 9, NULL, 9, 'AI_ANALYSIS', 'Analyse IA de votre compte', '### Analyse de votre profil financier par NEXORA AI\n\n**Segment :** A risque\n**Score de risque :** 24/100\n\n#### Analyse détaillée\nScore 24/100. 1 compte(s) bloque(s) et 2 anomalie(s) demandent une verification prioritaire. Segment ML: Surveillance risque.\n\n#### Alertes de sécurité & fraude\n- **Activite inhabituelle detectee :** Labidi Bouthayna presente 2 transaction(s) anormale(s) a verifier rapidement.\n- **Compte bloque a surveiller :** Labidi Bouthayna a 1 compte(s) bloque(s), ce qui fragilise sa stabilite globale.\n\n#### Recommandations personnalisées\n- Verifier les operations recentes et securiser les comptes sensibles.\n- Contacter le client pour clarifier la situation des comptes bloques.\n- Analyser les transactions atypiques avant toute nouvelle operation critique.\n\nCes recommandations sont générées automatiquement pour vous aider à mieux gérer votre situation financière.', 0, '2026-04-23 10:21:26'),
(138, NULL, 'ROLE_ADMIN', 18, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Kodra Fakraoui a soumis une demande de création de compte Courant (N° CB-620). En attente de validation.', 0, '2026-04-23 11:04:44'),
(139, NULL, 'ROLE_ADMIN', 18, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Kodra Fakraoui a soumis une demande de création de compte Professionnel (N° CB-840). En attente de validation.', 0, '2026-04-23 11:05:38'),
(140, 18, NULL, 18, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-23 11:06:05'),
(141, 18, NULL, 18, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-23 11:06:13'),
(142, NULL, 'ROLE_ADMIN', 17, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Adam Guedich a soumis une demande de création de compte Courant (N° CB-150). En attente de validation.', 0, '2026-04-30 09:19:11'),
(143, 17, NULL, 17, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-04-30 10:41:10'),
(144, NULL, 'ROLE_ADMIN', 17, 'ACCOUNT_PENDING', 'Nouvelle demande de compte bancaire', 'Le client Adam Guedich a soumis une demande de création de compte Professionnel (N° CB-212). En attente de validation.', 0, '2026-05-01 20:15:46'),
(145, 17, NULL, 17, 'STRATEGY_ACTIVATED', '✅ Stratégie d\'épargne activée', 'La stratégie AGRESSIVE (78.00 DT/mois) a été activée pour votre coffre. Le prochain transfert aura lieu le 02/06/2026.', 0, '2026-05-01 22:13:00'),
(146, 17, NULL, 17, 'ACCOUNT_VALIDATION', 'Compte bancaire activé', 'Bienvenue à Nexora Bank, ce compte est maintenant activé et visible avec toutes ses informations.', 0, '2026-05-01 22:34:19'),
(147, 15, NULL, 15, 'STRATEGY_ACTIVATED', '✅ Stratégie d\'épargne activée', 'La stratégie MODEREE (23.00 DT/mois) a été activée pour votre coffre. Le prochain transfert aura lieu le 02/06/2026.', 0, '2026-05-02 07:29:51'),
(148, 17, NULL, 17, 'STRATEGY_ACTIVATED', 'Strategie d epargne activee', 'La strategie AGRESSIVE (71.00 DT/mois) a ete activee pour votre coffre. Le prochain transfert aura lieu le 02/06/2026.', 0, '2026-05-02 08:11:36'),
(149, 17, NULL, 17, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '71.00 DT ont ete transferes vers votre coffre (transfert 1/24). Prochain transfert : 02/06/2026.', 0, '2026-05-02 08:11:36'),
(150, 17, NULL, 17, 'STRATEGY_ACTIVATED', 'Strategie d epargne activee', 'La strategie AGRESSIVE (30.00 DT/mois) a ete activee pour votre coffre. Le prochain transfert aura lieu le 02/06/2026.', 0, '2026-05-02 08:12:52'),
(151, 17, NULL, 17, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '30.00 DT ont ete transferes vers votre coffre (transfert 1/24). Prochain transfert : 02/06/2026.', 0, '2026-05-02 08:12:52'),
(152, 15, NULL, 15, 'STRATEGY_ACTIVATED', 'Strategie d epargne activee', 'La strategie DOUCE (10.00 DT/mois) a ete activee pour votre coffre. Le prochain transfert aura lieu le 02/06/2026.', 0, '2026-05-02 08:38:16'),
(153, 15, NULL, 15, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '10.00 DT ont ete transferes vers votre coffre (transfert 1/60). Prochain transfert : 02/06/2026.', 0, '2026-05-02 08:45:39'),
(154, 15, NULL, 15, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '10.00 DT ont ete transferes vers votre coffre (transfert 2/60). Prochain transfert : 02/06/2026.', 0, '2026-05-02 09:19:23'),
(155, 15, NULL, 15, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '10.00 DT ont ete transferes vers votre coffre (transfert 3/60). Prochain transfert : 02/06/2026.', 0, '2026-05-02 09:35:43'),
(156, 15, NULL, 15, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '10.00 DT ont ete transferes vers votre coffre (transfert 1/60). Prochain transfert : 02/06/2026.', 0, '2026-05-02 09:41:34'),
(157, 15, NULL, 15, 'STRATEGY_TRANSFER', 'Transfert automatique effectue', '10.00 DT ont ete transferes vers votre coffre (transfert 2/60). Prochain transfert : 02/06/2026.', 0, '2026-05-02 09:46:47');

-- --------------------------------------------------------

--
-- Structure de la table `partenaire`
--

CREATE TABLE `partenaire` (
  `idPartenaire` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `categorie` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `ville` varchar(120) DEFAULT NULL,
  `tauxCashback` double NOT NULL,
  `tauxCashbackMax` double NOT NULL,
  `plafondMensuel` double NOT NULL,
  `conditions` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Actif',
  `rating` double NOT NULL DEFAULT 4
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `partenaire`
--

INSERT INTO `partenaire` (`idPartenaire`, `nom`, `categorie`, `description`, `ville`, `tauxCashback`, `tauxCashbackMax`, `plafondMensuel`, `conditions`, `status`, `rating`) VALUES
(1, 'Geant', 'Mode et Vetements', 'dezadzadza', NULL, 3, 12, 200, 'FZEFEZGQREQGVRE', 'Premium', 4),
(2, 'carrefour', 'Grande Distribution', 'sdfghjk', NULL, 5, 10, 500, 'sdfghj', 'Actif', 4),
(3, 'eya', 'dfz', 'ERGV', NULL, 2000, 1300, 600, '', 'Actif', 4),
(4, 'mouhaned', 'jaune', 'aaa', NULL, 500, 1400, 5, '', 'Inactif', 2);

-- --------------------------------------------------------

--
-- Structure de la table `qr_login_sessions`
--

CREATE TABLE `qr_login_sessions` (
  `idQrLoginSession` int(11) NOT NULL,
  `session_token` char(64) NOT NULL,
  `browser_session_id` varchar(190) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `requested_ip` varchar(64) DEFAULT NULL,
  `requested_user_agent` varchar(255) DEFAULT NULL,
  `approved_user_id` int(11) DEFAULT NULL,
  `approved_device_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `approved_at` datetime DEFAULT NULL,
  `consumed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `qr_trust_sessions`
--

CREATE TABLE `qr_trust_sessions` (
  `idQrTrustSession` int(11) NOT NULL,
  `session_token` char(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `trusted_device_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reclamation`
--

CREATE TABLE `reclamation` (
  `idReclamation` int(11) NOT NULL,
  `dateReclamation` date NOT NULL,
  `typeReclamation` varchar(50) NOT NULL,
  `description` varchar(150) NOT NULL,
  `sentiment` varchar(20) NOT NULL DEFAULT 'neutral',
  `status` varchar(30) NOT NULL,
  `idUser` int(11) DEFAULT NULL,
  `idTransaction` int(11) DEFAULT NULL,
  `is_inappropriate` tinyint(1) NOT NULL DEFAULT 0,
  `is_blurred` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reclamation`
--

INSERT INTO `reclamation` (`idReclamation`, `dateReclamation`, `typeReclamation`, `description`, `sentiment`, `status`, `idUser`, `idTransaction`, `is_inappropriate`, `is_blurred`) VALUES
(2, '2026-04-09', 'Probleme de connexion au compte', 'aaaaaaaaaaaaaaaaaaa123', 'neutral', 'Ferme', 9, NULL, 0, 0),
(4, '2026-04-09', 'Erreur de transaction', '124578963', 'neutral', 'Attende', 1, NULL, 0, 0),
(5, '2026-04-09', 'Virement non recu', '7842hhhhhhhhhhhhh', 'neutral', 'Valide', 1, NULL, 0, 0),
(6, '2026-04-09', 'Virement non recu', 'aaaaaaaaaaaaaaaaaaaaaavrai', 'neutral', 'Valide', 9, NULL, 0, 0),
(8, '2026-04-09', 'Virement non recu', 'mmmmmmmmmmmmmmmmmm', 'neutral', 'Valide', 9, NULL, 0, 0),
(10, '2026-04-09', '', '', 'neutral', 'En attente', 9, NULL, 0, 0),
(11, '2026-04-09', 'Virement non recu', 'jjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj', 'neutral', 'En attente', 9, NULL, 0, 0),
(12, '2026-04-09', 'Probleme de connexion au compte', 'kokokoooooooo', 'neutral', 'En attente', 9, NULL, 0, 0),
(13, '2026-04-14', 'Echec de montant', 'débile', 'neutral', 'En attente', 9, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `roue_fortune_points`
--

CREATE TABLE `roue_fortune_points` (
  `idUser` int(11) NOT NULL,
  `totalPoints` int(11) NOT NULL DEFAULT 0,
  `dernierTour` varchar(10) DEFAULT NULL,
  `dernierMois` varchar(7) DEFAULT NULL,
  `pointsGagnes` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `roue_fortune_points`
--

INSERT INTO `roue_fortune_points` (`idUser`, `totalPoints`, `dernierTour`, `dernierMois`, `pointsGagnes`) VALUES
(9, 10, '2026-04-19', '2026-04', 10),
(13, 93, '2026-03-12', '2026-03', 6),
(15, 0, '2026-04-23', '2026-04', 15),
(16, 0, '2026-04-20', '2026-04', 20),
(17, 0, '2026-04-21', '2026-04', 10);

-- --------------------------------------------------------

--
-- Structure de la table `strategiesproposees`
--

CREATE TABLE `strategiesproposees` (
  `idStrategie` int(11) NOT NULL,
  `idCoffre` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `typeStrategie` varchar(20) DEFAULT NULL,
  `montantEpargnePropose` decimal(10,2) DEFAULT NULL,
  `scoreRisque` decimal(5,2) DEFAULT NULL,
  `probabiliteSucces` decimal(5,2) DEFAULT NULL,
  `estAcceptee` tinyint(1) DEFAULT 0,
  `statut` varchar(20) DEFAULT 'PROPOSEE',
  `dateProposition` date DEFAULT NULL,
  `dateProchaineExecution` date DEFAULT NULL,
  `dernierExecution` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `strategies_proposees`
--

CREATE TABLE `strategies_proposees` (
  `idStrategie` int(11) NOT NULL,
  `idCoffre` int(11) NOT NULL,
  `idCompte` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `typeStrategie` varchar(20) NOT NULL COMMENT 'DOUCE | MODEREE | AGRESSIVE',
  `montantMensuel` decimal(12,2) NOT NULL,
  `dureeEstimee` int(11) NOT NULL COMMENT 'en mois',
  `tauxSucces` decimal(5,2) NOT NULL COMMENT 'pourcentage 0-100',
  `niveauRisque` varchar(20) NOT NULL COMMENT 'Très faible | Faible | Modéré | Élevé',
  `statut` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active | suspendue | terminee',
  `safetyCheckSeuil` decimal(12,2) NOT NULL DEFAULT 50.00 COMMENT 'solde minimum avant suspension',
  `dateActivation` datetime NOT NULL,
  `dateProchaineExecution` date DEFAULT NULL,
  `nombreTransferts` int(11) NOT NULL DEFAULT 0,
  `montantTotalTransfere` decimal(12,2) NOT NULL DEFAULT 0.00,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stratégies d épargne générées par le ML Random Forest';

--
-- Déchargement des données de la table `strategies_proposees`
--

INSERT INTO `strategies_proposees` (`idStrategie`, `idCoffre`, `idCompte`, `idUser`, `typeStrategie`, `montantMensuel`, `dureeEstimee`, `tauxSucces`, `niveauRisque`, `statut`, `safetyCheckSeuil`, `dateActivation`, `dateProchaineExecution`, `nombreTransferts`, `montantTotalTransfere`, `createdAt`, `updatedAt`) VALUES
(5, 214, 103, 15, 'DOUCE', 10.00, 60, 96.80, 'Tres faible', 'active', 400.30, '2026-04-02 10:38:16', '2026-06-02', 2, 20.00, '2026-05-02 08:38:16', '2026-05-02 09:46:47');

-- --------------------------------------------------------

--
-- Structure de la table `superplus_notifications`
--

CREATE TABLE `superplus_notifications` (
  `idUser` int(11) NOT NULL,
  `moisAffiche` varchar(7) NOT NULL,
  `dateCreation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `superplus_notifications`
--

INSERT INTO `superplus_notifications` (`idUser`, `moisAffiche`, `dateCreation`) VALUES
(9, '2026-04', '2026-04-19 10:37:23'),
(15, '2026-03', '2026-03-19 16:31:04'),
(15, '2026-04', '2026-04-23 06:45:32'),
(16, '2026-03', '2026-03-20 22:07:44'),
(16, '2026-04', '2026-04-20 22:09:48'),
(17, '2026-04', '2026-04-21 17:40:52');

-- --------------------------------------------------------

--
-- Structure de la table `support_messages`
--

CREATE TABLE `support_messages` (
  `idSupportMessage` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `recipient_user_id` int(11) NOT NULL,
  `sender_role` varchar(20) NOT NULL,
  `recipient_role` varchar(20) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `idTransaction` int(11) NOT NULL,
  `idCompte` int(11) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `categorie` varchar(50) NOT NULL,
  `dateTransaction` date DEFAULT NULL,
  `montant` decimal(12,3) DEFAULT NULL,
  `typeTransaction` varchar(30) NOT NULL,
  `soldeApres` decimal(12,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `montantPaye` decimal(12,3) DEFAULT NULL,
  `idCompteDestinataire` varchar(255) DEFAULT NULL,
  `nomDestinataire` varchar(255) DEFAULT NULL,
  `emailDestinataire` varchar(255) DEFAULT NULL,
  `original_amount` decimal(12,3) DEFAULT NULL,
  `original_currency` varchar(3) DEFAULT 'TND',
  `exchange_rate` decimal(10,6) DEFAULT NULL,
  `conversion_fee` decimal(12,3) DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`idTransaction`, `idCompte`, `idUser`, `categorie`, `dateTransaction`, `montant`, `typeTransaction`, `soldeApres`, `description`, `montantPaye`, `idCompteDestinataire`, `nomDestinataire`, `emailDestinataire`, `original_amount`, `original_currency`, `exchange_rate`, `conversion_fee`) VALUES
(5055, 143, 9, '', '2026-04-20', 0.000, 'DEPOT', NULL, 'ela', 0.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5056, 143, 9, '', '2026-04-20', 20.000, 'DEPOT', 1700.00, 'loulz', 20.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5057, 143, 9, '', '2026-04-20', 20.000, 'DEPOT', 1700.00, 'bouth', 20.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5058, 143, 9, '', '2026-04-20', 40.000, 'VIREMENT', 1720.00, 'vghhh', 40.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5059, 143, 9, '', '2026-04-14', 41.000, 'VIREMENT', 1721.00, 'bobo', 41.000, '1233456', NULL, NULL, 41.000, 'USD', NULL, 0.000),
(5060, 143, 9, '', '2026-04-09', 70.000, 'VIREMENT', 1750.00, 'dsedfh', 70.000, '1233456', NULL, NULL, 70.000, 'TND', NULL, 0.000),
(5079, 102, 15, '', '2026-04-20', 500.000, 'DEPOT', 7600.00, 'test 1 Roue', 500.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5080, 102, 15, '', '2026-03-15', 1050.000, 'DEPOT', 6050.00, 'Test 2  superlus', 1050.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5081, 102, 15, '', '2026-02-23', 1100.000, 'DEPOT', 6100.00, 'test 3 superplus', 1100.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5082, 103, 15, '', '2026-01-23', 1000.000, 'DEPOT', 10050.00, 'test 4 superplus', 1000.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5083, 103, 15, 'Transport', '2026-04-23', 500.000, 'PAIEMENT', 8550.00, 'paiement', 0.000, NULL, 'salwa', 'salwa@gmail.com', NULL, 'TND', NULL, 0.000),
(5084, 103, 15, 'Shopping', '2026-04-21', 10.000, 'PAIEMENT', 8050.00, 'validation1', 1000.000, NULL, 'soso', 'isra@gmail.com', NULL, 'TND', NULL, 0.000),
(5085, 103, 15, '', '2026-04-20', 200.000, 'VIREMENT', 10730.00, 'test 3', 1680.000, '1233456', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5088, 103, 15, '', '2026-04-21', 320.000, 'VIREMENT', 8880.00, 'transfert un montanat vers le coffre', 320.000, 'CB-103', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5089, 103, 15, '', '2026-04-20', 210.000, 'RETRAIT', 8350.00, 'transfaert un Montanat cers un coffre virtuel', 210.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5090, 103, 15, 'Epargne', '2026-04-23', 554.000, 'DEBIT', 8006.00, 'Transfert surplus mensuel vers coffre #202', 554.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5091, 177, 15, 'Recompense', '2026-04-23', 50.000, 'CREDIT', 200.50, 'Bonus roue de fortune +50 DT', 50.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5092, 177, 15, 'Epargne', '2026-04-23', 500.000, 'CREDIT', 5200.50, 'Transfert depuis le coffre objectif #201', 5000.000, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5093, 180, 17, 'Epargne automatique', '2026-05-02', 71.000, 'VIREMENT', 1129.00, 'Transfert automatique strategie AGRESSIVE vers coffre #220', NULL, '180', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5094, 180, 17, 'Epargne automatique', '2026-05-02', 30.000, 'VIREMENT', 1099.00, 'Transfert automatique strategie AGRESSIVE vers coffre #220', NULL, '180', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5098, 103, 15, 'Epargne automatique', '2026-05-02', 10.000, 'VIREMENT', 7996.00, 'Transfert automatique strategie DOUCE vers coffre #214', NULL, '103', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5099, 103, 15, 'Epargne automatique', '2026-05-02', 10.000, 'VIREMENT', 7986.00, 'Transfert automatique strategie DOUCE vers coffre #214', NULL, '103', NULL, NULL, NULL, 'TND', NULL, 0.000),
(5100, 143, 9, 'Salaire', '2026-04-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5101, 143, 9, 'Salaire', '2026-03-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5102, 143, 9, 'Salaire', '2026-02-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5103, 143, 9, 'Salaire', '2026-01-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5104, 143, 9, 'Salaire', '2025-12-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5105, 143, 9, 'Salaire', '2025-11-25', 1200.000, 'DEPOT', NULL, 'Salaire mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5106, 143, 9, 'Loyer', '2026-04-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5107, 143, 9, 'Loyer', '2026-03-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5108, 143, 9, 'Loyer', '2026-02-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5109, 143, 9, 'Loyer', '2026-01-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5110, 143, 9, 'Loyer', '2025-12-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5111, 143, 9, 'Loyer', '2025-11-28', 500.000, 'DEBIT', NULL, 'Loyer mensuel', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5112, 143, 9, 'Alimentation', '2026-04-20', 180.000, 'DEBIT', NULL, 'Courses alimentaires', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5113, 143, 9, 'Alimentation', '2026-03-20', 200.000, 'DEBIT', NULL, 'Courses alimentaires', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5114, 143, 9, 'Alimentation', '2026-02-20', 190.000, 'DEBIT', NULL, 'Courses alimentaires', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5115, 143, 9, 'Transport', '2026-04-15', 80.000, 'DEBIT', NULL, 'Abonnement transport', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5116, 143, 9, 'Transport', '2026-03-15', 80.000, 'DEBIT', NULL, 'Abonnement transport', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5117, 143, 9, 'Transport', '2026-02-15', 80.000, 'DEBIT', NULL, 'Abonnement transport', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5118, 143, 9, 'Loisirs', '2026-04-10', 50.000, 'DEBIT', NULL, 'Sortie cinéma', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5119, 143, 9, 'Loisirs', '2026-03-10', 60.000, 'DEBIT', NULL, 'Restaurant', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5120, 142, 9, 'Salaire', '2026-02-01', 2200.000, 'DEPOT', 4500.00, 'Salaire février', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5121, 142, 9, 'Salaire', '2026-03-01', 2200.000, 'DEPOT', 6700.00, 'Salaire mars', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5122, 142, 9, 'Salaire', '2026-04-01', 2200.000, 'DEPOT', 8900.00, 'Salaire avril', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5123, 142, 9, 'Prime', '2026-04-20', 500.000, 'DEPOT', 9400.00, 'Prime trimestrielle', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5124, 142, 9, 'Loyer', '2026-02-05', 900.000, 'PAIEMENT', 3600.00, 'Loyer février', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5125, 142, 9, 'Alimentation', '2026-02-12', 450.000, 'PAIEMENT', 3150.00, 'Courses supermarché', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5126, 142, 9, 'Transport', '2026-02-18', 180.000, 'RETRAIT', 2970.00, 'Essence et parking', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5127, 142, 9, 'Abonnements', '2026-02-25', 120.000, 'PAIEMENT', 2850.00, 'Netflix, Spotify, Internet', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5128, 142, 9, 'Loyer', '2026-03-05', 900.000, 'PAIEMENT', 5800.00, 'Loyer mars', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5129, 142, 9, 'Alimentation', '2026-03-14', 420.000, 'PAIEMENT', 5380.00, 'Courses supermarché', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5130, 142, 9, 'Transport', '2026-03-20', 160.000, 'RETRAIT', 5220.00, 'Essence', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5131, 142, 9, 'Shopping', '2026-03-28', 250.000, 'PAIEMENT', 4970.00, 'Vêtements', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5132, 142, 9, 'Loyer', '2026-04-05', 900.000, 'PAIEMENT', 8000.00, 'Loyer avril', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5133, 142, 9, 'Alimentation', '2026-04-12', 480.000, 'PAIEMENT', 7520.00, 'Courses supermarché', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5134, 142, 9, 'Transport', '2026-04-18', 200.000, 'RETRAIT', 7320.00, 'Essence', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000),
(5135, 142, 9, 'Divertissement', '2026-04-24', 180.000, 'PAIEMENT', 7140.00, 'Restaurant + cinéma', NULL, NULL, NULL, NULL, NULL, 'TND', NULL, 0.000);

-- --------------------------------------------------------

--
-- Structure de la table `trusted_mobile_devices`
--

CREATE TABLE `trusted_mobile_devices` (
  `idTrustedDevice` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(140) NOT NULL,
  `device_name` varchar(140) NOT NULL DEFAULT 'Mobile device',
  `platform` varchar(40) DEFAULT NULL,
  `app_version` varchar(40) DEFAULT NULL,
  `trusted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `idUser` int(11) NOT NULL,
  `nom` varchar(80) NOT NULL,
  `prenom` varchar(80) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(30) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'ROLE_USER',
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_opened_from` varchar(180) NOT NULL DEFAULT 'Unknown device',
  `last_online_at` timestamp NULL DEFAULT NULL,
  `last_online_from` varchar(180) DEFAULT NULL,
  `biometric_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `profile_image_path` varchar(600) DEFAULT NULL,
  `account_opened_location` varchar(200) NOT NULL DEFAULT 'Unknown location',
  `account_opened_lat` decimal(10,7) DEFAULT NULL,
  `account_opened_lng` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`idUser`, `nom`, `prenom`, `email`, `telephone`, `role`, `status`, `password`, `created_at`, `updated_at`, `account_opened_from`, `last_online_at`, `last_online_from`, `biometric_enabled`, `profile_image_path`, `account_opened_location`, `account_opened_lat`, `account_opened_lng`) VALUES
(1, 'System', 'Admin', 'admin@nexora.com', '+21600000000', 'ROLE_ADMIN', 'ACTIVE', 'PBKDF2$210000$vilkFWvVrCUKaSHVKswdnA==$D6x8vFgqQ70O7fi+oBIRwcOKNu6nsnQUIrxElRpLkuc=', '2026-02-17 00:39:41', '2026-05-01 22:31:03', 'Unknown device', '2026-05-01 23:31:03', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Tunis, Tunis, Tunisia', 36.8064948, 10.1815316),
(2, 'karimmmmmm', 'naddari', 'nadderikarim@gmail.com', '92720527', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$X1tm/Lm5CBCtk1cicfSSVQ==$R+KTHlgoAeZrPrEUSMBcX80G1z57rJxWJPH7ssfg9dE=', '2026-02-17 00:40:45', '2026-03-01 19:25:12', 'Unknown device', '2026-03-01 19:25:12', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 1, NULL, 'Unknown location', NULL, NULL),
(3, 'Karim', 'Naddari 2', 'nooobnaab@gmail.com', '92 720 527', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$c9fv0oTzGgM7arOG00HJUQ==$bGzwOh1O3bez1Ck1WfOkEoDwmKVXj+aapnM3q9avgYA=', '2026-02-17 17:08:34', '2026-03-05 03:08:17', 'Unknown device', '2026-03-05 03:08:17', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 0, 'C:\\Users\\XPS\\.nexora-bank\\profile-images\\user_3_1772409358609.jpg', 'Tunis, Tunis, Tunisia', 36.8064948, 10.1815316),
(8, 'Labidi', 'Brahim', 'bouthaynalabidi78@gmail.com', '23095381', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$5dsdzVmAPnp56rQG8jySpQ==$MBvh/iLN4SGDxPYrIKa2CRQwytG7KczKKdcDB0zrge4=', '2026-02-19 17:09:01', '2026-04-08 11:33:50', 'user@DESKTOP-H7LO1H2 (Windows 10) (created by admin)', '2026-04-08 12:33:50', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 0, NULL, 'Nabeul, Nabeul, Tunisia', 36.7278251, 10.7097867),
(9, 'Bouthayna', 'Labidi', 'bouthynalabidi@gmail.com', '22210366', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$TeCAZ5bDhmTTO2pVSZIm1g==$IFJvvrNHuWiWRBprXhP40vmPRx7PXb3MxyiCvia4Cak=', '2026-02-19 17:01:49', '2026-05-02 09:54:13', 'user@DESKTOP-H7LO1H2 (Windows 10) (created by admin)', '2026-05-02 10:54:13', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Nabeul, Nabeul, Tunisia', 36.7278251, 10.7097867),
(10, 'eyaaaa', 'saidane', 'saidaneeya3@gmail.com', '953519', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$bqQ2BHYIZCAPF8cEAjti8g==$MyqbvUlYOR/wQvD40ytffynbp2eWT0ljlDZ5SfNQ5VY=', '2026-03-05 19:58:32', '2026-04-10 09:05:42', 'HP@DESKTOP-MPPK2VD (Windows 11)', '2026-04-10 10:05:42', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0)', 0, 'uploads/profile/profile_847e7a8fc22fa500bfae.jpg', 'Ben Arous, Ben Arous, Tunisia', 36.7435003, 10.2319757),
(12, 'labidi', 'rahma', 'rahmalabidi082@gmail.com', '21356210', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$SFKfCvq8DFVp3aXQ1U57hQ==$YjQlvq2u8kwzvl4WGhgld7hasEIJV8RHPOpi1R1uWxk=', '2026-04-11 11:35:15', '2026-04-12 08:33:57', 'Symfony admin', '2026-04-12 09:33:57', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(13, 'Labidi', 'Mouhamed', 'mouhamedlabidi@gmail.com', '28564320', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$rt1qnpjIh28dymhNa46rMQ==$FbrXZVD+gSShcFfxnLW+mmPl6UgKUx4kgTtpZ6FPs6Y=', '2026-04-11 14:05:56', '2026-04-20 19:51:03', 'Symfony admin', '2026-04-20 20:51:03', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(14, 'ma', 'salwa', 'salwa@gmail.com', '85621352', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$T+W8lhCHtfJ+ivDdYkEf4w==$6dbcjkTGW+54fo100ZLpKg/ovhn9Aea1VPqmwDmNMJY=', '2026-04-11 14:21:59', '2026-04-11 14:25:17', 'Symfony admin', '2026-04-11 15:25:17', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(15, 'Labidi', 'AbdeAziz', 'koussaylabidi9@gmail.com', '98372153', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$8FfbTehCRQzuIv/xqPtXaQ==$p2eaVTI2pyIeA8KqdMRJN8xrDEQXA76P7rcg+ODUCYw=', '2026-04-18 15:10:21', '2026-05-02 07:26:05', 'Symfony admin', '2026-05-02 08:26:05', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(16, 'Boussidi', 'Rimene', 'rimeneboussidi@yahoo.com', '65281346', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$8l1h1u/hHObpUi7mVcs9Bw==$kmd59q5SZtFSWp+6Xzlxzd+KU7WOQg9TmhAxdMl9GyQ=', '2026-04-20 08:19:19', '2026-04-22 22:54:19', 'Symfony admin', '2026-04-22 23:54:19', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(17, 'Guedich', 'Adam', 'adam@gmail.com', '65213547', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$kciIrKEgIe+prVmk+yS+fA==$UB4f9c1ZrVoKamWhQckI+sntGkXH1xoAj6+leKqzqVM=', '2026-04-21 16:04:37', '2026-05-01 11:55:44', 'Symfony admin', '2026-05-01 12:55:44', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL),
(18, 'Fakraoui', 'Kodra', 'kodra@gmail.com', '51236457', 'ROLE_USER', 'ACTIVE', 'PBKDF2$210000$VzH+WKqmfVIhco2j+MXh2g==$qkLxtaE305gnp0AL8nXAJV3MmLk33iiCgV1xgKa101w=', '2026-04-23 11:01:56', '2026-04-23 11:06:53', 'Symfony admin', '2026-04-23 12:06:53', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 0, NULL, 'Unknown location', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `idAction` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_source` varchar(180) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `user_activity_log`
--

INSERT INTO `user_activity_log` (`idAction`, `idUser`, `action_type`, `action_source`, `details`, `created_at`) VALUES
(1, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 20:46:55'),
(2, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 20:54:34'),
(3, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 21:45:13'),
(4, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 21:53:09'),
(5, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 21:56:13'),
(6, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 21:57:27'),
(7, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 22:55:19'),
(8, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:04:13'),
(9, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:08:55'),
(10, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:14:02'),
(11, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:33:31'),
(12, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:36:19'),
(13, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:38:48'),
(14, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:41:15'),
(15, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-01 23:54:32'),
(16, 3, 'PROFILE_IMAGE_UPDATE', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'Profile image updated.', '2026-03-01 23:55:58'),
(17, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:06:52'),
(18, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:10:36'),
(19, 3, 'BIOMETRIC_PREF', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'Biometric login enabled.', '2026-03-02 00:13:40'),
(20, 3, 'BIOMETRIC_PREF', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'Biometric login disabled.', '2026-03-02 00:13:44'),
(21, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:15:26'),
(22, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:18:30'),
(23, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:23:59'),
(24, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:33:36'),
(25, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:52:23'),
(26, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 00:57:35'),
(27, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:00:28'),
(28, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:02:58'),
(29, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:08:43'),
(30, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:09:45'),
(31, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:10:51'),
(32, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:17:22'),
(33, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:18:03'),
(34, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:21:39'),
(35, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:22:29'),
(36, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-02 01:33:03'),
(37, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 10:08:38'),
(38, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 10:19:40'),
(39, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 19:09:07'),
(40, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 19:18:42'),
(41, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 20:40:23'),
(42, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:08:49'),
(43, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:24:06'),
(44, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:29:43'),
(45, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:35:58'),
(46, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:45:14'),
(47, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:51:04'),
(48, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:53:15'),
(49, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:55:39'),
(50, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:57:04'),
(51, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 21:59:41'),
(52, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 22:00:39'),
(53, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-03 22:01:18'),
(54, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 01:43:02'),
(55, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 02:05:29'),
(56, 3, 'AI_ACCOUNT_SECURED', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'AI risk level: HIGH; Password strengthened', '2026-03-04 02:06:03'),
(57, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 02:07:09'),
(58, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 02:16:03'),
(59, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:12:53'),
(60, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:14:08'),
(61, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:21:13'),
(62, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:25:34'),
(63, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:26:25'),
(64, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:28:40'),
(65, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:29:38'),
(66, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:31:01'),
(67, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:31:50'),
(68, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:32:52'),
(69, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:33:45'),
(70, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:42:52'),
(71, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 19:59:44'),
(72, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:01:55'),
(73, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:04:06'),
(74, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:06:34'),
(75, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:13:07'),
(76, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:20:50'),
(77, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:23:23'),
(78, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:27:19'),
(79, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:34:43'),
(80, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:38:24'),
(81, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-04 20:44:52'),
(82, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:01:48'),
(83, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:07:52'),
(84, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:19:00'),
(85, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:21:28'),
(86, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:30:01'),
(87, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:54:38'),
(88, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 02:58:54'),
(89, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 03:00:10'),
(90, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 03:07:46'),
(91, 3, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 03:08:17'),
(92, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 03:09:16'),
(93, 1, 'LOGIN', 'XPS@DESKTOP-3JU5SI6 (Windows 11)', 'User login recorded.', '2026-03-05 03:10:18'),
(94, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 10:44:00'),
(95, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 10:48:14'),
(96, 10, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 10:49:17'),
(97, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 10:54:42'),
(98, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 10:59:23'),
(99, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 11:12:37'),
(100, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 11:45:53'),
(101, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 11:46:03'),
(102, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 11:51:56'),
(103, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:01:14'),
(104, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:10:41'),
(105, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:17:29'),
(106, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:22:34'),
(107, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:28:16'),
(108, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:30:37'),
(109, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:42:35'),
(110, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:46:06'),
(111, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 12:46:42'),
(112, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:06:03'),
(113, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:28:29'),
(114, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:34:41'),
(115, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:35:55'),
(116, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:41:51'),
(117, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 13:44:23'),
(118, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:11:00'),
(119, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:14:47'),
(120, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:27:42'),
(121, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:40:11'),
(122, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:45:52'),
(123, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:49:57'),
(124, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:52:31'),
(125, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-04-04 14:54:58'),
(126, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 14:56:57'),
(127, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:01:09'),
(128, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:20:15'),
(129, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:21:49'),
(130, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-04-04 15:26:15'),
(131, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-01-28 15:27:35'),
(132, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:29:45'),
(133, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:34:11'),
(134, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:49:25'),
(135, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:51:24'),
(136, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:57:58'),
(137, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 15:59:40'),
(138, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:01:20'),
(139, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:04:02'),
(140, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:08:50'),
(141, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:09:32'),
(142, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:12:08'),
(143, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-02-01 16:13:50'),
(144, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:20:06'),
(145, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 16:29:10'),
(146, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 17:55:48'),
(147, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:02:07'),
(148, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:03:39'),
(149, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:11:34'),
(150, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:13:18'),
(151, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:15:15'),
(152, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:17:06'),
(153, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:20:12'),
(154, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:25:00'),
(155, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:27:10'),
(156, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:50:15'),
(157, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-03-05 18:57:12'),
(158, 9, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-04-01 18:59:41'),
(159, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-04-01 19:01:33'),
(160, 1, 'LOGIN', 'user@DESKTOP-H7LO1H2 (Windows 10)', 'User login recorded.', '2026-04-01 19:04:07'),
(161, 10, 'SIGNUP', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'Account created and waiting for admin approval.', '2026-03-05 19:58:32'),
(162, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 19:59:07'),
(163, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 20:01:37'),
(164, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 20:55:52'),
(165, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 20:57:17'),
(166, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 20:58:27'),
(167, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:16:19'),
(168, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:25:37'),
(169, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:26:34'),
(170, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:53:59'),
(171, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:55:22'),
(172, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:57:45'),
(173, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 21:58:44'),
(174, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:06:20'),
(175, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:14:59'),
(176, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:21:33'),
(177, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:23:18'),
(178, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:26:29'),
(179, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:30:13'),
(180, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:35:36'),
(181, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:42:24'),
(182, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:45:13'),
(183, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:49:31'),
(184, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:53:14'),
(185, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:54:00'),
(186, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 22:58:04'),
(187, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 23:46:12'),
(188, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 23:49:21'),
(189, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-05 23:59:10'),
(190, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 00:18:18'),
(191, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 00:19:06'),
(192, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 01:24:32'),
(193, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 01:27:46'),
(194, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 01:41:03'),
(195, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 08:16:29'),
(196, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 08:36:30'),
(197, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 08:37:40'),
(198, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 08:42:34'),
(199, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 08:46:52'),
(200, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 10:34:39'),
(201, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 10:41:52'),
(202, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 10:42:32'),
(203, 1, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 10:47:17'),
(204, 10, 'LOGIN', 'HP@DESKTOP-MPPK2VD (Windows 11)', 'User login recorded.', '2026-03-06 10:56:45'),
(205, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-06 22:05:37'),
(206, 7, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-06 22:06:36'),
(207, 10, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #4 updated.', '2026-04-06 22:14:11'),
(208, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-06 22:31:30'),
(209, 10, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-06 22:33:20'),
(210, 10, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-06 22:33:24'),
(211, 10, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-06 22:33:28'),
(212, 10, 'GARANTIE_DELETE', 'Symfony portal', 'Guarantee #3 deleted.', '2026-04-06 23:06:27'),
(213, 10, 'GARANTIE_DELETE', 'Symfony portal', 'Guarantee #4 deleted.', '2026-04-06 23:06:30'),
(214, 10, 'GARANTIE_DELETE', 'Symfony portal', 'Guarantee #5 deleted.', '2026-04-06 23:06:33'),
(215, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-06 23:29:21'),
(216, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-06 23:41:01'),
(217, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 00:00:49'),
(218, 10, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-07 00:08:34'),
(219, 10, 'PROFILE_UPDATE', NULL, 'Profile details updated.', '2026-04-07 00:14:09'),
(220, 10, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-07 10:36:44'),
(221, 10, 'CASHBACK_CREATE', 'Symfony portal', 'Cashback request created.', '2026-04-07 10:40:29'),
(222, 10, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-07 10:55:30'),
(223, 10, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #4 updated.', '2026-04-07 13:10:52'),
(224, 10, 'PROFILE_UPDATE', NULL, 'Profile details updated.', '2026-04-07 13:13:21'),
(225, 10, 'PROFILE_UPDATE', NULL, 'Profile details updated.', '2026-04-07 13:13:39'),
(226, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:15:21'),
(227, 10, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #4 updated.', '2026-04-07 13:15:53'),
(228, 10, 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.', '2026-04-07 13:16:28'),
(229, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:16:51'),
(230, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:17:37'),
(231, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:28:04'),
(232, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:33:41'),
(233, 10, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #5 updated.', '2026-04-07 13:34:14'),
(234, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 13:42:30'),
(235, 1, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 14:20:45'),
(236, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 14:22:15'),
(237, 10, 'LOGIN', 'DESKTOP-MPPK2VD (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 14:30:24'),
(238, 9, 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.', '2026-04-07 21:13:35'),
(239, 9, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #6 updated.', '2026-04-07 21:13:57'),
(240, 9, 'CASHBACK_UPDATE', 'Symfony portal', 'Cashback #14 updated.', '2026-04-07 21:14:44'),
(241, 9, 'CASHBACK_UPDATE', 'Symfony portal', 'Cashback #12 updated.', '2026-04-07 21:15:16'),
(242, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-07 21:15:47'),
(243, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 08:07:27'),
(244, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 10:56:17'),
(245, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 10:57:07'),
(246, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 11:08:16'),
(247, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #121 updated.', '2026-04-08 11:08:26'),
(248, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #121 updated.', '2026-04-08 11:08:57'),
(249, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 11:11:34'),
(250, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 11:27:57'),
(251, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 11:29:07'),
(252, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #122 updated.', '2026-04-08 11:29:38'),
(253, 8, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 11:33:50'),
(254, 8, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #100 updated.', '2026-04-08 13:37:58'),
(255, 8, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 14:18:46'),
(256, 8, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 14:19:33'),
(257, 8, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #125 updated.', '2026-04-08 14:36:50'),
(258, 8, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #100 deleted.', '2026-04-08 14:36:58'),
(259, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 14:55:49'),
(260, 8, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 14:56:58'),
(261, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 15:14:46'),
(262, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 15:14:46'),
(263, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 15:22:31'),
(264, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 15:22:31'),
(265, 8, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 15:24:24'),
(266, 8, 'VAULT_DELETE', 'Symfony portal', 'Virtual vault #71 deleted.', '2026-04-08 15:26:47'),
(267, 8, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #70 updated.', '2026-04-08 15:27:13'),
(268, 8, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #69 updated.', '2026-04-08 15:28:11'),
(269, 8, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 15:29:24'),
(270, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 17:03:01'),
(271, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 17:04:35'),
(272, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 17:08:24'),
(273, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 17:16:37'),
(274, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #126 updated.', '2026-04-08 17:16:58'),
(275, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 17:17:46'),
(276, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-08 17:18:04'),
(277, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 17:36:34'),
(278, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 17:37:21'),
(279, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 17:37:21'),
(280, 9, 'VAULT_DELETE', 'Symfony portal', 'Virtual vault #74 deleted.', '2026-04-08 17:49:25'),
(281, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 17:49:56'),
(282, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 18:55:30'),
(283, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #129 updated.', '2026-04-08 18:56:07'),
(284, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 18:56:47'),
(285, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #75 updated.', '2026-04-08 19:10:50'),
(286, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #75 updated.', '2026-04-08 19:11:08'),
(287, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 19:16:09'),
(288, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-08 19:27:31'),
(289, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-08 19:35:27'),
(290, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 19:44:42'),
(291, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 19:46:26'),
(292, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 19:48:01'),
(293, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 19:58:25'),
(294, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 20:09:28'),
(295, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 20:21:34'),
(296, 9, 'VAULT_DELETE', 'Symfony portal', 'Virtual vault #50 deleted.', '2026-04-08 20:23:13'),
(297, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-08 20:23:31'),
(298, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #130 deleted.', '2026-04-08 20:23:47'),
(299, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #129 updated.', '2026-04-08 20:24:14'),
(300, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #73 updated.', '2026-04-08 20:24:40'),
(301, 9, 'VAULT_DELETE', 'Symfony portal', 'Virtual vault #50 deleted.', '2026-04-08 20:25:02'),
(302, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #75 updated.', '2026-04-08 20:26:49'),
(303, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #73 updated.', '2026-04-08 20:28:27'),
(304, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 20:28:52'),
(305, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-08 20:29:28'),
(306, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #132 updated.', '2026-04-08 20:29:42'),
(307, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-08 20:31:44'),
(308, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #78 updated.', '2026-04-08 20:32:09'),
(309, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 22:10:59'),
(310, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 22:11:32'),
(311, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 22:14:50'),
(312, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-08 22:42:26'),
(313, 1, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-08 23:09:44'),
(314, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #54 updated.', '2026-04-08 23:10:15'),
(315, 1, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-08 23:11:58'),
(316, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-08 23:12:56'),
(317, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 00:14:04'),
(318, 9, 'RECLAMATION_UPDATE', 'Symfony portal', 'Complaint #1 updated.', '2026-04-09 00:14:34'),
(319, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 00:15:47'),
(320, 9, 'RECLAMATION_UPDATE', 'Symfony portal', 'Complaint #2 updated.', '2026-04-09 00:15:58'),
(321, 1, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 00:18:33'),
(322, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 00:30:43'),
(323, 1, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 00:35:45'),
(324, 1, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 00:42:21'),
(325, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #83 updated.', '2026-04-09 00:43:30'),
(326, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #126 updated.', '2026-04-09 00:44:39'),
(327, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 07:59:47'),
(328, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 08:23:52'),
(329, 9, 'TRANSACTION_DELETE', 'Symfony portal', 'Transaction #83 deleted.', '2026-04-09 08:24:03'),
(330, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 08:28:36'),
(331, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 08:29:20'),
(332, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 08:30:04'),
(333, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 08:36:17'),
(334, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #87 updated.', '2026-04-09 09:09:42'),
(335, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #87 updated.', '2026-04-09 09:09:59'),
(336, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #86 updated.', '2026-04-09 09:10:22'),
(337, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 09:53:36'),
(338, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 10:08:56'),
(339, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 10:10:23'),
(340, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 10:15:00'),
(341, 9, 'RECLAMATION_DELETE', 'Symfony portal', 'Complaint #9 deleted.', '2026-04-09 10:15:22'),
(342, 9, 'RECLAMATION_UPDATE', 'Symfony portal', 'Complaint #6 updated.', '2026-04-09 10:27:40'),
(343, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-09 10:58:53'),
(344, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #133 updated.', '2026-04-09 10:59:07'),
(345, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #133 updated.', '2026-04-09 11:02:23'),
(346, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-09 11:05:15'),
(347, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #79 updated.', '2026-04-09 11:05:37'),
(348, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 12:13:21'),
(349, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 12:13:37'),
(350, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #88 updated.', '2026-04-09 12:14:03'),
(351, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 12:24:31'),
(352, 9, 'RECLAMATION_DELETE', 'Symfony portal', 'Complaint #7 deleted.', '2026-04-09 12:24:45'),
(353, 9, 'RECLAMATION_UPDATE', 'Symfony portal', 'Complaint #11 updated.', '2026-04-09 12:25:06'),
(354, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 12:25:46'),
(355, 9, 'TRANSACTION_DELETE', 'Symfony portal', 'Transaction #54 deleted.', '2026-04-09 12:25:57'),
(356, 9, 'TRANSACTION_DELETE', 'Symfony portal', 'Transaction #82 deleted.', '2026-04-09 12:26:37'),
(357, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #89 updated.', '2026-04-09 12:26:57'),
(358, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 12:54:19'),
(359, 9, 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.', '2026-04-09 13:21:00'),
(360, 9, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #7 updated.', '2026-04-09 13:22:17'),
(361, 9, 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.', '2026-04-09 13:44:17'),
(362, 9, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #8 updated.', '2026-04-09 13:44:39'),
(363, 9, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #8 updated.', '2026-04-09 14:29:00'),
(364, 9, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-09 14:29:40'),
(365, 9, 'CREDIT_CREATE', 'Symfony portal', 'Credit dossier created.', '2026-04-09 14:39:19'),
(366, 9, 'CREDIT_UPDATE', 'Symfony portal', 'Credit #7 updated.', '2026-04-09 14:39:44'),
(367, 9, 'CREDIT_DELETE', 'Symfony portal', 'Credit #8 deleted.', '2026-04-09 14:39:56'),
(368, 9, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-09 14:40:25'),
(369, 9, 'GARANTIE_CREATE', 'Symfony portal', 'Guarantee created.', '2026-04-09 14:41:20'),
(370, 9, 'CASHBACK_UPDATE', 'Symfony portal', 'Cashback #8 updated.', '2026-04-09 14:42:58'),
(371, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 15:18:14'),
(372, 5, 'CASHBACK_UPDATE', 'Symfony portal', 'Cashback #4 updated.', '2026-04-09 15:29:15'),
(373, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 15:33:12'),
(374, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-09 15:38:46'),
(375, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #134 updated.', '2026-04-09 15:39:14'),
(376, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #131 deleted.', '2026-04-09 15:40:32'),
(377, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-09 15:41:54'),
(378, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #80 updated.', '2026-04-09 15:42:16'),
(379, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-09 15:43:20'),
(380, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #87 updated.', '2026-04-09 15:43:59'),
(381, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-09 15:44:32'),
(382, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #87 updated.', '2026-04-09 15:45:21'),
(383, 9, 'TRANSACTION_DELETE', 'Symfony portal', 'Transaction #86 deleted.', '2026-04-09 15:45:26'),
(384, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-09 15:46:27'),
(385, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-09 15:47:01'),
(386, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #135 updated.', '2026-04-09 15:47:12'),
(387, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #80 updated.', '2026-04-09 15:48:13'),
(388, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-09 15:49:00'),
(389, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #87 updated.', '2026-04-09 15:50:08'),
(390, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 07:39:42'),
(391, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-10 07:40:20'),
(392, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #133 updated.', '2026-04-10 07:40:46'),
(393, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #81 updated.', '2026-04-10 07:42:08'),
(394, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:04:34'),
(395, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-10 08:05:09'),
(396, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #137 updated.', '2026-04-10 08:05:29'),
(397, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #129 deleted.', '2026-04-10 08:05:50'),
(398, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-10 08:06:32'),
(399, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #82 updated.', '2026-04-10 08:07:04'),
(400, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:08:56'),
(401, 1, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-10 08:29:16'),
(402, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:29:55'),
(403, 9, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #90 updated.', '2026-04-10 08:30:52'),
(404, 9, 'TRANSACTION_DELETE', 'Symfony portal', 'Transaction #90 deleted.', '2026-04-10 08:30:58'),
(405, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:52:47'),
(406, 1, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-10 08:53:17'),
(407, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #138 updated.', '2026-04-10 08:53:40'),
(408, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:54:08'),
(409, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #137 updated.', '2026-04-10 08:54:48'),
(410, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:55:10'),
(411, 1, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #137 updated.', '2026-04-10 08:55:29'),
(412, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 08:55:42'),
(413, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-10 08:56:58'),
(414, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #83 updated.', '2026-04-10 08:57:24'),
(415, 10, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0)', 'User login recorded.', '2026-04-10 09:05:42'),
(416, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0)', 'User login recorded.', '2026-04-10 09:08:28'),
(417, 1, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-10 09:12:58'),
(418, 1, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #92 updated.', '2026-04-10 09:14:04'),
(419, 9, 'RECLAMATION_UPDATE', 'Symfony portal', 'Complaint #2 updated.', '2026-04-10 09:18:00'),
(420, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 09:24:16'),
(421, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 09:32:01'),
(422, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 09:40:03'),
(423, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 12:25:55'),
(424, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-10 12:26:47'),
(425, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 10:08:46'),
(426, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:36:35'),
(427, 12, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:37:38'),
(428, 12, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #140 updated.', '2026-04-11 11:40:27'),
(429, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:40:55'),
(430, 12, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #140 updated.', '2026-04-11 11:41:31'),
(431, 12, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:41:50'),
(432, 12, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 11:42:25'),
(433, 12, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-11 11:43:22'),
(434, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:44:06'),
(435, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #84 updated.', '2026-04-11 11:44:48'),
(436, 12, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #141 updated.', '2026-04-11 11:45:26'),
(437, 12, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:45:40'),
(438, 12, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #84 updated.', '2026-04-11 11:46:15'),
(439, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:47:47'),
(440, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:49:47'),
(441, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #134 deleted.', '2026-04-11 11:50:05'),
(442, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-11 11:50:43'),
(443, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:50:58'),
(444, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-11 11:52:23'),
(445, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:52:44'),
(446, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #85 updated.', '2026-04-11 11:53:07');
INSERT INTO `user_activity_log` (`idAction`, `idUser`, `action_type`, `action_source`, `details`, `created_at`) VALUES
(447, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 11:53:55'),
(448, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-11 11:54:39'),
(449, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:54:59'),
(450, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 11:56:13'),
(451, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 11:56:29'),
(452, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 12:00:35'),
(453, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:06:26'),
(454, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:07:23'),
(455, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:08:58'),
(456, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:11:36'),
(457, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:14:24'),
(458, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 14:15:14'),
(459, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:15:35'),
(460, 13, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #144 updated.', '2026-04-11 14:16:01'),
(461, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 14:16:39'),
(462, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:17:29'),
(463, 13, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #145 updated.', '2026-04-11 14:17:45'),
(464, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:18:02'),
(465, 13, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-11 14:18:54'),
(466, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:19:15'),
(467, 14, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:22:48'),
(468, 14, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 14:23:21'),
(469, 14, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #146 updated.', '2026-04-11 14:23:35'),
(470, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:23:53'),
(471, 14, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #146 updated.', '2026-04-11 14:24:21'),
(472, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-11 14:25:01'),
(473, 14, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:25:17'),
(474, 14, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #88 updated.', '2026-04-11 14:26:01'),
(475, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:26:16'),
(476, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:43:32'),
(477, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 14:44:04'),
(478, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 14:44:28'),
(479, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 15:09:39'),
(480, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 15:10:11'),
(481, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 15:10:34'),
(482, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 15:10:47'),
(483, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 15:11:31'),
(484, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 15:16:37'),
(485, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 15:17:26'),
(486, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 15:21:52'),
(487, 12, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-11 15:22:42'),
(488, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 15:26:07'),
(489, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-11 20:16:15'),
(490, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-12 07:49:02'),
(491, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-12 08:01:08'),
(492, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-12 08:07:09'),
(493, 12, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-12 08:33:57'),
(494, 12, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-12 08:38:00'),
(495, 12, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #152 updated.', '2026-04-12 08:38:20'),
(496, 12, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-12 08:45:14'),
(497, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-12 11:04:21'),
(498, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #151 deleted.', '2026-04-12 11:11:01'),
(499, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-12 11:37:49'),
(500, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #90 updated.', '2026-04-12 11:47:30'),
(501, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-12 11:49:55'),
(502, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-12 11:50:36'),
(503, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #92 updated.', '2026-04-12 11:51:32'),
(504, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #91 updated.', '2026-04-12 11:52:33'),
(505, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-12 14:17:05'),
(506, 13, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 3 points.', '2026-04-12 15:12:42'),
(507, 13, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 7 points.', '2026-04-12 15:34:45'),
(508, 13, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 15 points.', '2026-04-12 15:37:20'),
(509, 13, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #153.', '2026-04-12 15:37:25'),
(510, 13, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 20 points.', '2026-05-12 16:21:25'),
(511, 13, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 5 points.', '2026-04-12 17:24:41'),
(512, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-13 07:30:33'),
(513, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-13 09:07:35'),
(514, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 6 points.', '2026-04-13 11:07:21'),
(515, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 15 points.', '2026-03-12 11:20:07'),
(516, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 20 points.', '2026-04-13 11:27:52'),
(517, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 12:05:04'),
(518, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 18:37:06'),
(519, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-14 19:47:31'),
(520, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 19:55:07'),
(521, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 19:55:41'),
(522, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 20:16:49'),
(523, 13, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #144 deleted.', '2026-04-14 20:19:50'),
(524, 13, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #147 deleted.', '2026-04-14 20:19:58'),
(525, 13, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-14 20:32:35'),
(526, 13, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-14 20:33:25'),
(527, 13, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #93 updated.', '2026-04-14 20:33:44'),
(528, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:30:06'),
(529, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:31:29'),
(530, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:32:09'),
(531, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-14 21:50:56'),
(532, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:51:42'),
(533, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:52:33'),
(534, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #156 updated.', '2026-04-14 21:52:54'),
(535, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-14 21:53:10'),
(536, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:53:31'),
(537, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #156 updated.', '2026-04-14 21:53:58'),
(538, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #95 updated.', '2026-04-14 21:54:20'),
(539, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-14 21:54:31'),
(540, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #95 updated.', '2026-04-14 21:55:12'),
(541, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #155 deleted.', '2026-04-14 23:08:11'),
(542, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #150 deleted.', '2026-04-14 23:08:31'),
(543, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #156 deleted.', '2026-04-14 23:08:40'),
(544, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 08:07:48'),
(545, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 8 points.', '2026-04-15 09:34:29'),
(546, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 2 points.', '2026-04-15 09:35:42'),
(547, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 5 points.', '2026-04-15 09:36:16'),
(548, 9, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #143.', '2026-04-15 09:36:19'),
(549, 9, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 700.00 DT of detected monthly surplus to vault #92.', '2026-04-15 10:36:20'),
(550, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 20:18:55'),
(551, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #143 updated.', '2026-04-15 20:19:47'),
(552, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 20:20:37'),
(553, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-15 20:21:16'),
(554, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #143 updated.', '2026-04-15 20:31:31'),
(555, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-15 20:32:22'),
(556, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 20:32:58'),
(557, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 20:33:18'),
(558, 9, 'VAULT_GOAL_EXTEND', 'Symfony portal', 'Extended vault #75 by P3M until 2026-07-13.', '2026-04-15 21:18:37'),
(559, 9, 'VAULT_GOAL_TRANSFER', 'Symfony portal', 'Transferred 950.00 DT from vault #79 to account #142.', '2026-04-15 21:19:14'),
(560, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-15 21:30:23'),
(561, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 21:30:57'),
(562, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-15 21:32:22'),
(563, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-15 21:34:23'),
(564, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #157 deleted.', '2026-04-15 21:34:39'),
(565, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #142 updated.', '2026-04-15 21:50:50'),
(566, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 10:52:58'),
(567, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-16 10:54:05'),
(568, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 11:03:04'),
(569, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 11:04:43'),
(570, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-16 11:08:42'),
(571, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 12:47:19'),
(572, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 12:56:39'),
(573, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 13:30:30'),
(574, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-16 13:32:05'),
(575, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-16 13:33:26'),
(576, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-16 13:39:53'),
(577, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-17 08:59:08'),
(578, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #159 deleted.', '2026-04-17 12:20:24'),
(579, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-17 12:20:37'),
(580, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-17 12:49:45'),
(581, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-17 13:30:58'),
(582, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-17 13:34:08'),
(583, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-17 15:59:33'),
(584, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 09:24:28'),
(585, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 15:11:11'),
(586, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 16:07:45'),
(587, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:00:52'),
(588, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:11:36'),
(589, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:22:43'),
(590, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:23:23'),
(591, 15, 'VAULT_GOAL_TRANSFER', 'Symfony portal', 'Transferred 7000.00 DT from vault #205 to account #103.', '2026-04-18 19:23:50'),
(592, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:32:18'),
(593, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:33:09'),
(594, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-18 19:38:05'),
(595, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:38:25'),
(596, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 19:40:46'),
(597, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 21:31:06'),
(598, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 21:34:36'),
(599, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 21:49:22'),
(600, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 21:51:00'),
(601, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 22:12:01'),
(602, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 22:12:59'),
(603, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 22:17:25'),
(604, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-18 22:18:09'),
(605, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 08:43:29'),
(606, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #201 updated.', '2026-04-19 09:01:46'),
(607, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #202 updated.', '2026-04-19 09:02:37'),
(608, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 09:21:57'),
(609, 9, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 786.67 DT of detected monthly surplus to vault #96.', '2026-04-19 09:37:23'),
(610, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #96 updated.', '2026-04-19 09:37:47'),
(611, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #98 updated.', '2026-04-19 09:38:24'),
(612, 9, 'VAULT_GOAL_TRANSFER', 'Symfony portal', 'Transferred 1120.00 DT from vault #98 to account #143.', '2026-04-19 09:39:24'),
(613, 9, 'VAULT_GOAL_EXTEND', 'Symfony portal', 'Extended vault #85 by P6M until 2026-10-18.', '2026-04-19 09:39:55'),
(614, 9, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 10 points.', '2026-04-19 09:42:44'),
(615, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 14:26:00'),
(616, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 12 points.', '2026-04-19 14:55:21'),
(617, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 5 points.', '2026-04-19 14:56:11'),
(618, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 2 points.', '2026-04-19 14:56:37'),
(619, 15, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #103.', '2026-04-19 14:56:41'),
(620, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #205 updated.', '2026-04-19 14:57:52'),
(621, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #204 updated.', '2026-04-19 14:58:24'),
(622, 15, 'VAULT_GOAL_TRANSFER', 'Symfony portal', 'Transferred 10000.00 DT from vault #204 to account #101.', '2026-04-19 14:59:16'),
(623, 15, 'VAULT_GOAL_EXTEND', 'Symfony portal', 'Extended vault #202 by P3M until 2026-07-15.', '2026-04-19 14:59:35'),
(624, 15, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 90.00 DT of detected monthly surplus to vault #204.', '2026-04-19 15:31:04'),
(625, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 19:45:09'),
(626, 15, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-19 19:46:28'),
(627, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 19:46:51'),
(628, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 19:51:45'),
(629, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-19 19:52:43'),
(630, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 19:53:00'),
(631, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 19:58:30'),
(632, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 20:27:12'),
(633, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #164 updated.', '2026-04-19 21:42:33'),
(634, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-19 21:42:53'),
(635, 9, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-19 21:43:15'),
(636, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #206 updated.', '2026-04-19 21:43:58'),
(637, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 08:16:09'),
(638, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 08:17:16'),
(639, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 08:20:05'),
(640, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 08:23:27'),
(641, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #165 updated.', '2026-04-20 08:28:59'),
(642, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 08:29:23'),
(643, 16, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-20 08:30:11'),
(644, 16, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-20 08:30:52'),
(645, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #207 updated.', '2026-04-20 08:31:14'),
(646, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 08:32:09'),
(647, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 08:32:56'),
(648, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 10:58:01'),
(649, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-20 11:26:29'),
(650, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-20 11:27:00'),
(651, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 11:36:01'),
(652, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 18:20:44'),
(653, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 18:22:26'),
(654, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #167 updated.', '2026-04-20 18:23:10'),
(655, 1, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-20 18:24:46'),
(656, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #209 updated.', '2026-04-20 18:25:05'),
(657, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 18:26:24'),
(658, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #167 updated.', '2026-04-20 18:26:39'),
(659, 16, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-20 18:26:57'),
(660, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #209 updated.', '2026-04-20 18:27:44'),
(661, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #209 updated.', '2026-04-20 18:28:15'),
(662, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #210 updated.', '2026-04-20 18:28:37'),
(663, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 18:29:00'),
(664, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #167 updated.', '2026-04-20 18:29:24'),
(665, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 18:29:38'),
(666, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 18:31:21'),
(667, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 18:42:23'),
(668, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 19:11:51'),
(669, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 19:40:07'),
(670, 13, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 19:51:03'),
(671, 13, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 19:58:02'),
(672, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 19:59:20'),
(673, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 19:59:48'),
(674, 9, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-20 20:00:28'),
(675, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 20:01:49'),
(676, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 20:02:39'),
(677, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #171 deleted.', '2026-04-20 20:02:51'),
(678, 9, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #164 deleted.', '2026-04-20 20:03:05'),
(679, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 20:03:32'),
(680, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #210 updated.', '2026-04-20 20:24:35'),
(681, 16, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 25 points.', '2026-04-20 21:01:27'),
(682, 16, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 2 points.', '2026-04-20 21:02:11'),
(683, 16, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 20 points.', '2026-04-20 21:03:08'),
(684, 16, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #167.', '2026-04-20 21:03:14'),
(685, 16, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 230.00 DT of detected monthly surplus to vault #208.', '2026-04-20 21:07:44'),
(686, 16, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 230.00 DT of detected monthly surplus to vault #208.', '2026-04-20 21:09:48'),
(687, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #165 updated.', '2026-04-20 21:32:36'),
(688, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-20 21:57:22'),
(689, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 09:37:52'),
(690, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 09:38:55'),
(691, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 09:55:11'),
(692, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 10:08:05'),
(693, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 10:10:05'),
(694, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 10:21:07'),
(695, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 10:30:57'),
(696, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 10:49:48'),
(697, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 11:03:31'),
(698, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 11:04:45'),
(699, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 11:09:15'),
(700, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 11:12:02'),
(701, 9, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 11:14:08'),
(702, 9, 'RECLAMATION_CREATE', 'Symfony portal', 'Complaint created.', '2026-04-21 11:23:42'),
(703, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 11:55:48'),
(704, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 12:15:30'),
(705, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 12:17:22'),
(706, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 12:18:46'),
(707, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 14:06:31'),
(708, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 14:07:42'),
(709, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 14:08:52'),
(710, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 16:02:40'),
(711, 17, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-21 16:05:37'),
(712, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 16:06:25'),
(713, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #172 updated.', '2026-04-21 16:07:06'),
(714, 17, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-21 16:07:16'),
(715, 17, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #211 updated.', '2026-04-21 16:07:40'),
(716, 17, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-21 16:08:17'),
(717, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #172 updated.', '2026-04-21 16:08:43'),
(718, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 16:08:53'),
(719, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #172 updated.', '2026-04-21 16:09:15'),
(720, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 16:10:01'),
(721, 17, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-21 16:10:41'),
(722, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #173 updated.', '2026-04-21 16:11:31'),
(723, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:17:32'),
(724, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:20:31'),
(725, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:22:29'),
(726, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:24:29'),
(727, 17, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #5070 updated.', '2026-04-21 16:26:25'),
(728, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:28:54'),
(729, 17, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 8 points.', '2026-04-21 16:30:53'),
(730, 17, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 10 points.', '2026-04-21 16:35:40'),
(731, 17, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #173.', '2026-04-21 16:35:42'),
(732, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:36:52'),
(733, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:37:27'),
(734, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:38:01'),
(735, 17, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 330.00 DT of detected monthly surplus to vault #211.', '2026-04-21 16:40:52'),
(736, 17, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #212 updated.', '2026-04-21 16:41:12'),
(737, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:43:01'),
(738, 17, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-21 16:43:47'),
(739, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-21 16:44:35'),
(740, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 19:55:10'),
(741, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 20:01:27'),
(742, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 20:05:42'),
(743, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 20:09:23'),
(744, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 20:51:16'),
(745, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-22 21:25:07'),
(746, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 22:46:17'),
(747, 16, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-22 22:48:08'),
(748, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #207 updated.', '2026-04-22 22:48:50'),
(749, 16, 'ACCOUNT_DELETE', 'Symfony portal', 'Bank account #166 deleted.', '2026-04-22 22:51:13'),
(750, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 22:51:44'),
(751, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #175 updated.', '2026-04-22 22:52:30'),
(752, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 22:52:45'),
(753, 16, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-22 22:53:00'),
(754, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #175 updated.', '2026-04-22 22:53:29'),
(755, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 22:53:51'),
(756, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #175 updated.', '2026-04-22 22:54:06'),
(757, 16, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-22 22:54:19'),
(758, 16, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #213 updated.', '2026-04-22 22:54:50'),
(759, 16, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #175 updated.', '2026-04-22 22:55:43'),
(760, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 04:50:22'),
(761, 15, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-23 04:51:30'),
(762, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 05:00:55'),
(763, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 05:05:30'),
(764, 15, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #103 updated.', '2026-04-23 05:06:08'),
(765, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #205 updated.', '2026-04-23 05:06:42'),
(766, 15, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-23 05:06:49'),
(767, 15, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-23 05:08:16'),
(768, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #214 updated.', '2026-04-23 05:09:04'),
(769, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 05:09:26'),
(770, 15, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #102 updated.', '2026-04-23 05:11:10'),
(771, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 05:11:31'),
(772, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:15:00'),
(773, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:15:37'),
(774, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:16:29'),
(775, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:16:53'),
(776, 15, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #5079 updated.', '2026-04-23 05:17:29'),
(777, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:18:22'),
(778, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:20:10'),
(779, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:22:00'),
(780, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:22:15'),
(781, 15, 'TRANSACTION_UPDATE', 'Symfony portal', 'Transaction #5084 updated.', '2026-04-23 05:25:13'),
(782, 15, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 490.00 DT of detected monthly surplus to vault #204.', '2026-04-23 05:37:53'),
(783, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:41:30'),
(784, 15, 'TRANSACTION_CREATE', 'Symfony portal', 'Transaction created.', '2026-04-23 05:44:28'),
(785, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 2 points.', '2026-04-23 05:45:04'),
(786, 15, 'SURPLUS_TRANSFER', 'Symfony portal', 'Transferred 554.00 DT of detected monthly surplus to vault #202.', '2026-04-23 05:45:32'),
(787, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 2 points.', '2026-04-23 05:47:22'),
(788, 15, 'WHEEL_SPIN', 'Symfony portal', 'Wheel spin awarded 15 points.', '2026-04-23 05:47:57'),
(789, 15, 'WHEEL_BONUS', 'Symfony portal', 'Wheel bonus credited to account #177.', '2026-04-23 05:47:59'),
(790, 15, 'VAULT_GOAL_TRANSFER', 'Symfony portal', 'Transferred 5000.00 DT from vault #201 to account #177.', '2026-04-23 05:49:40'),
(791, 15, 'VAULT_GOAL_EXTEND', 'Symfony portal', 'Extended vault #201 by P3M until 2026-05-05.', '2026-04-23 05:51:23'),
(792, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 05:52:21'),
(793, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 07:57:20'),
(794, 15, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #102 updated.', '2026-04-23 07:57:42'),
(795, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 08:07:35'),
(796, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #204 updated.', '2026-04-23 08:11:56'),
(797, 1, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #202 updated.', '2026-04-23 08:12:53'),
(798, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 08:14:02'),
(799, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 08:16:23'),
(800, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 10:22:13'),
(801, 9, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #81 updated.', '2026-04-23 10:23:35'),
(802, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 10:27:54'),
(803, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:01:08'),
(804, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:02:40');
INSERT INTO `user_activity_log` (`idAction`, `idUser`, `action_type`, `action_source`, `details`, `created_at`) VALUES
(805, 18, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:03:24'),
(806, 18, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-23 11:04:44'),
(807, 18, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-23 11:05:38'),
(808, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:05:57'),
(809, 18, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #178 updated.', '2026-04-23 11:06:27'),
(810, 18, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:06:53'),
(811, 18, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-23 11:07:10'),
(812, 18, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-23 11:07:14'),
(813, 18, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #216 updated.', '2026-04-23 11:07:58'),
(814, 18, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #215 updated.', '2026-04-23 11:08:24'),
(815, 18, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-23 11:09:06'),
(816, 18, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-04-23 11:09:15'),
(817, 18, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #218 updated.', '2026-04-23 11:10:07'),
(818, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-23 11:10:36'),
(819, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 09:18:43'),
(820, 17, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-04-30 09:19:11'),
(821, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 10:00:31'),
(822, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 10:40:16'),
(823, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 10:41:34'),
(824, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 10:42:26'),
(825, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-04-30 10:43:09'),
(826, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #180 updated.', '2026-04-30 10:43:32'),
(827, 17, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-05-01 11:55:44'),
(828, 17, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-05-01 11:58:24'),
(829, 17, 'VAULT_CREATE', 'Symfony portal', 'Virtual vault created.', '2026-05-01 11:59:06'),
(830, 17, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #219 updated.', '2026-05-01 12:00:41'),
(831, 17, 'ACCOUNT_CREATE', 'Symfony portal', 'Bank account created.', '2026-05-01 20:15:46'),
(832, 17, 'ACCOUNT_UPDATE', 'Symfony portal', 'Bank account #180 updated.', '2026-05-01 20:16:34'),
(833, 17, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #219 updated.', '2026-05-01 20:17:35'),
(834, 1, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-05-01 22:31:03'),
(835, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-05-01 22:42:23'),
(836, 15, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-05-02 07:26:05'),
(837, 15, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #205 updated.', '2026-05-02 09:48:39'),
(838, 9, 'LOGIN', 'DESKTOP-H7LO1H2 (Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36)', 'User login recorded.', '2026-05-02 09:54:13'),
(839, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #206 updated.', '2026-05-02 09:54:58'),
(840, 9, 'VAULT_UPDATE', 'Symfony portal', 'Virtual vault #85 updated.', '2026-05-02 10:56:31');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `cashback`
--
ALTER TABLE `cashback`
  ADD PRIMARY KEY (`idCashback`),
  ADD KEY `fk_cashback_partenaire` (`idPartenaire`),
  ADD KEY `fk_cashback_transaction` (`idTransaction`);

--
-- Index pour la table `cashback_entries`
--
ALTER TABLE `cashback_entries`
  ADD PRIMARY KEY (`id_cashback`),
  ADD KEY `fk_cashback_entries_user` (`id_user`),
  ADD KEY `fk_cashback_entries_partenaire` (`id_partenaire`);

--
-- Index pour la table `coffrevirtuel`
--
ALTER TABLE `coffrevirtuel`
  ADD PRIMARY KEY (`idCoffre`),
  ADD KEY `fk_coffre_compte` (`idCompte`),
  ADD KEY `fk_coffre_user` (`idUser`);

--
-- Index pour la table `compte`
--
ALTER TABLE `compte`
  ADD PRIMARY KEY (`idCompte`),
  ADD UNIQUE KEY `numeroCompte` (`numeroCompte`),
  ADD KEY `fk_compte_user` (`idUser`);

--
-- Index pour la table `credit`
--
ALTER TABLE `credit`
  ADD PRIMARY KEY (`idCredit`),
  ADD KEY `fk_credit_compte` (`idCompte`);

--
-- Index pour la table `credit_garantie_link`
--
ALTER TABLE `credit_garantie_link`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_CREDIT_GARANTIE_LINK_PAIR` (`idCredit`,`idGarantie`),
  ADD KEY `IDX_CREDIT_GARANTIE_LINK_CREDIT` (`idCredit`),
  ADD KEY `IDX_CREDIT_GARANTIE_LINK_GARANTIE` (`idGarantie`);

--
-- Index pour la table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Index pour la table `garantiecredit`
--
ALTER TABLE `garantiecredit`
  ADD PRIMARY KEY (`idGarantie`),
  ADD KEY `fk_garantie_credit` (`idCredit`);

--
-- Index pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`);

--
-- Index pour la table `mobile_access_tokens`
--
ALTER TABLE `mobile_access_tokens`
  ADD PRIMARY KEY (`idMobileAccessToken`),
  ADD UNIQUE KEY `uniq_mobile_token_hash` (`token_hash`),
  ADD KEY `idx_mobile_token_user` (`user_id`),
  ADD KEY `idx_mobile_token_device` (`user_id`,`device_id`,`revoked_at`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`idNotification`),
  ADD KEY `idx_notifications_recipient_user` (`recipient_user_id`),
  ADD KEY `idx_notifications_recipient_role` (`recipient_role`),
  ADD KEY `idx_notifications_created` (`created_at`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Index pour la table `partenaire`
--
ALTER TABLE `partenaire`
  ADD PRIMARY KEY (`idPartenaire`);

--
-- Index pour la table `qr_login_sessions`
--
ALTER TABLE `qr_login_sessions`
  ADD PRIMARY KEY (`idQrLoginSession`),
  ADD UNIQUE KEY `uniq_qr_login_token` (`session_token`),
  ADD KEY `idx_qr_login_lookup` (`session_token`,`status`,`expires_at`),
  ADD KEY `idx_qr_login_browser` (`browser_session_id`,`status`);

--
-- Index pour la table `qr_trust_sessions`
--
ALTER TABLE `qr_trust_sessions`
  ADD PRIMARY KEY (`idQrTrustSession`),
  ADD UNIQUE KEY `uniq_qr_trust_token` (`session_token`),
  ADD KEY `idx_qr_trust_lookup` (`session_token`,`status`,`expires_at`),
  ADD KEY `idx_qr_trust_user` (`user_id`,`status`);

--
-- Index pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD PRIMARY KEY (`idReclamation`),
  ADD KEY `fk_reclamation_user` (`idUser`),
  ADD KEY `fk_reclamation_transaction` (`idTransaction`);

--
-- Index pour la table `roue_fortune_points`
--
ALTER TABLE `roue_fortune_points`
  ADD PRIMARY KEY (`idUser`);

--
-- Index pour la table `strategiesproposees`
--
ALTER TABLE `strategiesproposees`
  ADD PRIMARY KEY (`idStrategie`),
  ADD KEY `idCoffre` (`idCoffre`),
  ADD KEY `idUser` (`idUser`);

--
-- Index pour la table `strategies_proposees`
--
ALTER TABLE `strategies_proposees`
  ADD PRIMARY KEY (`idStrategie`),
  ADD KEY `idx_coffre` (`idCoffre`),
  ADD KEY `idx_compte` (`idCompte`),
  ADD KEY `idx_user` (`idUser`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `superplus_notifications`
--
ALTER TABLE `superplus_notifications`
  ADD PRIMARY KEY (`idUser`,`moisAffiche`);

--
-- Index pour la table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`idSupportMessage`),
  ADD KEY `idx_support_sender` (`sender_user_id`),
  ADD KEY `idx_support_recipient` (`recipient_user_id`,`recipient_role`,`is_read`),
  ADD KEY `idx_support_created` (`created_at`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`idTransaction`),
  ADD KEY `fk_transaction_compte` (`idCompte`),
  ADD KEY `fk_transaction_user` (`idUser`);

--
-- Index pour la table `trusted_mobile_devices`
--
ALTER TABLE `trusted_mobile_devices`
  ADD PRIMARY KEY (`idTrustedDevice`),
  ADD UNIQUE KEY `uniq_user_device` (`user_id`,`device_id`),
  ADD KEY `idx_trusted_user` (`user_id`,`is_active`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`idUser`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Index pour la table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`idAction`),
  ADD KEY `idx_user_activity_user` (`idUser`),
  ADD KEY `idx_user_activity_created` (`created_at`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `cashback`
--
ALTER TABLE `cashback`
  MODIFY `idCashback` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cashback_entries`
--
ALTER TABLE `cashback_entries`
  MODIFY `id_cashback` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `coffrevirtuel`
--
ALTER TABLE `coffrevirtuel`
  MODIFY `idCoffre` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT pour la table `compte`
--
ALTER TABLE `compte`
  MODIFY `idCompte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT pour la table `credit`
--
ALTER TABLE `credit`
  MODIFY `idCredit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `credit_garantie_link`
--
ALTER TABLE `credit_garantie_link`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `garantiecredit`
--
ALTER TABLE `garantiecredit`
  MODIFY `idGarantie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mobile_access_tokens`
--
ALTER TABLE `mobile_access_tokens`
  MODIFY `idMobileAccessToken` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `idNotification` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;

--
-- AUTO_INCREMENT pour la table `partenaire`
--
ALTER TABLE `partenaire`
  MODIFY `idPartenaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `qr_login_sessions`
--
ALTER TABLE `qr_login_sessions`
  MODIFY `idQrLoginSession` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `qr_trust_sessions`
--
ALTER TABLE `qr_trust_sessions`
  MODIFY `idQrTrustSession` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reclamation`
--
ALTER TABLE `reclamation`
  MODIFY `idReclamation` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `strategiesproposees`
--
ALTER TABLE `strategiesproposees`
  MODIFY `idStrategie` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `strategies_proposees`
--
ALTER TABLE `strategies_proposees`
  MODIFY `idStrategie` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `idSupportMessage` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `idTransaction` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5136;

--
-- AUTO_INCREMENT pour la table `trusted_mobile_devices`
--
ALTER TABLE `trusted_mobile_devices`
  MODIFY `idTrustedDevice` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `idUser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `idAction` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=841;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `cashback`
--
ALTER TABLE `cashback`
  ADD CONSTRAINT `fk_cashback_partenaire` FOREIGN KEY (`idPartenaire`) REFERENCES `partenaire` (`idPartenaire`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cashback_transaction` FOREIGN KEY (`idTransaction`) REFERENCES `transactions` (`idTransaction`) ON DELETE CASCADE;

--
-- Contraintes pour la table `cashback_entries`
--
ALTER TABLE `cashback_entries`
  ADD CONSTRAINT `fk_cashback_entries_partenaire` FOREIGN KEY (`id_partenaire`) REFERENCES `partenaire` (`idPartenaire`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cashback_entries_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`idUser`) ON DELETE CASCADE;

--
-- Contraintes pour la table `coffrevirtuel`
--
ALTER TABLE `coffrevirtuel`
  ADD CONSTRAINT `fk_coffre_compte` FOREIGN KEY (`idCompte`) REFERENCES `compte` (`idCompte`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_coffre_user` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE SET NULL;

--
-- Contraintes pour la table `compte`
--
ALTER TABLE `compte`
  ADD CONSTRAINT `fk_compte_user` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `credit`
--
ALTER TABLE `credit`
  ADD CONSTRAINT `fk_credit_compte` FOREIGN KEY (`idCompte`) REFERENCES `compte` (`idCompte`) ON DELETE CASCADE;

--
-- Contraintes pour la table `garantiecredit`
--
ALTER TABLE `garantiecredit`
  ADD CONSTRAINT `fk_garantie_credit` FOREIGN KEY (`idCredit`) REFERENCES `credit` (`idCredit`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD CONSTRAINT `fk_reclamation_transaction` FOREIGN KEY (`idTransaction`) REFERENCES `transactions` (`idTransaction`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `roue_fortune_points`
--
ALTER TABLE `roue_fortune_points`
  ADD CONSTRAINT `fk_roue_user` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `strategiesproposees`
--
ALTER TABLE `strategiesproposees`
  ADD CONSTRAINT `strategiesproposees_ibfk_1` FOREIGN KEY (`idCoffre`) REFERENCES `coffrevirtuel` (`idCoffre`),
  ADD CONSTRAINT `strategiesproposees_ibfk_2` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`);

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transaction_compte` FOREIGN KEY (`idCompte`) REFERENCES `compte` (`idCompte`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transaction_user` FOREIGN KEY (`idUser`) REFERENCES `users` (`idUser`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
