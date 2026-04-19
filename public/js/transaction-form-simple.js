// GESTION DES TRANSACTIONS v6.0
document.addEventListener('DOMContentLoaded', function () {

    var form = document.getElementById('transaction-form');
    if (!form) return;

    console.log('[TNX] Formulaire trouvé, initialisation...');

    // ── Sections ──────────────────────────────────────────────────────────────
    var sectionEls = {
        DEPOT:    form.querySelector('.section-depot'),
        RETRAIT:  form.querySelector('.section-retrait'),
        VIREMENT: form.querySelector('.section-virement'),
        PAIEMENT: form.querySelector('.section-paiement')
    };

    function getActiveType() {
        var checked = form.querySelector('input[name="typeTransaction"]:checked');
        return checked ? checked.value : '';
    }

    function showSection(type) {
        Object.keys(sectionEls).forEach(function(key) {
            var s = sectionEls[key];
            if (s) s.style.display = (key === type) ? 'block' : 'none';
        });
    }

    // Init affichage
    var initType = getActiveType();
    if (initType) showSection(initType);

    form.querySelectorAll('input[name="typeTransaction"]').forEach(function(r) {
        r.addEventListener('change', function() {
            showSection(this.value);
        });
    });

    // ── Validation ────────────────────────────────────────────────────────────
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    function validateCompte() {
        var el = document.getElementById('f-compte');
        if (!el) return true;
        if (!el.value) { showMsg('msg-compte', '⚠ Veuillez sélectionner un compte source', 'error'); return false; }
        showMsg('msg-compte', '', '');
        return true;
    }

    function validateDate() {
        var el = document.getElementById('f-date');
        if (!el) return true;
        if (!el.value) { showMsg('msg-dateTransaction', '⚠ La date est obligatoire', 'error'); return false; }
        var d = new Date(el.value), today = new Date();
        today.setHours(0,0,0,0);
        if (d > today) { showMsg('msg-dateTransaction', '⚠ La date ne peut pas être dans le futur', 'error'); return false; }
        showMsg('msg-dateTransaction', '', '');
        return true;
    }

    function validateMontant(inputId, msgId) {
        var el = document.getElementById(inputId);
        if (!el) return true;
        var v = parseFloat(el.value);
        if (!el.value || isNaN(v)) { showMsg(msgId, '⚠ Le montant est obligatoire', 'error'); return false; }
        if (v <= 0) { showMsg(msgId, '⚠ Le montant doit être supérieur à 0', 'error'); return false; }
        if (v > 1000000) { showMsg(msgId, '⚠ Montant trop élevé', 'error'); return false; }
        showMsg(msgId, '✓ ' + v.toFixed(2) + ' DT', 'ok');
        return true;
    }

    function validateSoldeRetrait() {
        var montantEl = document.getElementById('f-amount-retrait');
        var compteEl  = document.getElementById('f-compte');
        if (!montantEl || !compteEl) return true;
        var montant = parseFloat(montantEl.value);
        var opt = compteEl.options[compteEl.selectedIndex];
        var solde = opt ? parseFloat(opt.dataset.solde || 0) : 0;
        if (!isNaN(montant) && montant > solde) {
            showMsg('msg-montant-retrait', '⚠ Solde insuffisant — disponible : ' + solde.toFixed(2) + ' DT', 'error');
            return false;
        }
        return true;
    }

    function validateCompteDestinataire() {
        var el = document.getElementById('f-compte-dest');
        if (!el) return true;
        var v = el.value.trim();
        if (!v) { showMsg('msg-compteDestinataire', '⚠ Le numéro de compte est obligatoire', 'error'); return false; }
        if (v.length < 3) { showMsg('msg-compteDestinataire', '⚠ Minimum 3 caractères', 'error'); return false; }
        showMsg('msg-compteDestinataire', '✓ Valide', 'ok');
        return true;
    }

    function validateNomDestinataire(inputId, msgId) {
        var el = document.getElementById(inputId);
        if (!el) return true;
        var v = el.value.trim();
        if (!v) { showMsg(msgId, '⚠ Le nom est obligatoire', 'error'); return false; }
        if (v.length < 2) { showMsg(msgId, '⚠ Minimum 2 caractères', 'error'); return false; }
        showMsg(msgId, '✓ Valide', 'ok');
        return true;
    }

    function validateEmail(inputId, msgId, required) {
        var el = document.getElementById(inputId);
        if (!el) return true;
        var v = el.value.trim();
        if (!v) {
            if (required) { showMsg(msgId, "⚠ L'email est obligatoire", 'error'); return false; }
            showMsg(msgId, '', '');
            return true;
        }
        if (!EMAIL_RE.test(v)) { showMsg(msgId, '⚠ Format email invalide', 'error'); return false; }
        showMsg(msgId, '✓ Email valide', 'ok');
        return true;
    }

    function validateCategorie() {
        var el = document.getElementById('f-cat');
        if (!el) return true;
        if (!el.value) { showMsg('msg-categorie', '⚠ Veuillez sélectionner une catégorie', 'error'); return false; }
        showMsg('msg-categorie', '', '');
        return true;
    }

    function showMsg(id, msg, type) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.style.display = msg ? 'block' : 'none';
        el.style.color = type === 'error' ? '#dc2626' : '#16a34a';
        el.style.fontSize = '.72rem';
        el.style.fontWeight = '600';
        el.style.marginTop = '4px';
    }

    function validateAll(type) {
        var ok = true;
        ok = validateCompte() && ok;
        ok = validateDate()   && ok;

        switch (type) {
            case 'DEPOT':
                ok = validateMontant('f-amount-depot', 'msg-montant-depot') && ok;
                break;
            case 'RETRAIT':
                ok = validateMontant('f-amount-retrait', 'msg-montant-retrait') && ok;
                ok = validateSoldeRetrait() && ok;
                break;
            case 'VIREMENT':
                ok = validateMontant('f-amount-virement', 'msg-montant-virement') && ok;
                ok = validateCompteDestinataire() && ok;
                ok = validateNomDestinataire('f-nom-dest', 'msg-nomDestinataire') && ok;
                ok = validateEmail('f-email-dest', 'msg-emailDestinataire', false) && ok;
                break;
            case 'PAIEMENT':
                ok = validateCategorie() && ok;
                ok = validateMontant('f-montant-paye', 'msg-montantPaye') && ok;
                ok = validateEmail('f-email-benef', 'msg-emailDestinataire', false) && ok;
                break;
        }

        if (!ok) console.warn('[TNX] Validation échouée pour type:', type);
        return ok;
    }

    // ── Solde affiché ─────────────────────────────────────────────────────────
    var compteEl = document.getElementById('f-compte');
    if (compteEl) {
        compteEl.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var solde = opt ? parseFloat(opt.dataset.solde || 0) : 0;
            var soldeEl = document.getElementById('solde-disponible');
            if (soldeEl) soldeEl.textContent = solde.toFixed(2) + ' DT';
        });
    }

    // ── Soumission ────────────────────────────────────────────────────────────
    form.addEventListener('submit', function(e) {
        var submitter = e.submitter;
        var action = submitter ? (submitter.value || submitter.getAttribute('value') || '') : '';

        console.log('[TNX] Submit - action:', action);

        if (action === 'transaction_delete') return;

        var type = getActiveType();
        console.log('[TNX] Type:', type);

        // Remplir champ caché montant depuis la section active
        var montantHidden  = document.getElementById('f-montant-hidden');
        var currencyHidden = document.getElementById('f-currency-hidden');
        var amountMap = {
            DEPOT:    { amount: 'f-amount-depot',    currency: 'f-currency-depot' },
            RETRAIT:  { amount: 'f-amount-retrait',  currency: 'f-currency-retrait' },
            VIREMENT: { amount: 'f-amount-virement', currency: 'f-currency-virement' },
            PAIEMENT: { amount: 'f-montant-paye',    currency: 'f-currency-paiement' }
        };

        if (amountMap[type] && montantHidden) {
            var amountEl   = document.getElementById(amountMap[type].amount);
            var currencyEl = document.getElementById(amountMap[type].currency);
            montantHidden.value  = amountEl   ? amountEl.value   : '';
            if (currencyHidden) currencyHidden.value = currencyEl ? currencyEl.value : 'TND';
            console.log('[TNX] montant=', montantHidden.value, 'currency=', currencyHidden ? currencyHidden.value : 'N/A');
        }

        if (!validateAll(type)) {
            e.preventDefault();
            var firstError = form.querySelector('[style*="dc2626"]');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        console.log('[TNX] OK - soumission');
    });

    console.log('[TNX] Script initialisé');
});
