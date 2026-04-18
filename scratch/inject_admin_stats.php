<?php
$file = 'C:/PiDevSymfony/templates/interfaces/admin/tabs/credits.html.twig';
$content = file_get_contents($file);

// 1. Inject HTML after </nav>
$htmlInject = <<<HTML
    </nav>
    
    <!-- STATS PANEL INJECTED -->
    <div class="nx-acc__panel" id="nx-admin-stats-panel" style="display: none; margin-bottom: 2rem; border-top: 4px solid var(--na-blue);">
        <div class="nx-acc__panel-head" style="justify-content: space-between;">
            <div>
                <h3><i class="fa-solid fa-chart-pie"></i> Vue d'ensemble Analytique</h3>
                <p>Répartition instantanée des crédits et garanties</p>
            </div>
            <button class="na-btn na-btn--ghost-dark" onclick="document.getElementById('nx-admin-stats-panel').style.display='none';"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="display: flex; gap: 2rem; padding: 1.5rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <h5 style="color: var(--na-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem;">Répartition par Statut (Crédits)</h5>
                <div id="stat-credit-status" style="display: flex; flex-direction: column; gap: 0.8rem;"></div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h5 style="color: var(--na-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem;">Répartition par Type (Crédits)</h5>
                <div id="stat-credit-type" style="display: flex; flex-direction: column; gap: 0.8rem;"></div>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <h5 style="color: var(--na-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem;">Capital Couvert (Garanties)</h5>
                <div id="stat-garantie-overview" style="display: flex; flex-direction: column; gap: 1rem;"></div>
            </div>
        </div>
    </div>
    <!-- END STATS PANEL -->
HTML;

$content = str_replace(
    "    </nav>",
    $htmlInject,
    $content
);

// 2. Inject JS before })();
$jsInject = <<<JS
    /* ========================================================= */
    /* LOGIQUE STATISTIQUES ADMIN                                */
    /* ========================================================= */
    const statsBtn = document.getElementById('nx-module-stats-btn');
    const statsPanel = document.getElementById('nx-admin-stats-panel');
    if (statsBtn && statsPanel) {
        statsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isHidden = statsPanel.style.display === 'none';
            statsPanel.style.display = isHidden ? 'block' : 'none';
            
            if (isHidden) {
                const creditRows = Array.from(document.querySelectorAll('.nx-credit-row'));
                const garantieRows = Array.from(document.querySelectorAll('.nx-garantie-row'));
                
                const countBy = (rows, datasetKey) => rows.reduce((acc, row) => {
                    let val = row.dataset[datasetKey];
                    if (!val || val.trim() === '') val = 'Non spécifié';
                    acc[val] = (acc[val] || 0) + 1;
                    return acc;
                }, {});

                const statusCounts = countBy(creditRows, 'creditStatus');
                const typeCounts = countBy(creditRows, 'creditType');
                const totalCredits = creditRows.length || 1;

                const colors = ['var(--na-blue)', 'var(--na-green)', 'var(--na-gold)', 'var(--na-red)', 'var(--na-purple)'];

                const renderBars = (counts, total, containerId) => {
                    const container = document.getElementById(containerId);
                    if (!container) return;
                    container.innerHTML = '';
                    let i = 0;
                    Object.entries(counts).sort((a,b)=>b[1]-a[1]).forEach(([label, count]) => {
                        let pct = Math.round((count / total) * 100);
                        let color = colors[i % colors.length];
                        container.innerHTML += `
                            <div>
                                <div style="display:flex; justify-content:space-between; margin-bottom: 4px;">
                                    <span style="font-size:0.85rem; font-weight:600; color: var(--na-text);">\${label}</span>
                                    <span style="font-size:0.85rem; color:var(--na-muted);">\${count} (\${pct}%)</span>
                                </div>
                                <div style="width:100%; background:var(--na-border); border-radius:4px; height:6px; overflow:hidden;">
                                    <div style="width:\${pct}%; background:\${color}; height:100%; border-radius:4px;"></div>
                                </div>
                            </div>
                        `;
                        i++;
                    });
                };

                renderBars(statusCounts, totalCredits, 'stat-credit-status');
                renderBars(typeCounts, totalCredits, 'stat-credit-type');

                const gTotal = garantieRows.length;
                const gTotalEst = garantieRows.reduce((sum, r) => sum + Number(r.dataset.garantieEstimated || 0), 0);
                const gContainer = document.getElementById('stat-garantie-overview');
                if (gContainer) {
                    gContainer.innerHTML = `
                        <div style="padding: 1.2rem; background: var(--na-bg); border-radius: 8px; border-left: 4px solid var(--na-green);">
                            <div style="font-size: 2rem; color: var(--na-text); font-weight: 800; line-height:1;">\${gTotal}</div>
                            <div style="font-size: 0.8rem; color: var(--na-muted); margin-top:0.3rem;">Garanties Actives</div>
                        </div>
                        <div style="padding: 1.2rem; background: var(--na-bg); border-radius: 8px; border-left: 4px solid var(--na-gold);">
                            <div style="font-size: 1.6rem; color: var(--na-text); font-weight: 800; line-height:1;">\${gTotalEst.toLocaleString()} <span style="font-size:1rem;color:var(--na-muted);">DT</span></div>
                            <div style="font-size: 0.8rem; color: var(--na-muted); margin-top:0.3rem;">Valeur Globale Estimée</div>
                        </div>
                    `;
                }
            }
        });
    }

})();
</script>
JS;

$content = str_replace(
    "})();\n</script>",
    $jsInject,
    $content
);

file_put_contents($file, $content);
echo "Admin stats panel updated successfully!";
