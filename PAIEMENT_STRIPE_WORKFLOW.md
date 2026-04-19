# Workflow de paiement Stripe

## 📋 Flux complet

### 1. Création de la transaction PAIEMENT

#### Frontend (JavaScript)
1. Utilisateur remplit le formulaire de paiement
2. Clique sur "Enregistrer et payer avec Stripe"
3. Validation JavaScript complète
4. Ajout du champ `action=transaction_save`
5. Soumission du formulaire

#### Backend (PHP)
1. `PortalController::index()` reçoit la requête POST
2. Appelle `handlePortalAction()`
3. `TransactionsController::handlePortalAction()` détecte `action=transaction_save`
4. Appelle `BankingService::saveTransaction()`

### 2. Enregistrement de la transaction

#### BankingService
```php
// Pour les paiements :
- statutTransaction = 'En attente' (pas 'Validée')
- soldeApres = soldeActuel (pas de débit immédiat)
- Le compte n'est PAS débité
```

**Pourquoi ?**
- Le paiement n'est pas encore confirmé par Stripe
- Le solde sera débité après confirmation du paiement
- Évite les débits en cas d'échec du paiement

### 3. Redirection vers Stripe

#### TransactionsController
```php
if ($typeTransaction === 'PAIEMENT' && $transactionId) {
    return [
        'type' => 'redirect_stripe',
        'transaction_id' => $transactionId
    ];
}
```

#### PortalController
```php
if ($flash['type'] === 'redirect_stripe') {
    return $this->redirectToRoute('portal_stripe_checkout', [
        'id' => $flash['transaction_id']
    ]);
}
```

### 4. Stripe Checkout

#### Route : `/portal/stripe/checkout/{id}`
- Récupère la transaction
- Crée une session Stripe Checkout
- Redirige vers Stripe

### 5. Paiement Stripe

- Utilisateur saisit ses informations de carte
- Stripe traite le paiement
- Redirection vers success ou cancel

### 6. Confirmation du paiement

#### Route : `/portal/stripe/success/{id}`
- Vérifie le statut du paiement
- Met à jour la transaction :
  - `statutTransaction = 'Validée'`
  - Débite le compte
  - Met à jour `soldeApres`

## 🔧 Modifications apportées

### 1. BankingService (`src/Service/BankingService.php`)

#### Méthode `saveTransaction()`
```php
// Statut conditionnel
'statutTransaction' => $type === 'PAIEMENT' 
    ? 'En attente' 
    : trim((string) ($data['statutTransaction'] ?? 'Validée')),

// Pas de débit pour les paiements
if ($type === 'PAIEMENT') {
    $newSolde = $oldSolde; // Pas de modification
}

// Pas de mise à jour du solde pour les paiements
if ($type !== 'PAIEMENT') {
    $this->connection->update('compte', ['solde' => $newSolde], ['idCompte' => $accountId]);
}

// Retourne l'ID de la transaction
return $id;
```

### 2. TransactionsController (`src/Controller/Sections/TransactionsController.php`)

#### Méthode `handlePortalAction()`
```php
case 'transaction_save':
    $typeTransaction = strtoupper(trim((string) $request->request->get('typeTransaction', '')));
    $transactionId = $bankingService->saveTransaction(...);
    
    // Redirection Stripe pour les paiements
    if ($typeTransaction === 'PAIEMENT' && $transactionId) {
        return [
            'type' => 'redirect_stripe',
            'transaction_id' => $transactionId
        ];
    }
    
    return ['type' => 'success', 'message' => 'Transaction enregistrée.'];
```

### 3. PortalController (`src/Controller/PortalController.php`)

#### Méthode `handlePortalAction()`
```php
// Retourne maintenant ?Response au lieu de void
private function handlePortalAction(...): ?Response

// Gère la redirection Stripe
if ($flash['type'] === 'redirect_stripe') {
    return $this->redirectToRoute('portal_stripe_checkout', [
        'id' => $flash['transaction_id']
    ]);
}
```

#### Méthode `index()`
```php
// Gère le retour de handlePortalAction
$redirectResponse = $this->handlePortalAction(...);

if ($redirectResponse instanceof Response) {
    return $redirectResponse;
}
```

### 4. Template (`templates/interfaces/portal/tabs/transactions.html.twig`)

#### Bouton Stripe
```html
<button type="button" id="btn-payer-stripe" ...>
    <i class="fa-solid fa-lock"></i> Enregistrer et payer avec Stripe
</button>
```

#### JavaScript
```javascript
btnPayerStripe.addEventListener('click', function(e) {
    // Validation complète
    // Ajout du champ action
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'transaction_save';
    form.appendChild(actionInput);
    
    // Soumission
    form.submit();
});
```

## 🐛 Débogage

### Logs JavaScript (Console navigateur)
```
Bouton Stripe cliqué
Type sélectionné: PAIEMENT
Soumission du formulaire...
```

### Logs PHP (Serveur)
```
TransactionsController: Type de transaction = PAIEMENT
TransactionsController: Transaction ID = 123
TransactionsController: Redirection vers Stripe pour transaction #123
PortalController: Flash reçu - Type: redirect_stripe
PortalController: Redirection vers Stripe pour transaction #123
```

### Vérifications
1. ✅ Le formulaire a bien `name="typeTransaction" value="PAIEMENT"`
2. ✅ Le champ `action=transaction_save` est ajouté
3. ✅ La transaction est créée avec `statutTransaction='En attente'`
4. ✅ Le solde n'est pas débité
5. ✅ La redirection vers Stripe est déclenchée

## 📊 États de la transaction

| État | Statut | Solde débité | Action |
|------|--------|--------------|--------|
| **Création** | En attente | ❌ Non | Redirection Stripe |
| **Paiement réussi** | Validée | ✅ Oui | Retour page transactions |
| **Paiement échoué** | Échouée | ❌ Non | Message d'erreur |
| **Paiement annulé** | Annulée | ❌ Non | Retour page transactions |

## 🔄 Après confirmation Stripe

### Route success
```php
#[Route('/portal/stripe/success/{id}', name: 'portal_stripe_success')]
public function stripeSuccess(int $id, ...) {
    // 1. Récupérer la transaction
    // 2. Vérifier le paiement Stripe
    // 3. Mettre à jour la transaction
    // 4. Débiter le compte
    // 5. Rediriger avec message de succès
}
```

### Mise à jour nécessaire
```php
// Mettre à jour le statut
UPDATE transactions 
SET statutTransaction = 'Validée' 
WHERE idTransaction = ?

// Débiter le compte
UPDATE compte 
SET solde = solde - montantPaye 
WHERE idCompte = ?

// Mettre à jour soldeApres
UPDATE transactions 
SET soldeApres = (SELECT solde FROM compte WHERE idCompte = ?)
WHERE idTransaction = ?
```

## ✅ Checklist de test

- [ ] Créer une transaction PAIEMENT
- [ ] Vérifier que le statut est "En attente"
- [ ] Vérifier que le solde n'est pas débité
- [ ] Vérifier la redirection vers Stripe
- [ ] Compléter le paiement sur Stripe
- [ ] Vérifier le retour sur la page transactions
- [ ] Vérifier que le statut est "Validée"
- [ ] Vérifier que le solde est débité
- [ ] Tester un paiement annulé
- [ ] Tester un paiement échoué

## 🚀 Prochaines étapes

1. Tester la redirection Stripe
2. Vérifier les logs
3. Compléter un paiement test
4. Vérifier la mise à jour du statut
5. Vérifier le débit du compte
