# Migration des Types de Transactions

## Résumé des changements

Les types de transactions ont été standardisés pour améliorer la cohérence et la maintenabilité du système.

### Nouveaux types standardisés

| Type | Description | Impact sur le solde |
|------|-------------|---------------------|
| `DEPOT` | Dépôt d'argent sur le compte | ➕ Augmente le solde |
| `RETRAIT` | Retrait d'argent du compte | ➖ Diminue le solde |
| `VIREMENT` | Virement vers un autre compte | ➕ Augmente le solde du destinataire |
| `PAIEMENT` | Paiement d'un service/produit | ➖ Diminue le solde |

### Changements techniques

#### 1. Entité `Transactions`
- ✅ Ajout de constantes pour les types : `TYPE_DEPOT`, `TYPE_RETRAIT`, `TYPE_VIREMENT`, `TYPE_PAIEMENT`
- ✅ Modification du champ `compteDestinataire` : `Compte` (relation) → `string` (numéro de compte)

#### 2. Service `BankingService`
- ✅ Validation stricte des types de transactions
- ✅ Logique de calcul du solde basée sur le type
- ✅ Recherche du compte destinataire par numéro de compte (string)

#### 3. Formulaire `TransactionsType`
- ✅ Mise à jour des choix de types
- ✅ Champ `compteDestinataire` en TextType au lieu de EntityType

#### 4. Contrôleur `TransactionsController`
- ✅ Mise à jour des constantes `TRANSACTION_TYPES`
- ✅ Mise à jour des couleurs `TYPE_COLORS`
- ✅ Fonction `canonicalTransactionType()` pour mapper les anciens types

#### 5. Templates Twig
- ✅ `templates/interfaces/portal/tabs/transactions.html.twig` : Mise à jour des boutons radio
- ✅ Mise à jour de la logique de détection des débits/crédits

## Migration de la base de données

### Étape 1 : Appliquer la migration Doctrine

```bash
php bin/console doctrine:migrations:migrate
```

Cette migration modifie le champ `idCompteDestinataire` de INT vers VARCHAR(255).

### Étape 2 : Migrer les données existantes

```bash
php migrations/migrate_transaction_types.php
```

Ce script met à jour tous les types de transactions existants vers les nouveaux types standardisés.

### Mapping des anciens types

| Ancien type | Nouveau type |
|-------------|--------------|
| Versement, Credit, Entree | `DEPOT` |
| Debit, Sortie | `RETRAIT` |
| Virement, Transfer | `VIREMENT` |
| Paiement, Payment | `PAIEMENT` |

## Utilisation dans le code

### Créer une transaction

```php
use App\Entity\Transactions;

$data = [
    'typeTransaction' => Transactions::TYPE_DEPOT,
    'montant' => 100.00,
    'idCompte' => 1,
    'categorie' => 'Alimentation',
    'dateTransaction' => date('Y-m-d'),
    'statutTransaction' => 'Validée',
];

$bankingService->saveTransaction($data);
```

### Virement avec compte destinataire

```php
$data = [
    'typeTransaction' => Transactions::TYPE_VIREMENT,
    'montant' => 50.00,
    'idCompte' => 1,
    'compteDestinataire' => '1234567890', // Numéro de compte (string)
    'categorie' => 'Virement',
    'dateTransaction' => date('Y-m-d'),
];

$bankingService->saveTransaction($data);
```

## Tests recommandés

1. ✅ Créer un dépôt et vérifier que le solde augmente
2. ✅ Créer un retrait et vérifier que le solde diminue
3. ✅ Créer un virement et vérifier que les deux comptes sont mis à jour
4. ✅ Créer un paiement et vérifier que le solde diminue
5. ✅ Tester la validation des types invalides
6. ✅ Vérifier que les anciens types sont correctement migrés

## Rollback

Si vous devez revenir en arrière :

```bash
php bin/console doctrine:migrations:migrate prev
```

⚠️ **Attention** : Le rollback ne restaurera pas les types de transactions migrés. Vous devrez restaurer une sauvegarde de la base de données.
