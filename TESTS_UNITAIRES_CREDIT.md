# Tests Unitaires pour l'Entité Credit

## 📋 Résumé

Ce document présente l'implémentation des tests unitaires pour l'entité **Credit**, en suivant le même principe que l'entité **Compte**.

## 🎯 Règles Métier Validées

Les tests unitaires vérifient les règles métier suivantes pour l'entité Credit :

1. **Le type de crédit est obligatoire**
   - Le champ ne peut pas être vide ou contenir uniquement des espaces

2. **Le type de crédit doit être valide**
   - Valeurs acceptées : Professionnel, Immobilier, Auto, Consommation, Etudes, Travaux, Personnel, Hypotheque, Pret auto, Education, Sante, Autre

3. **Le montant demandé doit être supérieur à zéro**
   - Le montant ne peut pas être nul, zéro ou négatif

4. **La durée doit être au moins de 1 mois**
   - La durée ne peut pas être nulle, zéro ou négative
   - Minimum : 1 mois

5. **Le taux d'intérêt ne peut pas être négatif**
   - Le taux peut être nul (0%) ou null
   - Mais ne peut pas être négatif

6. **Le statut doit être valide (si défini)**
   - Valeurs acceptées : En attente, Approuvé, Rejeté, En cours, Terminé
   - Le statut peut être null

## 📁 Structure des Fichiers

```
src/
└── Service/
    └── CreditManager.php          # Service métier pour valider les crédits

tests/
└── Service/
    └── CreditManagerTest.php      # Tests unitaires pour CreditManager
```

## 🔧 Service Métier : CreditManager

Le service `CreditManager` contient une méthode `validate()` qui vérifie toutes les règles métier :

```php
<?php

namespace App\Service;

use App\Entity\Credit;

class CreditManager
{
    public function validate(Credit $credit): bool
    {
        // Validation des règles métier
        // Lance InvalidArgumentException si une règle est violée
        return true;
    }
}
```

## ✅ Tests Implémentés

### Tests Nominaux (Cas Valides)
- ✓ `testCreditValide()` - Vérifie qu'un crédit valide passe la validation
- ✓ `testDureeUnMoisEstValide()` - Vérifie qu'une durée de 1 mois est acceptée
- ✓ `testTauxInteretZeroEstValide()` - Vérifie qu'un taux de 0% est accepté
- ✓ `testTauxInteretNullEstValide()` - Vérifie qu'un taux null est accepté
- ✓ `testStatutNullEstValide()` - Vérifie qu'un statut null est accepté
- ✓ `testTypesValides()` - Vérifie tous les types de crédit valides (12 cas)
- ✓ `testStatutsValides()` - Vérifie tous les statuts valides (5 cas)

### Tests d'Erreur (Cas Invalides)
- ✓ `testTypeCreditVide()` - Type de crédit vide
- ✓ `testTypeCreditEspacesSeuls()` - Type de crédit avec espaces uniquement
- ✓ `testTypeCreditInvalide()` - Type de crédit non reconnu
- ✓ `testMontantDemandeZero()` - Montant à zéro
- ✓ `testMontantDemandeNegatif()` - Montant négatif
- ✓ `testMontantDemandeNull()` - Montant null
- ✓ `testDureeZero()` - Durée à zéro
- ✓ `testDureeNegative()` - Durée négative
- ✓ `testDureeNull()` - Durée null
- ✓ `testTauxInteretNegatif()` - Taux d'intérêt négatif
- ✓ `testStatutInvalide()` - Statut non reconnu

## 📊 Résultats des Tests

```bash
php bin/phpunit tests/Service/CreditManagerTest.php
```

**Résultat :**
```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

.................................                                 33 / 33 (100%)

Time: 00:02.013, Memory: 10.00 MB

OK (33 tests, 44 assertions)
```

### Détails des Tests
- **33 tests** exécutés
- **44 assertions** vérifiées
- **100% de réussite** ✅
- Temps d'exécution : 2.013 secondes

## 🔄 Comparaison avec CompteManager

| Critère | CompteManager | CreditManager |
|---------|---------------|---------------|
| Tests | 14 | 33 |
| Assertions | ~20 | 44 |
| Règles métier | 5 | 6 |
| Temps d'exécution | 0.126s | 2.013s |

## 📝 Utilisation des Data Providers

Les tests utilisent des **Data Providers** pour tester plusieurs valeurs avec une seule méthode :

```php
/** @dataProvider typesValidesProvider */
public function testTypesValides(string $type): void
{
    // Test avec chaque type valide
}

public static function typesValidesProvider(): array
{
    return [
        'type Professionnel' => ['Professionnel'],
        'type Immobilier'    => ['Immobilier'],
        // ... 10 autres types
    ];
}
```

## 🎓 Concepts PHPUnit Utilisés

1. **TestCase** - Classe de base pour les tests unitaires
2. **Assertions** - `assertTrue()`, `expectException()`, `expectExceptionMessage()`
3. **Data Providers** - Pour tester plusieurs valeurs avec une seule méthode
4. **Annotations** - `@dataProvider` pour lier les fournisseurs de données
5. **Organisation** - Séparation claire entre tests nominaux et tests d'erreur

## 🚀 Exécution des Tests

### Tester uniquement CreditManager
```bash
php bin/phpunit tests/Service/CreditManagerTest.php
```

### Tester tous les services
```bash
php bin/phpunit tests/Service/
```

### Tester tout le projet
```bash
php bin/phpunit
```

## ✨ Points Clés

1. **Validation stricte** - Chaque règle métier est testée individuellement
2. **Couverture complète** - Tests pour les cas valides ET invalides
3. **Messages clairs** - Les exceptions contiennent des messages explicites
4. **Maintenabilité** - Code organisé et bien documenté
5. **Réutilisabilité** - Le service peut être utilisé dans toute l'application

## 📚 Conclusion

<cite index="1-26,1-27">Les tests unitaires constituent la première étape de la phase de test. Ils permettent de valider la logique métier et de sécuriser le projet avant la livraison finale.</cite>

L'implémentation des tests pour l'entité Credit suit exactement le même principe que CompteManager, garantissant ainsi une cohérence dans la qualité du code et la validation des règles métier.

---

**Date de création :** 3 mai 2026  
**Auteur :** Tests Unitaires - Symfony 6.4  
**Statut :** ✅ Tous les tests passent avec succès
