# Système de Transactions Conditionnelles

## Vue d'ensemble

Système de gestion de transactions bancaires avec formulaire dynamique qui affiche des sections différentes selon le type de transaction sélectionné.

## Types de transactions

### 1. DEPOT
**Champs affichés:**
- Montant (requis)
- Description (optionnel)

**Logique:**
- ✅ Crédite le compte sélectionné
- ✅ Calcule automatiquement `soldeApres = soldeActuel + montant`
- ✅ Pas de vérification de solde minimum

### 2. RETRAIT
**Champs affichés:**
- Montant (requis)
- Description (optionnel)
- Affichage du solde disponible

**Logique:**
- ✅ Vérifie que `montant <= soldeActuel`
- ✅ Débite le compte : `soldeApres = soldeActuel - montant`
- ✅ Empêche les soldes négatifs
- ✅ Message d'erreur si solde insuffisant

### 3. VIREMENT
**Champs affichés:**
- Numéro de compte destinataire (requis)
- Nom du destinataire (requis)
- Email du destinataire (optionnel)
- Montant (requis)
- Description (optionnel)

**Logique:**
- ✅ Vérifie que le compte destinataire existe
- ✅ Vérifie que `montant <= soldeActuel`
- ✅ Débite le compte source : `soldeSource = soldeSource - montant`
- ✅ Crédite le compte destinataire : `soldeDestinataire = soldeDestinataire + montant`
- ✅ Transaction atomique (rollback si erreur)

### 4. PAIEMENT
**Champs affichés:**
- Catégorie (requis) - dropdown
- Montant à payer (requis)
- Bénéficiaire (optionnel)
- Email du bénéficiaire (optionnel)
- Description (optionnel)

**Logique:**
- ✅ Vérifie que `montantPaye <= soldeActuel`
- ✅ Débite le compte : `soldeApres = soldeActuel - montantPaye`
- ✅ Intégration Stripe (à implémenter)
- ✅ Empêche les soldes négatifs

## Structure de la base de données

### Table `transactions`

```sql
CREATE TABLE transactions (
    idTransaction INT PRIMARY KEY AUTO_INCREMENT,
    idCompte INT NOT NULL,
    idUser INT,
    categorie VARCHAR(50),
    dateTransaction VARCHAR(20),
    montant VARCHAR(255),  -- Chiffré
    typeTransaction VARCHAR(30),  -- DEPOT, RETRAIT, VIREMENT, PAIEMENT
    statutTransaction VARCHAR(30),
    soldeApres DECIMAL(12,2),
    description VARCHAR(255),
    montantPaye VARCHAR(255),  -- Chiffré
    idCompteDestinataire VARCHAR(255),  -- Numéro de compte (string)
    nomDestinataire VARCHAR(255),
    emailDestinataire VARCHAR(255),
    FOREIGN KEY (idCompte) REFERENCES compte(idCompte),
    FOREIGN KEY (idUser) REFERENCES users(idUser)
);
```

## Fichiers modifiés

### 1. Entité (`src/Entity/Transactions.php`)
```php
class Transactions
{
    public const TYPE_DEPOT = 'DEPOT';
    public const TYPE_RETRAIT = 'RETRAIT';
    public const TYPE_VIREMENT = 'VIREMENT';
    public const TYPE_PAIEMENT = 'PAIEMENT';
    
    private ?string $compteDestinataire = null;
    private ?string $nomDestinataire = null;
    private ?string $emailDestinataire = null;
    // ... autres propriétés
}
```

### 2. Formulaire (`src/Form/TransactionsType.php`)
- Tous les champs sont optionnels sauf `idCompte`, `typeTransaction`, `dateTransaction`
- Validation conditionnelle côté backend
- Classes CSS pour ciblage JavaScript

### 3. Service (`src/Service/BankingService.php`)
**Méthode `saveTransaction()`:**
- Validation stricte du type de transaction
- Calcul du nouveau solde selon le type
- Vérification du solde avant débit
- Gestion des virements (débit source + crédit destinataire)
- Transaction atomique avec rollback

### 4. Template (`templates/interfaces/portal/tabs/transactions_new.html.twig`)
**Structure:**
- Section base (toujours visible)
- Section DEPOT (conditionnelle)
- Section RETRAIT (conditionnelle)
- Section VIREMENT (conditionnelle)
- Section PAIEMENT (conditionnelle)
- Section résumé (toujours visible après sélection type)

**JavaScript:**
- Affichage/masquage dynamique des sections
- Calcul en temps réel du nouveau solde
- Validation côté client
- Gestion des champs requis dynamiques

## Flux de validation

### Côté Client (JavaScript)
1. Vérification que le type est sélectionné
2. Vérification que le compte source est sélectionné
3. Vérification du montant > 0
4. Vérification du solde suffisant (RETRAIT, VIREMENT, PAIEMENT)
5. Affichage du résumé avec nouveau solde

### Côté Serveur (PHP)
1. Validation du type de transaction
2. Vérification de l'existence du compte
3. Vérification du solde actuel
4. Calcul du nouveau solde
5. Validation des montants
6. Pour VIREMENT : vérification du compte destinataire
7. Transaction atomique en base de données

## Règles métier

### Règles générales
- ✅ Le solde ne peut jamais être négatif
- ✅ Les montants doivent être > 0
- ✅ La date ne peut pas être dans le futur
- ✅ Toutes les transactions sont tracées (user, date, montant)

### Règles spécifiques DEPOT
- ✅ Aucune limite de montant
- ✅ Pas de vérification de solde

### Règles spécifiques RETRAIT
- ✅ Montant <= solde actuel
- ✅ Message d'erreur explicite si solde insuffisant

### Règles spécifiques VIREMENT
- ✅ Compte destinataire doit exister
- ✅ Montant <= solde actuel
- ✅ Nom destinataire obligatoire
- ✅ Email destinataire optionnel mais validé si fourni
- ✅ Transaction atomique (les 2 comptes sont mis à jour ou aucun)

### Règles spécifiques PAIEMENT
- ✅ Catégorie obligatoire
- ✅ Montant <= solde actuel
- ✅ Intégration Stripe (à implémenter)
- ✅ Bénéficiaire et email optionnels

## Sécurité

### Chiffrement
- ✅ Les montants sont chiffrés en base de données
- ✅ Utilisation de `SecurityService->encryptAmount()`

### Validation
- ✅ Validation côté client (UX)
- ✅ Validation côté serveur (sécurité)
- ✅ Protection contre les injections SQL (prepared statements)
- ✅ Validation des emails
- ✅ Validation des montants (positifs, format décimal)

### Transactions atomiques
```php
$this->connection->beginTransaction();
try {
    // Opérations multiples
    $this->connection->commit();
} catch (\Throwable $e) {
    $this->connection->rollBack();
    throw $e;
}
```

## Intégration Stripe (PAIEMENT)

### À implémenter
1. Créer un `PaymentIntent` Stripe
2. Afficher le formulaire de paiement
3. Confirmer le paiement
4. Débiter le compte après confirmation
5. Enregistrer la transaction avec référence Stripe

### Exemple de flux
```php
// Dans BankingService
if ($type === 'PAIEMENT') {
    // 1. Créer PaymentIntent
    $paymentIntent = $stripeService->createPaymentIntent($paidAmount);
    
    // 2. Retourner client_secret au frontend
    // 3. Frontend confirme le paiement
    // 4. Webhook Stripe notifie le succès
    // 5. Débiter le compte et enregistrer la transaction
}
```

## Utilisation

### Créer une transaction DEPOT
```php
$data = [
    'idCompte' => 1,
    'typeTransaction' => 'DEPOT',
    'montant' => 100.00,
    'description' => 'Dépôt initial',
    'dateTransaction' => date('Y-m-d'),
];
$bankingService->saveTransaction($data);
```

### Créer une transaction VIREMENT
```php
$data = [
    'idCompte' => 1,
    'typeTransaction' => 'VIREMENT',
    'montant' => 50.00,
    'compteDestinataire' => '1234567890',
    'nomDestinataire' => 'Jean Dupont',
    'emailDestinataire' => 'jean@example.com',
    'description' => 'Virement mensuel',
    'dateTransaction' => date('Y-m-d'),
];
$bankingService->saveTransaction($data);
```

## Tests recommandés

### Tests unitaires
- ✅ Calcul du solde pour chaque type
- ✅ Validation des montants négatifs
- ✅ Validation des soldes insuffisants
- ✅ Vérification de l'existence du compte destinataire

### Tests d'intégration
- ✅ Transaction DEPOT complète
- ✅ Transaction RETRAIT avec solde insuffisant
- ✅ Transaction VIREMENT avec mise à jour des 2 comptes
- ✅ Transaction PAIEMENT avec Stripe
- ✅ Rollback en cas d'erreur

### Tests fonctionnels
- ✅ Affichage/masquage des sections
- ✅ Calcul en temps réel du nouveau solde
- ✅ Validation des formulaires
- ✅ Messages d'erreur appropriés

## Améliorations futures

1. **Notifications**
   - Email de confirmation après transaction
   - Notification push pour virements reçus

2. **Historique**
   - Affichage détaillé de l'historique
   - Export PDF/CSV

3. **Limites**
   - Plafonds de retrait/virement par jour
   - Alertes de sécurité

4. **Stripe**
   - Intégration complète
   - Gestion des remboursements
   - Webhooks

5. **UX**
   - Animations de transition
   - Confirmation modale avant validation
   - Scan de RIB pour virement
