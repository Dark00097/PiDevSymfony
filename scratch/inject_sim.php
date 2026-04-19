<?php
$file = 'C:/PiDevSymfony/templates/interfaces/portal/tabs/credits.html.twig';
$content = file_get_contents($file);

$htmlInject = <<<HTML
            </section>
            
            <section class="cr-tool-panel" data-tool-panel="simulate">
                <style>
                    .sim-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; }
                    .sim-card { background: var(--cr-bg-card, #fefefe); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--cr-border, #eaeaea); }
                    .sim-val { font-size: 1.8rem; font-weight: 700; color: var(--cr-primary, #4f46e5); }
                    .sim-val.risk { color: var(--cr-error, #ef4444); }
                    @media (max-width: 768px) { .sim-grid { grid-template-columns: 1fr; } }
                    .proj-bar { width: 100%; border-radius: 4px 4px 0 0; position: relative; transition: height 0.3s ease; }
                    .proj-bar-wrap { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 5px; height: 100%; }
                    .proj-bar span { font-size: 0.7rem; color: #666; text-align: center; }
                </style>
                <div class="sim-grid">
                    <div class="sim-card">
                        <h4 style="margin-bottom: 1rem; color: var(--cr-title);">Paramètres du crédit</h4>
                        <div style="margin-bottom: 1rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Montant (DT)</label>
                            <input type="number" id="sim-montant" value="20000" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Durée (Mois)</label>
                            <input type="number" id="sim-duree" value="36" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Taux Annuel (%)</label>
                            <input type="number" step="0.1" id="sim-taux" value="8" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <button id="sim-btn" style="width:100%; padding:0.8rem; border:none; border-radius:6px; background:var(--cr-primary, #4f46e5); color:#fff; font-weight:600; cursor:pointer;">Simuler Amortissement</button>
                    </div>
                    <div class="sim-card">
                        <div id="sim-result" hidden>
                            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eaeaea;">
                                <div>
                                    <div style="font-size:0.85rem; color:#666;">Mensualité estimée</div>
                                    <div class="sim-val" id="sim-mensualite">0.00 <span style="font-size:1rem;">DT</span></div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:0.85rem; color:#666;">Coût total du crédit</div>
                                    <div class="sim-val risk" id="sim-cout-total">0.00 <span style="font-size:1rem;">DT</span></div>
                                </div>
                            </div>
                            <h5 style="margin-bottom:0.5rem; color:var(--cr-title);">Tableau Prévisionnel</h5>
                            <div style="max-height: 250px; overflow-y: auto; border: 1px solid #eaeaea; border-radius: 8px;">
                                <table class="cr-tool-table" style="width:100%; text-align:right; border-collapse:collapse;">
                                    <thead style="position:sticky; top:0; background:#f9f9f9; z-index:1;">
                                        <tr>
                                            <th style="padding:0.5rem;">Mois</th>
                                            <th style="padding:0.5rem;">Part Intérêt</th>
                                            <th style="padding:0.5rem;">Part Capital</th>
                                            <th style="padding:0.5rem;">Capital Restant</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sim-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div id="sim-placeholder" style="display:flex; align-items:center; justify-content:center; height:100%; color:#aaa; font-style:italic;">
                            Remplissez les critères pour lancer la simulation.
                        </div>
                    </div>
                </div>
            </section>

            <section class="cr-tool-panel" data-tool-panel="projection">
                 <div class="sim-grid">
                    <div class="sim-card">
                        <h4 style="margin-bottom: 1rem; color: var(--cr-title);">Modèle de Projection</h4>
                        <div style="margin-bottom: 1rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Revenus mensuels (DT)</label>
                            <input type="number" id="proj-revenus" value="3000" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Dépenses courantes (DT)</label>
                            <input type="number" id="proj-depenses" value="1200" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Mensualité du crédit (DT)</label>
                            <input type="number" id="proj-mensualite" value="500" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display:block; font-size:0.85rem; margin-bottom:0.3rem;">Inflation annuelle Prévue (%)</label>
                            <input type="number" step="0.1" id="proj-inflation" value="6.5" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #ccc; outline:none;">
                        </div>
                        <button id="proj-btn" style="width:100%; padding:0.8rem; border:none; border-radius:6px; background:#10b981; color:#fff; font-weight:600; cursor:pointer;">Projeter sur 5 ans</button>
                    </div>
                    <div class="sim-card">
                        <div id="proj-result" hidden>
                             <h4 style="margin-bottom: 0.5rem; color: var(--cr-title);">Capacité Résiduelle Nette (5 Ans)</h4>
                             <p style="font-size:0.85rem; color:#666; margin-bottom:1rem;">Évaluation de votre reste-à-vivre face à l'inflation constante des dépenses.</p>
                             
                             <div id="proj-chart" style="display:flex; align-items:flex-end; gap:8px; height:200px; padding:1rem; border-radius:8px; background:#fafafa; border:1px solid #eaeaea;">
                                 <!-- Barres générées en JS -->
                             </div>
                             
                             <div id="proj-conclusion" style="margin-top: 1.5rem; padding: 1rem; border-radius: 8px; font-weight: 500; text-align:center;"></div>
                        </div>
                        <div id="proj-placeholder" style="display:flex; align-items:center; justify-content:center; height:100%; color:#aaa; font-style:italic;">
                            Définissez vos revenus/dépenses actuels pour projeter votre viabilité financière.
                        </div>
                    </div>
                 </div>
            </section>
        </div>
    </section>
HTML;

$jsInject = <<<JS
    /* ========================================================= */
    /* LOGIQUE SIMULATION ET PROJECTION                          */
    /* ========================================================= */
    const simFormat = (num) => Number(num).toFixed(2);
    
    // -- Simulateur d'Amortissement
    const simBtn = document.getElementById('sim-btn');
    if (simBtn) {
        simBtn.addEventListener('click', () => {
            const montant = parseNum(document.getElementById('sim-montant').value);
            const duree = parseNum(document.getElementById('sim-duree').value);
            const taux = parseNum(document.getElementById('sim-taux').value);
            
            if (montant <= 0 || duree <= 0 || taux <= 0) return alert('Veuillez entrer des valeurs valides supérieures à 0.');
            
            const mr = taux / 100 / 12;
            const mensualite = (montant * mr) / (1 - Math.pow(1 + mr, -duree));
            const coutTotal = (mensualite * duree) - montant;
            
            document.getElementById('sim-mensualite').innerHTML = simFormat(mensualite) + ' <span style="font-size:1rem;">DT</span>';
            document.getElementById('sim-cout-total').innerHTML = simFormat(coutTotal) + ' <span style="font-size:1rem;">DT</span>';
            
            let tbody = document.getElementById('sim-tbody');
            tbody.innerHTML = '';
            
            let capitalRestant = montant;
            for (let i = 1; i <= duree; i++) {
                const interet = capitalRestant * mr;
                const partCapital = mensualite - interet;
                capitalRestant -= partCapital;
                if (capitalRestant < 0) capitalRestant = 0;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `<td style="padding:0.4rem;border-bottom:1px solid #eee;">\${i}</td>
                                <td style="padding:0.4rem;border-bottom:1px solid #eee;">\${simFormat(interet)}</td>
                                <td style="padding:0.4rem;border-bottom:1px solid #eee;">\${simFormat(partCapital)}</td>
                                <td style="padding:0.4rem;border-bottom:1px solid #eee;">\${simFormat(capitalRestant)}</td>`;
                tbody.appendChild(tr);
            }
            
            document.getElementById('sim-placeholder').hidden = true;
            document.getElementById('sim-result').hidden = false;
        });
    }

    // -- Projection Financière
    const projBtn = document.getElementById('proj-btn');
    if (projBtn) {
        projBtn.addEventListener('click', () => {
            const revenus = parseNum(document.getElementById('proj-revenus').value);
            const depenses = parseNum(document.getElementById('proj-depenses').value);
            const mensualite = parseNum(document.getElementById('proj-mensualite').value);
            const inflation = parseNum(document.getElementById('proj-inflation').value) / 100;
            
            if (revenus <= 0 || depenses < 0) return alert('Revenus et dépenses invalides.');
            
            let chartDiv = document.getElementById('proj-chart');
            chartDiv.innerHTML = '';
            
            let currentDepenses = depenses;
            let currentRevenus = revenus; // on suppose les revenus augmentent très peu
            let bankruptYear = 0;
            
            let maxRest = revenus - depenses - mensualite;
            
            for(let annee = 1; annee <= 5; annee++) {
                // Projection purement empirique
                currentRevenus *= 1.02; // Augmentation salariale 2% moyenne
                currentDepenses *= (1 + inflation); // Les dépenses enflent
                
                let resteAVivre = currentRevenus - currentDepenses - mensualite;
                if (annee === 1 && resteAVivre > maxRest) maxRest = resteAVivre;
                
                if (resteAVivre < 0 && bankruptYear === 0) {
                    bankruptYear = annee;
                }
                
                // Dessin graphique
                let barPct = Math.max(0, (resteAVivre / maxRest) * 100);
                if (barPct > 100) barPct = 100;
                let color = resteAVivre < 500 ? '#ef4444' : (resteAVivre < 1000 ? '#f59e0b' : '#10b981');
                if (resteAVivre < 0) { color = '#7f1d1d'; barPct = 5; } // minimum bar for visual
                
                chartDiv.innerHTML += `
                    <div class="proj-bar-wrap">
                        <div class="proj-bar" style="height: \${barPct}%; background: \${color};" title="Année \${annee}: \${simFormat(resteAVivre)} DT"></div>
                        <span>A\${annee}</span>
                    </div>
                `;
            }
            
            let ccl = document.getElementById('proj-conclusion');
            if (bankruptYear > 0) {
                ccl.textContent = `Alerte Rouge : Déficit prévu dès l'année \${bankruptYear} à cause de l'inflation estimée.`;
                ccl.style.background = '#fee2e2';
                ccl.style.color = '#b91c1c';
            } else {
                ccl.textContent = `Plan viable ! Vous maintenez un solde positif sur 5 ans.`;
                ccl.style.background = '#d1fae5';
                ccl.style.color = '#047857';
            }
            
            document.getElementById('proj-placeholder').hidden = true;
            document.getElementById('proj-result').hidden = false;
        });
    }

  });
})();
</script>
JS;

$content = str_replace(
    "            </section>\n        </div>\n    </section>",
    $htmlInject,
    $content
);

$content = str_replace(
    "  });\n})();\n</script>",
    $jsInject,
    $content
);

file_put_contents($file, $content);
echo "Portal templates updated!";
