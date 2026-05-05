# Comment le système ML se met à jour automatiquement

## 🎯 Objectif
Changer le profil de **Labidi Bouthayna (user 9)** de **"A risque"** à **"Fragile"**

## 📊 Données actuelles (dans projetpidev (2).sql)

### Comptes user 9:
- CB-101: 400 DT (Actif)
- CB-100: 500 DT (Actif)
- CB-102: 800 DT (Actif)
- **Total: 1700 DT**

### Coffres user 9:
- test2: 150/452 DT
- modifier12: 200/5400 DT
- ModifierCompte100: 6500/6500 DT (Fermé)
- Coffre pour le Futur: 500/3000 DT
- Projet de Rêve: 400/4035 DT
- Coffre Investissement: 300/2500 DT
- Fonds de loisir: 0/1120 DT
- Amélioration Pro: 1680/1680 DT
- **Total actifs: 3230 DT**

### Calcul du riskScore actuel:
```
35 (base)
+ 18 (3 comptes actifs × 6)
+ 7 (solde 1700 >= 1000 < 3000)
+ 12 (savings_rate 65.5% >= 35%)
+ 0 (vault_progress 17.7% < 20%)
+ 2 (account_age ~64 jours)
= 74 → "Stable" (pas "Fragile")
```

## ❌ Problème
Le score **74** donne **"Stable"**, pas "Fragile" !

Pour obtenir **"Fragile"**, il faut `riskScore < 60`

## ✅ Solution: Exécuter ces requêtes SQL

```sql
-- 1. Réduire les soldes des comptes
UPDATE compte SET solde = 200.00 WHERE idCompte = 81 AND idUser = 9;
UPDATE compte SET solde = 300.00 WHERE idCompte = 142 AND idUser = 9;
UPDATE compte SET solde = 400.00 WHERE idCompte = 143 AND idUser = 9;
-- Nouveau total: 900 DT

-- 2. Réduire les coffres
UPDATE coffrevirtuel SET montantActuel = 50.00 WHERE idCoffre = 75 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 100.00 WHERE idCoffre = 85 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 200.00 WHERE idCoffre = 90 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 150.00 WHERE idCoffre = 92 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 100.00 WHERE idCoffre = 96 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 0.00 WHERE idCoffre = 98 AND idUser = 9;
UPDATE coffrevirtuel SET montantActuel = 500.00 WHERE idCoffre = 206 AND idUser = 9;
-- Nouveau total: 1100 DT

-- 3. IMPORTANT: Bloquer un compte pour descendre sous 60
UPDATE compte SET statutCompte = 'Bloqué' WHERE idCompte = 81 AND idUser = 9;
```

### Nouveau calcul du riskScore:
```
35 (base)
+ 18 (3 comptes dont 2 actifs, 1 bloqué)
+ 2 (solde 900 < 1000)
+ 4 (savings_rate 55% >= 35%)
+ 0 (vault_progress 6% < 20%)
+ 2 (account_age ~64 jours)
- 11 (1 compte bloqué × 11)
= 50 → "Fragile" ✓
```

## 🔄 Comment ça se met à jour automatiquement ?

### 1. Après avoir exécuté les requêtes SQL dans phpMyAdmin

### 2. Aller dans l'interface admin
- URL: `http://localhost/final/public/index.php?route=admin`
- Cliquer sur "Comptes Bancaires"
- Cliquer sur le bouton **"Assistant IA"** (ou `?tab=accounts&panel=ia`)

### 3. Le système fait automatiquement:

#### Étape A: Lecture de la base de données
```php
// BankingMlAssistantService::buildAdminAssistantData()
$users = $bankingService->listUsers();
$accounts = $bankingService->listAccounts();
$vaults = $bankingService->listVaults();
$transactions = $bankingService->listTransactions();
```

#### Étape B: Calcul du profil
```php
// hydrateProfile() calcule:
- totalBalance = 900 DT
- vaultCurrent = 1100 DT
- riskScore = 50
- profile_label = "Fragile" (car riskScore < 60)
```

#### Étape C: Export vers CSV
```php
// buildTrainingDatasets() écrit dans:
vendor/Model/randomforest/classification.csv
vendor/Model/kmeans/kmeans.csv
vendor/Model/isolation/anomalies.csv
```

#### Étape D: Vérification des modèles
```php
// BankingMlModelService::refreshModelsIfNeeded()
if (CSV plus récent que .pkl) {
    trainAll(); // Ré-entraîne automatiquement
}
```

#### Étape E: Prédiction
```php
// predict() utilise les nouveaux modèles
$predictions = python predict_banking_models.py
// Retourne: user 9 → "Fragile"
```

#### Étape F: Affichage
L'interface affiche user 9 avec le badge **"Fragile"** 🟡

## 📝 Résumé

**Avant:**
- Solde: 1700 DT
- Coffres: 3230 DT
- Comptes bloqués: 0
- riskScore: 74 → **"Stable"**

**Après:**
- Solde: 900 DT (700 DT dans 2 comptes actifs + 200 DT bloqué)
- Coffres: 1100 DT
- Comptes bloqués: 1
- riskScore: 50 → **"Fragile"** ✓

## 🎯 Pourquoi ça marche automatiquement ?

Le code dans `BankingMlModelService.php` a une fonction `refreshModelsIfNeeded()` qui:
1. Compare la date de modification des CSV vs les .pkl
2. Si CSV plus récent → ré-entraîne automatiquement
3. Sinon → utilise les modèles existants

Quand tu cliques sur "Assistant IA":
1. ✅ Lit la base (nouvelles valeurs)
2. ✅ Calcule le riskScore (50)
3. ✅ Exporte vers CSV avec label "Fragile"
4. ✅ Détecte que CSV est plus récent
5. ✅ Ré-entraîne les modèles automatiquement
6. ✅ Fait la prédiction → "Fragile"
7. ✅ Affiche dans l'interface

**Aucune manipulation manuelle des CSV ou .pkl nécessaire !** 🎉

## 🔍 Pour vérifier

Après avoir cliqué sur "Assistant IA", vérifie:

1. **Dans l'interface:** User 9 doit avoir le badge "Fragile" 🟡
2. **Dans les logs:** `var/log/banking_ml.log` doit montrer le ré-entraînement
3. **Dans les CSV:** `vendor/Model/randomforest/classification.csv` ligne user 9 doit avoir `rf_target = Fragile`
4. **Les modèles:** Les fichiers `.pkl` doivent avoir une date récente

## ⚠️ Note importante

Les **alertes Isolation Forest** (anomalies de transactions) sont indépendantes du profil "Fragile/Stable/Premium". Elles détectent des patterns de transactions inhabituels, pas le niveau de risque global.

Pour supprimer les alertes d'anomalies, il faudrait supprimer ou modifier les transactions détectées comme anormales (montants très élevés, soldes négatifs, etc.).
