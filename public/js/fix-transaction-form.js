// SOLUTION SIMPLE - Gestion du formulaire de transaction
console.log('🔧 Script de transaction chargé');

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('transaction-form');
    
    if (!form) {
        console.error('❌ Formulaire non trouvé');
        return;
    }
    
    console.log('✅ Formulaire trouvé');
    
    // Gérer l'affichage des sections selon le type de transaction
    const typeRadios = form.querySelectorAll('input[name="typeTransaction"]');
    const sections = {
        depot: form.querySelector('.section-depot'),
        retrait: form.querySelector('.section-retrait'),
        virement: form.querySelector('.section-virement'),
        paiement: form.querySelector('.section-paiement')
    };
    
    function showSection(type) {
        // Cacher toutes les sections
        Object.values(sections).forEach(s => {
            if (s) s.style.display = 'none';
        });
        
        // Afficher la section correspondante
        const sectionMap = {
            'DEPOT': sections.depot,
            'RETRAIT': sections.retrait,
            'VIREMENT': sections.virement,
            'PAIEMENT': sections.paiement
        };
        
        const target = sectionMap[type];
        if (target) {
            target.style.display = 'block';
            console.log('📋 Section affichée:', type);
        }
    }
    
    // Écouter les changements de type
    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            showSection(this.value);
        });
    });
    
    // Initialiser l'affichage
    const checked = form.querySelector('input[name="typeTransaction"]:checked');
    if (checked) {
        showSection(checked.value);
    }
    
    // Validation simple avant soumission
    form.addEventListener('submit', function(e) {
        const action = e.submitter?.value || e.submitter?.getAttribute('value');
        
        console.log('📤 Soumission du formulaire - Action:', action);
        
        // Permettre la suppression sans validation
        if (action === 'transaction_delete') {
            return true;
        }
        
        // Validation minimale pour l'enregistrement
        const type = form.querySelector('input[name="typeTransaction"]:checked')?.value;
        const compte = form.querySelector('#f-compte')?.value;
        const date = form.querySelector('#f-date')?.value;
        
        if (!type) {
            e.preventDefault();
            alert('⚠️ Veuillez sélectionner un type de transaction');
            return false;
        }
        
        if (!compte) {
            e.preventDefault();
            alert('⚠️ Veuillez sélectionner un compte');
            return false;
        }
        
        if (!date) {
            e.preventDefault();
            alert('⚠️ Veuillez sélectionner une date');
            return false;
        }
        
        console.log('✅ Validation OK - Soumission autorisée');
        console.log('Type:', type, 'Compte:', compte, 'Date:', date);
        
        return true;
    });
    
    console.log('✅ Gestionnaire de formulaire installé');
});
