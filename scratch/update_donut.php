<?php
$file = 'C:/PiDevSymfony/templates/interfaces/admin/tabs/credits.html.twig';
$content = file_get_contents($file);

// Strip HTML
$startHtml = strpos($content, '<!-- STATS PANEL INJECTED -->');
$endHtml = strpos($content, '<!-- END STATS PANEL -->');
if ($startHtml !== false && $endHtml !== false) {
    $content = substr_replace($content, '', $startHtml, ($endHtml + 24) - $startHtml);
}

// Strip JS
$startJs = strpos($content, '/* ========================================================= */
    /* LOGIQUE STATISTIQUES ADMIN                                */');
$endJs = strpos($content, '})();
</script>', $startJs);
if ($startJs !== false && $endJs !== false) {
    $content = substr_replace($content, '', $startJs, $endJs - $startJs);
}

$htmlInject = <<<HTML
    <!-- STATS PANEL INJECTED -->
    <div class="nx-acc__panel" id="nx-admin-stats-panel" style="display: none; margin-bottom: 2rem; border-top: 4px solid var(--na-blue);">
        <div class="nx-acc__panel-head" style="justify-content: space-between; padding: 1.5rem 1.5rem 0.5rem 1.5rem;">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #00B4A0; color: #fff; display:flex; align-items:center; justify-content:center; font-size: 1.2rem;">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                   <h3 style="margin:0; font-size: 1.2rem; color: var(--na-text);">Statistiques des Crédits & Garanties</h3>
                   <p style="margin:0; font-size: 0.85rem; color: var(--na-muted);" id="stat-main-subtitle">Chargement...</p>
                </div>
            </div>
            <button class="na-btn na-btn--ghost-dark" onclick="document.getElementById('nx-admin-stats-panel').style.display='none';"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="nx-stats-charts-row" style="display: flex; gap: 1.5rem; padding: 1.5rem; flex-wrap: wrap;">
            
            <div class="nx-chart-card" style="flex:1; min-width: 300px; background: var(--na-bg); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--na-border);">
                <div class="nx-chart-card-head" style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 1.5rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #00B4A0;"></span>
                    <strong style="color: var(--na-text);">Crédits par Statut</strong>
                </div>
                <div class="nx-chart-wrap" style="position: relative; width: 220px; height: 220px; margin: 0 auto 1.5rem auto;">
                    <canvas id="nx-chart-status-canvas"></canvas>
                </div>
                <div class="nx-chart-legend" id="stat-credit-status" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

            <div class="nx-chart-card" style="flex:1; min-width: 300px; background: var(--na-bg); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--na-border);">
                <div class="nx-chart-card-head" style="display:flex; align-items:center; gap:0.5rem; margin-bottom: 1.5rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #4F46E5;"></span>
                    <strong style="color: var(--na-text);">Progression Garanties vs Crédits</strong>
                </div>
                <div class="nx-chart-wrap" style="position: relative; width: 220px; height: 220px; margin: 0 auto 1.5rem auto;">
                    <canvas id="nx-chart-garantie-canvas"></canvas>
                </div>
                <div class="nx-chart-legend" id="stat-garantie-overview" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
            </div>

        </div>
    </div>
    <!-- END STATS PANEL -->
HTML;

$jsInject = <<<JS
    /* ========================================================= */
    /* LOGIQUE STATISTIQUES ADMIN                                */
    /* ========================================================= */
    const statsBtn = document.getElementById('nx-module-stats-btn');
    const statsPanel = document.getElementById('nx-admin-stats-panel');
    let chart1 = null, chart2 = null;

    if (statsBtn && statsPanel) {
        statsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isHidden = statsPanel.style.display === 'none';
            statsPanel.style.display = isHidden ? 'block' : 'none';
            
            if (isHidden) {
                const creditRows = Array.from(document.querySelectorAll('.nx-credit-row'));
                const garantieRows = Array.from(document.querySelectorAll('.nx-garantie-row'));
                
                const cTotal = creditRows.length;
                const gTotal = garantieRows.length;
                document.getElementById('stat-main-subtitle').textContent = `\${cTotal} crédits · \${gTotal} garanties enregistrées`;

                const countBy = (rows, datasetKey) => rows.reduce((acc, row) => {
                    let val = row.dataset[datasetKey];
                    if (!val || val.trim() === '') val = 'Non spécifié';
                    acc[val] = (acc[val] || 0) + 1;
                    return acc;
                }, {});

                const sumBy = (rows, groupKey, sumKey) => rows.reduce((acc, row) => {
                     let val = row.dataset[groupKey];
                     if (!val || val.trim() === '') val = 'Non spécifié';
                     let amount = Number(row.dataset[sumKey] || 0);
                     acc[val] = (acc[val] || 0) + amount;
                     return acc;
                }, {});

                const statusCounts = countBy(creditRows, 'creditStatus');
                const statusAmounts = sumBy(creditRows, 'creditStatus', 'creditAmount');

                // Données Garanties vs Crédit pour le 2ème graphique
                const cTotalAmount = creditRows.reduce((a,b)=>a+Number(b.dataset.creditAmount||0),0);
                const gTotalAmount = garantieRows.reduce((a,b)=>a+Number(b.dataset.garantieEstimated||0),0);
                const unsecuredAmount = Math.max(0, cTotalAmount - gTotalAmount);

                const colorsPalette = ['#00B4A0', '#0D1B2A', '#E6A23C', '#F56C6C', '#4F46E5', '#8E44AD'];
                
                // Chart Status
                const sLabels = Object.keys(statusCounts);
                const sData = Object.values(statusCounts);
                const sColors = sLabels.map((l,i) => colorsPalette[i % colorsPalette.length]);
                
                const buildLegend = (labels, data, amounts, total, colors, containerId) => {
                    const container = document.getElementById(containerId);
                    if(!container) return;
                    container.innerHTML = '';
                    labels.forEach((lbl, i) => {
                        let pct = total > 0 ? Math.round((data[i] / total) * 100) : 0;
                        let color = colors[i];
                        let valStr = amounts ? amounts[lbl].toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DT' : data[i] + ' unités';
                        container.innerHTML += `
                           <div style="display:flex; align-items:center; background: #fff; border: 1px solid #f0f0f0; border-radius: 8px; padding: 0.8rem 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                               <span style="width:10px; height:10px; border-radius:50%; background:\${color}; margin-right: 0.8rem flex-shrink:0;"></span>
                               <span style="flex:1; font-weight:600; font-size:0.9rem; margin-left:10px;">\${lbl}</span>
                               <strong style="margin-right:1rem; font-size:0.9rem;">\${data[i]}</strong>
                               <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:0.75rem; color:#64748b; margin-right:1rem; min-width:40px; text-align:center;">\${pct}%</span>
                               <span style="color:#94a3b8; font-size:0.85rem; font-weight:500; min-width: 80px; text-align:right;">\${valStr}</span>
                           </div>
                        `;
                    });
                };

                buildLegend(sLabels, sData, statusAmounts, cTotal, sColors, 'stat-credit-status');

                // Render Chart 1
                if (window.Chart) {
                    if(chart1) chart1.destroy();
                    chart1 = new Chart(document.getElementById('nx-chart-status-canvas'), {
                        type: 'doughnut',
                        data: {
                            labels: sLabels,
                            datasets: [{
                                data: sData,
                                backgroundColor: sColors,
                                borderWidth: 3,
                                borderColor: 'var(--na-bg)'
                            }]
                        },
                        options: { cutout: '65%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                    });
                }

                // Render Chart 2 (Couverture)
                const gLabels = ['Capital Couvert (Garanties)', 'Capital Non Couvert'];
                const gData = [gTotalAmount, unsecuredAmount];
                const gColors = ['#10B981', '#F56C6C'];

                buildLegend(gLabels, [gTotal, cTotal], {
                    'Capital Couvert (Garanties)': gTotalAmount, 
                    'Capital Non Couvert': unsecuredAmount
                }, gTotal + cTotal, gColors, 'stat-garantie-overview');

                if (window.Chart) {
                    if(chart2) chart2.destroy();
                    chart2 = new Chart(document.getElementById('nx-chart-garantie-canvas'), {
                        type: 'doughnut',
                        data: {
                            labels: gLabels,
                            datasets: [{
                                data: gData,
                                backgroundColor: gColors,
                                borderWidth: 3,
                                borderColor: 'var(--na-bg)'
                            }]
                        },
                        options: { cutout: '65%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                    });
                }
            }
        });
    }

JS;

$content = str_replace("    </nav>", "\n    </nav>\n".$htmlInject, $content);
$content = str_replace("})();\n</script>", "\n".$jsInject."\n})();\n</script>", $content);

file_put_contents($file, $content);
echo "Admin stats panel updated with donut charts!";
