-- ============================================
-- REQUÊTES SQL POUR METTRE À JOUR USER 9
-- Objectif: Changer de "A risque" à "Fragile"
-- ============================================

-- Les données actuelles dans la base sont déjà correctes:
-- User 9: 3 comptes, solde total 1700 DT, coffres 3230 DT
-- Mais le riskScore calculé = 74 → "Stable"

-- Pour obtenir "Fragile" (riskScore < 60), il faut réduire encore plus:

-- 1. Réduire davantage les soldes des comptes
UPDATE compte SET solde = 200.00 WHERE idCompte = 81 AND idUser = 9;
UPDATE compte SET solde = 300.00 WHERE idCompte = 142 AND idUser = 9;
UPDATE compte SET solde = 400.00 WHERE idCompte = 143 AND idUser = 9;
-- Nouveau total: 900 DT

-- 2. Réduire encore les coffres pour diminuer savings_rate
UPDATE coffrevirtuel SET montantActuel = 50.00 WHERE idCoffre = 75 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 100.00 WHERE idCoffre = 85 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 200.00 WHERE idCoffre = 90 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 150.00 WHERE idCoffre = 92 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 100.00 WHERE idCoffre = 96 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 0.00 WHERE idCoffre = 98 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 500.00 WHERE idCoffre = 206 AND idUser = 9;
-- Nouveau total coffres actifs: 1100 DT

-- 3. Vérifier les résultats
SELECT 
    'Comptes' as Type,
    SUM(solde) as Total,
    COUNT(*) as Nombre
FROM compte 
WHERE idUser = 9 AND statutCompte = 'Actif';

SELECT 
    'Coffres' as Type,
    SUM(montantActuel) as Total,
    COUNT(*) as Nombre
FROM coffrevirtuel 
WHERE idUser = 9 AND status = 'Actif';

-- Calcul attendu du riskScore:
-- 35 (base)
-- + 18 (3 comptes actifs)
-- + 2 (solde 900 < 1000)
-- + 0 (vault_progress = 1100/18187 = 6% < 20%)
-- + 4 (savings_rate = 1100/(900+1100) = 55% >= 35%)
-- + 2 (account_age ~64 jours)
-- = 35 + 18 + 2 + 0 + 4 + 2 = 61 → encore "Stable"

-- Pour vraiment obtenir "Fragile" (< 60), il faut soit:
-- Option A: Bloquer un compte (pénalité -11)
-- Option B: Réduire encore plus le solde

-- OPTION A (Recommandée): Bloquer un compte
UPDATE compte SET statutCompte = 'Bloqué' WHERE idCompte = 81 AND idUser = 9;
-- Nouveau calcul: 61 - 11 = 50 → "Fragile" ✓

-- OPTION B (Alternative): Réduire le solde à < 500 DT total
-- UPDATE compte SET solde = 100.00 WHERE idCompte = 81 AND idUser = 9;
-- UPDATE compte SET solde = 150.00 WHERE idCompte = 142 AND idUser = 9;
-- UPDATE compte SET solde = 200.00 WHERE idCompte = 143 AND idUser = 9;
-- Nouveau total: 450 DT
-- Calcul: 35 + 18 + 2 + 0 + 4 + 2 - 6 (solde < 500) = 55 → "Fragile" ✓

-- ============================================
-- APRÈS AVOIR EXÉCUTÉ CES REQUÊTES:
-- 1. Aller dans l'interface admin
-- 2. Cliquer sur "Comptes Bancaires"
-- 3. Cliquer sur "Assistant IA"
-- 4. Le système va automatiquement:
--    - Lire les nouvelles données
--    - Calculer riskScore = 50
--    - Exporter vers CSV avec label "Fragile"
--    - Ré-entraîner les modèles
--    - Afficher user 9 comme "Fragile"
-- ============================================
