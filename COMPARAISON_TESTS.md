# 📊 Comparaison des Tests Unitaires : Compte vs Credit

## Vue d'ensemble

Ce document compare les implémentations des tests unitaires pour les entités **Compte** et **Credit**, démontrant l'application cohérente des principes de tests unitaires dans le projet.

## 📈 Statistiques Comparatives

| Métrique | CompteManager | CreditManager | Différence |
|----------|---------------|---------------|------------|
| **Nombre de tests** | 14 | 33 | +19 (+135%) |
| **Nombre d'assertions** | 21 | 44 | +23 (+109%) |
| **Règles métier** | 5 | 6 | +1 |
| **Temps d'exécution** | ~0.02s | ~0.02s | Similaire |
| **Taux de réussite** | 100% ✅ | 100% ✅ | Identique |

## 🎯 Règles Métier Comparées

### CompteManager (5 règles)
1. ✓ Le numéro de compte est obligatoire
2. ✓ Le solde ne peut pas être négatif
3. ✓ Le plafond de retrait doit être supérieur à zéro
4. ✓ Le statut doit être valide (Actif, Inactif, Bloqué)
5. ✓ Le type de compte doit être valide (Courant, Epargne)

### CreditManager (6 règles)
1. ✓ Le type de crédit est obligatoire
2. ✓ Le type de crédit doit être valide (12 types possibles)
3. ✓ Le montant demandé doit être supérieur à zéro
4. ✓ La durée doit être au moins de 1 mois
5. ✓ Le taux d'intérêt ne peut pas être négatif
6. ✓ Le statut doit être valide (En attente, Approuvé, Rejeté, En cours, Terminé)

## 📋 Détail des Tests

### CompteManager - 14 Tests

#### Tests Nominaux (2)
- ✔ `testCompteValide()` - Compte valide complet
- ✔ `testSoldeZeroEstValide()` - Solde à zéro accepté

#### Tests d'Erreur (6)
- ✔ `testNumeroCompteVide()` - Numéro vide rejeté
- ✔ `testNumeroCompteEspacesSeuls()` - Espaces seuls rejetés
- ✔ `testSoldeNegatif()` - Solde négatif rejeté
- ✔ `testPlafondRetraitZero()` - Plafond zéro rejeté
- ✔ `testPlafondRetraitNegatif()` - Plafond négatif rejeté
- ✔ `testStatutInvalide()` - Statut invalide rejeté
- ✔ `testTypeCompteInvalide()` - Type invalide rejeté

#### Tests avec Data Providers (6)
- ✔ `testStatutsValides()` - 3 statuts valides
- ✔ `testTypesValides()` - 2 types valides

---

### CreditManager - 33 Tests

#### Tests Nominaux (6)
- ✔ `testCreditValide()` - Crédit valide complet
- ✔ `testDureeUnMoisEstValide()` - Durée minimale acceptée
- ✔ `testTauxInteretZeroEstValide()` - Taux 0% accepté
- ✔ `testTauxInteretNullEstValide()` - Taux null accepté
- ✔ `testStatutNullEstValide()` - Statut null accepté

#### Tests d'Erreur (10)
- ✔ `testTypeCreditVide()` - Type vide rejeté
- ✔ `testTypeCreditEspacesSeuls()` - Espaces seuls rejetés
- ✔ `testTypeCreditInvalide()` - Type invalide rejeté
- ✔ `testMontantDemandeZero()` - Montant zéro rejeté
- ✔ `testMontantDemandeNegatif()` - Montant négatif rejeté
- ✔ `testMontantDemandeNull()` - Montant null rejeté
- ✔ `testDureeZero()` - Durée zéro rejetée
- ✔ `testDureeNegative()` - Durée négative rejetée
- ✔ `testDureeNull()` - Durée null rejetée
- ✔ `testTauxInteretNegatif()` - Taux négatif rejeté
- ✔ `testStatutInvalide()` - Statut invalide rejeté

#### Tests avec Data Providers (17)
- ✔ `testTypesValides()` - 12 types de crédit valides
- ✔ `testStatutsValides()` - 5 statuts valides

## 🔍 Analyse Détaillée

### Points Communs

1. **Structure identique**
   - Même organisation des fichiers
   - Même nomenclature des méthodes
   - Même utilisation de PHPUnit

2. **Patterns utilisés**
   - Data Providers pour tester plusieurs valeurs
   - Séparation tests nominaux / tests d'erreur
   - Messages d'exception explicites

3. **Qualité du code**
   - Documentation complète
   - Code lisible et maintenable
   - Couverture exhaustive des cas

### Différences Notables

1. **Complexité**
   - Credit a plus de types possibles (12 vs 2)
   - Credit a plus de statuts possibles (5 vs 3)
   - Credit a plus de règles de validation

2. **Flexibilité**
   - Credit accepte certaines valeurs null (taux, statut)
   - Compte est plus strict sur les valeurs obligatoires

3. **Nombre de tests**
   - Credit : 33 tests (plus complet)
   - Compte : 14 tests (plus concis)

## 📊 Résultats d'Exécution

### Exécution Individuelle

#### CompteManager
```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

..............                                                    14 / 14 (100%)

Time: 00:00.022, Memory: 10.00 MB

OK (14 tests, 21 assertions)
```

#### CreditManager
```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

.................................                                 33 / 33 (100%)

Time: 00:00.022, Memory: 10.00 MB

OK (33 tests, 44 assertions)
```

### Exécution Globale (tous les tests Service)
```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

...............................................                   47 / 47 (100%)

Time: 00:00.027, Memory: 10.00 MB

OK (47 tests, 65 assertions)
```

## 🎓 Concepts PHPUnit Appliqués

### 1. Assertions Utilisées
- `assertTrue()` - Vérifier qu'une validation réussit
- `expectException()` - Vérifier qu'une exception est levée
- `expectExceptionMessage()` - Vérifier le message d'erreur

### 2. Data Providers
```php
/** @dataProvider typesValidesProvider */
public function testTypesValides(string $type): void
{
    // Un seul test, plusieurs valeurs
}
```

### 3. Organisation des Tests
```
Tests Nominaux (cas valides)
    ↓
Tests d'Erreur (cas invalides)
    ↓
Tests avec Data Providers (variations)
```

## ✅ Bonnes Pratiques Appliquées

1. **Nommage clair** - Les noms de méthodes décrivent exactement ce qui est testé
2. **Un test = Un concept** - Chaque test vérifie une seule règle
3. **Messages explicites** - Les exceptions contiennent des messages clairs
4. **Couverture complète** - Tous les cas limites sont testés
5. **Documentation** - Commentaires et docblocks présents

## 🚀 Commandes Utiles

### Tester un service spécifique
```bash
php bin/phpunit tests/Service/CompteManagerTest.php
php bin/phpunit tests/Service/CreditManagerTest.php
```

### Tester avec affichage détaillé
```bash
php bin/phpunit tests/Service/CreditManagerTest.php --testdox
```

### Tester tous les services
```bash
php bin/phpunit tests/Service/
```

### Tester avec couverture de code
```bash
php bin/phpunit --coverage-html coverage/
```

## 📝 Conclusion

Les deux implémentations démontrent :

1. ✅ **Cohérence** - Même approche, même qualité
2. ✅ **Complétude** - Tous les cas sont couverts
3. ✅ **Maintenabilité** - Code clair et documenté
4. ✅ **Fiabilité** - 100% de tests réussis
5. ✅ **Scalabilité** - Facile d'ajouter de nouveaux tests

<cite index="1-7">Les tests unitaires permettent de valider les règles métier, de sécuriser les évolutions et d'améliorer la qualité globale du projet.</cite>

---

**Date :** 3 mai 2026  
**Framework :** Symfony 6.4  
**PHPUnit :** 11.5.55  
**Statut :** ✅ Production Ready
