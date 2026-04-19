# 🧪 GUIDE DE TEST - Formulaire de Transaction

## ✅ CHECKLIST DE VÉRIFICATION

### 1. Préparation
- [ ] Ouvrir le navigateur en mode navigation privée (pour éviter le cache)
- [ ] Se connecter au portail utilisateur
- [ ] Naviguer vers l'onglet "Transactions"
- [ ] Ouvrir la console du navigateur (F12)
- [ ] Ouvrir l'onglet "Network" (Réseau)

### 2. Test DEPOT (Dépôt)
- [ ] Sélectionner un compte dans "Compte source"
- [ ] Sélectionner la date d'aujourd'hui
- [ ] Cliquer sur le bouton radio "Dépôt"
- [ ] Vérifier que la section "Dépôt" s'affiche
- [ ] Entrer un montant: `100.00`
- [ ] Sélectionner la devise: `TND`
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**:
  - [ ] Console affiche: `✅ Validation OK - Soumission du formulaire`
  - [ ] Network affiche une requête POST vers `/portal?tab=transactions`
  - [ ] Status de la requête: `302` (redirection)
  - [ ] Flash message vert: "Transaction enregistrée avec succès"
  - [ ] Transaction apparaît dans la liste avec montant `+100.00 DT`

### 3. Test RETRAIT (Retrait)
- [ ] Cliquer sur "Nouvelle" ou rafraîchir la page
- [ ] Sélectionner un compte avec solde suffisant
- [ ] Cliquer sur le bouton radio "Retrait"
- [ ] Vérifier que la section "Retrait" s'affiche
- [ ] Entrer un montant: `50.00`
- [ ] Sélectionner la devise: `TND`
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**:
  - [ ] Transaction enregistrée avec montant `-50.00 DT`
  - [ ] Solde du compte diminué de 50 DT

### 4. Test VIREMENT (Virement)
- [ ] Cliquer sur "Nouvelle"
- [ ] Sélectionner un compte source
- [ ] Cliquer sur le bouton radio "Virement"
- [ ] Vérifier que la section "Virement" s'affiche
- [ ] Entrer le numéro de compte destinataire (existant)
- [ ] Entrer le nom du destinataire
- [ ] Entrer l'email du destinataire (optionnel)
- [ ] Entrer un montant: `75.00`
- [ ] Sélectionner la devise: `TND`
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**:
  - [ ] Transaction enregistrée
  - [ ] Solde du compte source diminué
  - [ ] Solde du compte destinataire augmenté

### 5. Test PAIEMENT (Paiement avec Stripe)
- [ ] Cliquer sur "Nouvelle"
- [ ] Sélectionner un compte
- [ ] Cliquer sur le bouton radio "Paiement"
- [ ] Vérifier que la section "Paiement" s'affiche
- [ ] Sélectionner une catégorie: `Factures`
- [ ] Entrer un montant à payer: `25.00`
- [ ] Sélectionner la devise: `TND`
- [ ] Entrer le nom du bénéficiaire (optionnel)
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**:
  - [ ] Console affiche: `TransactionsController: Redirection vers Stripe`
  - [ ] Redirection vers la page Stripe Checkout
  - [ ] Transaction créée avec statut "Non payé"

### 6. Test MULTI-DEVISE (Conversion EUR → TND)
- [ ] Cliquer sur "Nouvelle"
- [ ] Sélectionner un compte
- [ ] Cliquer sur "Dépôt"
- [ ] Entrer un montant: `100.00`
- [ ] Sélectionner la devise: `EUR`
- [ ] **VÉRIFIER**:
  - [ ] Un aperçu de conversion s'affiche automatiquement
  - [ ] Affiche le taux de change
  - [ ] Affiche les frais de conversion (0.5%)
  - [ ] Affiche le montant total en TND
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**:
  - [ ] Transaction enregistrée avec les champs:
    - [ ] `original_amount` = 100.00
    - [ ] `original_currency` = EUR
    - [ ] `exchange_rate` = (taux actuel)
    - [ ] `conversion_fee` = (0.5% du montant converti)
    - [ ] `montant` = (montant total en TND)

### 7. Test VALIDATION
- [ ] Essayer de soumettre sans sélectionner de compte
  - [ ] **VÉRIFIER**: Alert "Veuillez sélectionner un compte"
- [ ] Essayer de soumettre sans sélectionner de date
  - [ ] **VÉRIFIER**: Alert "Veuillez sélectionner une date"
- [ ] Essayer de soumettre sans entrer de montant
  - [ ] **VÉRIFIER**: Alert "Veuillez entrer un montant valide"
- [ ] Pour VIREMENT, essayer sans compte destinataire
  - [ ] **VÉRIFIER**: Alert "Veuillez remplir le compte et le nom du destinataire"
- [ ] Pour PAIEMENT, essayer sans catégorie
  - [ ] **VÉRIFIER**: Alert "Veuillez sélectionner une catégorie"

### 8. Test MODIFICATION
- [ ] Cliquer sur une transaction existante dans la liste
- [ ] **VÉRIFIER**: Le formulaire se remplit avec les données de la transaction
- [ ] Modifier le montant
- [ ] Cliquer sur "Enregistrer"
- [ ] **VÉRIFIER**: Transaction mise à jour avec le nouveau montant

### 9. Test SUPPRESSION
- [ ] Cliquer sur une transaction dans la liste
- [ ] Cliquer sur le bouton "Supprimer"
- [ ] Confirmer la suppression
- [ ] **VÉRIFIER**: Transaction supprimée de la liste

## 🐛 DÉBOGAGE

### Si le formulaire ne se soumet pas:

1. **Vérifier la console JavaScript**:
   ```
   Attendu:
   🚀 Chargement du script de transaction...
   ✅ Formulaire trouvé
   ✅ Script initialisé avec succès
   ```

2. **Vérifier le bouton submit**:
   - Ouvrir l'inspecteur (F12)
   - Sélectionner le bouton "Enregistrer"
   - Vérifier les attributs:
     ```html
     <button class="tnx-act-btn tnx-act-btn--save" 
             type="submit" 
             name="action" 
             value="transaction_save">
     ```

3. **Vérifier le formulaire**:
   ```html
   <form method="post" 
         class="tnx-form" 
         id="transaction-form" 
         novalidate
         action="/portal?tab=transactions">
   ```

4. **Vérifier les scripts chargés**:
   - Onglet "Sources" dans DevTools
   - Chercher `transaction-form-simple.js`
   - Vérifier qu'il n'y a PAS de `fix-transaction-form.js`

5. **Vérifier la requête POST**:
   - Onglet "Network"
   - Filtrer par "XHR" ou "Fetch"
   - Chercher une requête vers `/portal?tab=transactions`
   - Vérifier le payload:
     ```
     tab: transactions
     action: transaction_save
     compte: [ID]
     dateTransaction: [DATE]
     typeTransaction: [TYPE]
     montant: [MONTANT]
     currency: [DEVISE]
     ```

### Si la transaction n'apparaît pas dans la liste:

1. **Vérifier la pagination**:
   - Les nouvelles transactions apparaissent sur la page 1
   - Si vous êtes sur page 2, retourner à page 1

2. **Vérifier la base de données**:
   ```sql
   SELECT * FROM transactions 
   ORDER BY idTransaction DESC 
   LIMIT 10;
   ```

3. **Vérifier les logs PHP**:
   ```bash
   tail -f var/log/dev.log
   ```

### Si la conversion de devise ne fonctionne pas:

1. **Vérifier les colonnes de la base**:
   ```sql
   DESCRIBE transactions;
   ```
   - Doit contenir: `original_amount`, `original_currency`, `exchange_rate`, `conversion_fee`

2. **Vérifier les logs de conversion**:
   - Console PHP doit afficher:
     ```
     Currency conversion: 100 EUR → 330.5 TND (rate: 3.3, fee: 0.5)
     ```

## 📊 RÉSULTATS ATTENDUS

### Console JavaScript (succès):
```
🚀 Chargement du script de transaction...
✅ Formulaire trouvé
📂 Affichage section: DEPOT
✅ Section affichée: DEPOT
✅ Script initialisé avec succès
📤 Soumission du formulaire
Action: transaction_save
Type: DEPOT
Compte: 123
Date: 2026-04-18
Montant DEPOT: 100
✅ Validation OK - Soumission du formulaire
```

### Network (succès):
```
POST /portal?tab=transactions
Status: 302 Found
Location: /portal?tab=transactions
```

### Interface (succès):
```
✅ Transaction enregistrée avec succès.
```

### Base de données (succès):
```sql
SELECT idTransaction, typeTransaction, montant, original_currency, exchange_rate
FROM transactions
WHERE idTransaction = [LAST_ID];

-- Résultat:
-- idTransaction | typeTransaction | montant | original_currency | exchange_rate
-- 123          | DEPOT           | [encrypted] | TND            | 1.000000
```

## ✅ CRITÈRES DE SUCCÈS

- [ ] Tous les types de transactions (DEPOT, RETRAIT, VIREMENT, PAIEMENT) fonctionnent
- [ ] La validation JavaScript empêche les soumissions invalides
- [ ] Les requêtes POST sont envoyées au serveur
- [ ] Les transactions sont enregistrées dans la base de données
- [ ] Les flash messages s'affichent correctement
- [ ] Les transactions apparaissent dans la liste
- [ ] La conversion multi-devise fonctionne
- [ ] La redirection Stripe fonctionne pour les paiements
- [ ] La modification et suppression fonctionnent

---

**Date**: 18 avril 2026  
**Version**: 1.0  
**Statut**: ✅ PRÊT POUR TEST
