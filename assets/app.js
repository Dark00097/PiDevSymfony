import './bootstrap.js';

(() => {
    const normalize = (value) => String(value || '').toLowerCase().trim();

    const parsePositiveInt = (value) => {
        const parsed = Number.parseInt(String(value || '').trim(), 10);
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    };

    const pickFirstId = (selectors) => {
        for (const selector of selectors) {
            const node = document.querySelector(selector);
            if (!node) continue;
            const value = 'value' in node ? node.value : node.getAttribute('value');
            const id = parsePositiveInt(value);
            if (id > 0) return id;
        }
        return 0;
    };

    const findCreditsRoot = () => document.querySelector('.nx-acc[data-complement-route-template]');

    const resolveCreditId = () => {
        const direct = pickFirstId([
            '#nx-aiw-credit-select',
            '#nx-credit-id',
            '#nx-garantie-credit',
            'select[name="selectedCreditId"]',
            '.nx-ai-drawer__select[id*="credit"]',
            '.nx-ai-drawer__select[name*="credit"]',
        ]);
        if (direct > 0) return direct;

        const selectedRow = document.querySelector('.nx-credit-row.is-selected');
        const selectedId = parsePositiveInt(selectedRow?.dataset?.creditId || '');
        if (selectedId > 0) return selectedId;

        const fallbackRow = document.querySelector('.nx-credit-row[data-credit-id]');
        return parsePositiveInt(fallbackRow?.dataset?.creditId || '');
    };

    const resolveGarantieId = () => {
        const direct = pickFirstId([
            '#nx-aiw-garantie-select',
            '#nx-garantie-id',
            'select[name="selectedGarantieId"]',
            '.nx-ai-drawer__select[id*="garantie"]',
            '.nx-ai-drawer__select[name*="garantie"]',
        ]);
        if (direct > 0) return direct;

        const selectedRow = document.querySelector('.nx-garantie-row.is-selected');
        const selectedId = parsePositiveInt(selectedRow?.dataset?.garantieId || '');
        if (selectedId > 0) return selectedId;

        const fallbackRow = document.querySelector('.nx-garantie-row[data-garantie-id]');
        return parsePositiveInt(fallbackRow?.dataset?.garantieId || '');
    };

    const findComplementTrigger = (node) => {
        if (!(node instanceof HTMLElement)) return null;
        return node.closest('.nx-ai-conclusion__action, [data-aiw-decision], [data-aiw-action], button, a, [role="button"]');
    };

    const isComplementTrigger = (node) => {
        if (!(node instanceof HTMLElement)) return false;
        const trigger = findComplementTrigger(node);
        if (!(trigger instanceof HTMLElement)) return false;

        const action = normalize(trigger.dataset.aiwAction || trigger.dataset.action || '');
        if (action.includes('request-complement') || action.includes('complement')) return true;

        const decision = normalize(trigger.dataset.aiwDecision || '');
        if (decision === 'complement') return true;

        const cssClass = normalize(trigger.className || '');
        if (cssClass.includes('nx-ai-conclusion__action')) return true;

        const label = normalize(trigger.textContent || '');
        return label.includes('piece complementaire') || label.includes('pièce complémentaire');
    };

    const showFeedback = (message, isError) => {
        const feedback = document.getElementById('nx-aiw-feedback');
        if (feedback instanceof HTMLElement) {
            feedback.classList.remove('is-success', 'is-error');
            feedback.textContent = message;
            feedback.style.display = 'block';
            feedback.classList.add(isError ? 'is-error' : 'is-success');
            return;
        }
        window.alert(message);
    };

    const setLoading = (trigger, loading) => {
        if (!(trigger instanceof HTMLElement)) return;
        if (loading) {
            trigger.dataset.originalLabel = trigger.textContent || '';
            trigger.textContent = 'Envoi en cours...';
        } else if (trigger.dataset.originalLabel) {
            trigger.textContent = trigger.dataset.originalLabel;
        }
        if ('disabled' in trigger) trigger.disabled = !!loading;
    };

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (!isComplementTrigger(target)) return;

        const creditsRoot = findCreditsRoot();
        if (!(creditsRoot instanceof HTMLElement)) return;

        const trigger = findComplementTrigger(target);
        if (!(trigger instanceof HTMLElement)) return;

        event.preventDefault();
        event.stopPropagation();

        const routeTemplate = String(creditsRoot.dataset.complementRouteTemplate || '').trim();
        const csrf = String(creditsRoot.dataset.complementCsrf || '').trim();
        if (routeTemplate === '' || csrf === '') {
            showFeedback('Configuration manquante pour la demande de piece complementaire.', true);
            return;
        }

        const creditId = resolveCreditId();
        if (creditId <= 0) {
            showFeedback('Selectionnez un credit avant de demander une piece complementaire.', true);
            return;
        }

        const garantieId = resolveGarantieId();
        const requestUrl = routeTemplate.replace(/0$/, String(creditId));
        const message = `Votre dossier credit #${creditId} necessite une piece complementaire pour finaliser l'analyse.`;

        setLoading(trigger, true);
        try {
            const response = await fetch(requestUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    idGarantie: garantieId,
                    message,
                }),
            });

            const payload = await response.json();
            if (!response.ok || !payload?.ok) {
                throw new Error(String(payload?.message || 'Action impossible.'));
            }

            const creditStatus = document.getElementById('nx-credit-status');
            if (creditStatus instanceof HTMLSelectElement) {
                creditStatus.value = String(payload.creditStatus || 'Piece complementaire demandee');
                creditStatus.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const garantieStatus = document.getElementById('nx-garantie-status');
            if (garantieStatus instanceof HTMLSelectElement) {
                garantieStatus.value = 'En cours_verification';
                garantieStatus.dispatchEvent(new Event('change', { bubbles: true }));
            }

            showFeedback(String(payload.message || 'Demande envoyee.'), false);
        } catch (error) {
            const messageError = (error && error.message) ? error.message : 'Erreur lors de la demande.';
            showFeedback(messageError, true);
        } finally {
            setLoading(trigger, false);
        }
    }, true);
})();
