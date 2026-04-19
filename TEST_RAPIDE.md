# 🧪 TEST RAPIDE - Vérification de la correction

## ✅ CORRECTION APPLIQUÉE

Le problème des **champs en double** a été résolu en désactivant (`disabled=true`) les inputs des sections cachées.

---

## 🚀 ÉTAPES DE TEST (5 minutes)

### 1️⃣ Vider le cache du navigateur
```
Ctrl + Shift + Delete
→ Cochez "Images et fichiers en cache"
→ Effacer
→ Fermez et rouvrez le navigateur
```

### 2️⃣ Aller sur le formulaire
```
http://localhost:8000/portal?tab=transactions
```

### 3️⃣ Ouvrir la console (F12)
Vérifiez que vous voyez:
```
🚀 Chargement du script de transaction (v3.0)...
✅ Formulaire trouvé
📂 Affichage section: DEPOT
✅ Section affichée et activée: DEPOT
✅ Script initialisé avec succès (v3.0)
```

**⚠️ Si vous voyez `(SANS VALIDATION)` ou `(v2.0)`, le cache n'est pas vidé!**

### 4️⃣ Test rapide dans la console
Copiez-collez ce code dans la console (F12):
```javascript
// Vérifier que les sections cachées sont disabled
document.querySelectorAll('input[name="montant"]').forEach((input, i) => {
    console.log(`Input ${i}: disabled=${input.disabled}, value=${input.value || '(vide)'}`);
});
```

**Résultat attendu:**
```
Input 0: disabled=true, value=(vide)    ← Section cachée
Input 1: disabled=true, value=(vide)    ← Section cachée
Input 2: disabled=true, value=(vide)    ← Section cachée
Input 3: disabled=false, value=(vide)   ← Section active (DEPOT)
```

### 5️⃣ Remplir le formulaire
- Compte: Sélectionnez n'importe quel compte
- Type: Laissez "Dépôt" (déjà sélectionné)
- Montant: `100`
- Devise: `EUR` (ou laissez TND)

### 6️⃣ Cliquer sur "Enregistrer"

### 7️⃣ Vérifier la console
Vous devriez voir:
```
📤 Soumission du formulaire
Action: transaction_save
✅ Sections désactivées sauf: DEPOT
📦 Données du formulaire:
  typeTransaction: DEPOT
  montant: 100
  currency: EUR
```

**✅ SUCCÈS si vous voyez UNE SEULE ligne pour `montant` et `currency`**

**❌ ÉCHEC si vous voyez plusieurs lignes pour `montant`**

---

## 🔍 VÉRIFICATION AVANCÉE

### Test 1: Vérifier le nombre de champs envoyés
```javascript
const form = document.getElementById('transaction-form');
const formData = new FormData(form);
const montants = formData.getAll('montant');
const currencies = formData.getAll('currency');
console.log('Nombre de montants:', montants.length, '→ Valeurs:', montants);
console.log('Nombre de devises:', currencies.length, '→ Valeurs:', currencies);
```

**Résultat attendu:**
```
Nombre de montants: 1 → Valeurs: ["100"]
Nombre de devises: 1 → Valeurs: ["EUR"]
```

### Test 2: Changer de section
1. Cliquez sur "Retrait"
2. Dans la console, tapez:
```javascript
document.querySelectorAll('input[name="montant"]').forEach((input, i) => {
    console.log(`Input ${i}: disabled=${input.disabled}`);
});
```

**Résultat attendu:** Seul l'input de la section "Retrait" doit être `disabled=false`

---

## ✅ RÉSULTAT ATTENDU

Après avoir cliqué sur "Enregistrer":
- ✅ La page se rafraîchit
- ✅ La transaction apparaît dans l'historique
- ✅ Le montant est correct (100)
- ✅ La devise est correcte (EUR)
- ✅ Aucune erreur dans la console

---

## 🐛 SI ÇA NE MARCHE PAS

### Problème 1: Le cache n'est pas vidé
**Symptôme:** Vous voyez `(SANS VALIDATION)` ou `(v2.0)` dans la console

**Solution:**
1. Fermez complètement le navigateur
2. Rouvrez-le
3. Ou testez en navigation privée (`Ctrl + Shift + N`)

### Problème 2: Plusieurs valeurs pour `montant`
**Symptôme:** `formData.getAll('montant')` retourne plusieurs valeurs

**Solution:**
1. Vérifiez que le script v3.0 est chargé
2. Ouvrez: `http://localhost:8000/js/transaction-form-simple.js?v=3.0`
3. Vérifiez que vous voyez `disableSection` et `enableSection`

### Problème 3: La transaction ne s'enregistre pas
**Symptôme:** La page se rafraîchit mais la transaction n'apparaît pas

**Solution:**
1. Vérifiez les logs Symfony: `tail -f var/log/dev.log`
2. Vérifiez que le montant est bien envoyé (console → Network → Payload)
3. Testez avec le formulaire simple: `/simple-transaction-test`

---

## 📞 SUPPORT

Si le test échoue, envoyez-moi:
1. Capture d'écran de la console (F12)
2. Résultat de `formData.getAll('montant')`
3. Résultat de la vérification des inputs disabled

---

## 🎉 CONCLUSION

**Si vous voyez `v3.0` dans la console et qu'il n'y a qu'UN SEUL montant envoyé, le problème est résolu!**

Le formulaire devrait maintenant fonctionner parfaitement. 🚀
