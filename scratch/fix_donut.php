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

// Ensure Chart.js is loaded if not already
$chartLoad = "";
if (strpos($content, "chart.umd.min.js") === false) {
    $chartLoad = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>'."\n";
}

$htmlInject = <<<HTML
    <!-- STATS PANEL INJECTED -->
    <div class="nx-stats-panel" id="nx-admin-stats-panel">
      <div class="nx-stats-inner">
        <div class="nx-stats-header">
          <span class="nx-stats-header-icon"><i class="fa-solid fa-chart-pie"></i></span>
          <div>
            <h3>Statistiques des Crédits & Garanties</h3>
            <p id="stat-main-subtitle">Chargement...</p>
          </div>
          <button class="nx-stats-close" type="button"
                  onclick="document.getElementById('nx-admin-stats-panel').classList.remove('is-open')">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="nx-stats-charts-row">
          
          <div class="nx-chart-card">
            <div class="nx-chart-card-head">
              <span class="nx-chart-dot" style="background:#00B4A0"></span>
              <strong>Crédits par Statut</strong>
            </div>
            <div class="nx-chart-wrap">
              <canvas id="nx-chart-status-canvas" width="200" height="200"></canvas>
            </div>
            <div class="nx-chart-legend" id="stat-credit-status"></div>
          </div>

          <div class="nx-chart-card">
            <div class="nx-chart-card-head">
              <span class="nx-chart-dot" style="background:#6c63ff"></span>
              <strong>Progression Garanties vs Crédits</strong>
            </div>
            <div class="nx-chart-wrap">
              <canvas id="nx-chart-garantie-canvas" width="200" height="200"></canvas>
            </div>
            <div class="nx-chart-legend" id="stat-garantie-overview"></div>
          </div>

        </div>
      </div>
    </div>
    <style>
      .nx-stats-panel {
        display: none;
        background: var(--na-surface);
        border: 1px solid var(--na-border);
        border-radius: var(--na-radius);
        box-shadow: var(--na-shadow-lg);
        overflow: hidden;
        animation: nx-slide-down .25s ease;
        margin-bottom: 2rem;
      }
      .nx-stats-panel.is-open { display: block; }
      @keyframes nx-slide-down {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      .nx-stats-inner { padding: 1.4rem 1.6rem; }
      .nx-stats-header {
        display: flex; align-items: center; gap: 1rem;
        margin-bottom: 1.4rem; padding-bottom: 1rem;
        border-bottom: 1px solid var(--na-border);
      }
      .nx-stats-header-icon {
        width: 42px; height: 42px; border-radius: 12px;
        background: linear-gradient(135deg, #00B4A0, #0d9e93);
        display: grid; place-items: center; color: #fff; font-size: 1.1rem; flex-shrink: 0;
      }
      .nx-stats-header h3 { font-size: .95rem; font-weight: 800; color: var(--na-txt); margin: 0 0 .15rem; }
      .nx-stats-header p  { font-size: .73rem; color: var(--na-muted); margin: 0; }
      .nx-stats-close {
        margin-left: auto; background: var(--na-bg); border: 1px solid var(--na-border);
        border-radius: 8px; width: 32px; height: 32px;
        display: grid; place-items: center; cursor: pointer; color: var(--na-muted); font-size: .85rem; transition: all .2s;
      }
      .nx-stats-close:hover { background: var(--na-red); color: #fff; border-color: transparent; }

      .nx-stats-charts-row {
        display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;
      }
      .nx-chart-card {
        background: var(--na-bg); border: 1px solid var(--na-border);
        border-radius: 12px; padding: 1.1rem 1.2rem;
        display: flex; flex-direction: column; gap: .85rem;
      }
      .nx-chart-card-head {
        display: flex; align-items: center; gap: .55rem;
        font-size: .85rem; font-weight: 700; color: var(--na-txt);
      }
      .nx-chart-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
      .nx-chart-wrap {
        display: flex; justify-content: center;
        height: 200px;
      }
      .nx-chart-wrap canvas { max-height: 200px; }
      
      .nx-chart-legend { display: flex; flex-direction: column; gap: .45rem; }
      .nx-chart-legend-row {
        display: flex; align-items: center; gap: .55rem;
        padding: .5rem .75rem; border-radius: 8px;
        background: var(--na-surface); border: 1px solid var(--na-border);
        font-size: .78rem; transition: transform .15s;
      }
      .nx-chart-legend-row:hover { transform: translateX(4px); border-color: var(--lc); }
      .nx-chart-legend-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--lc); flex-shrink: 0; }
      .nx-chart-legend-label { flex: 1; color: var(--na-txt); font-weight: 600; }
      .nx-chart-legend-row strong { font-size: .9rem; font-weight: 900; color: var(--lc); }
      .nx-chart-legend-pct { font-size: .72rem; color: var(--na-muted); background: var(--na-bg); border-radius: 20px; padding: .1rem .5rem; }
      .nx-chart-legend-val { font-size: .72rem; color: var(--na-muted); margin-left: auto; }
    </style>
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
        // Change default behavior if needed
        statsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = statsPanel.classList.contains('is-open');
            if (isOpen) {
                statsPanel.classList.remove('is-open');
                return;
            }
            statsPanel.classList.add('is-open');
            
            const creditRows = Array.from(document.querySelectorAll('.nx-credit-row'));
            const garantieRows = Array.from(document.querySelectorAll('.nx-garantie-row'));
            
            const cTotal = creditRows.length;
            const gTotal = garantieRows.length;
            document.getElementById('stat-main-subtitle').textContent = `\${cTotal} crédit\${cTotal!=1?'s':''} · \${gTotal} garantie\${gTotal!=1?'s':''} enregistrée\${gTotal!=1?'s':''}`;

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

            const cTotalAmount = creditRows.reduce((a,b)=>a+Number(b.dataset.creditAmount||0),0);
            const gTotalAmount = garantieRows.reduce((a,b)=>a+Number(b.dataset.garantieEstimated||0),0);
            const unsecuredAmount = Math.max(0, cTotalAmount - gTotalAmount);

            // Exactly matching account colors: #00B4A0 for Active, #0A2540 for Fermé, #B8860B for Bloqué...
            // Let's use a nice mapped palette
            const colorsPalette = ['#00B4A0', '#0A2540', '#B8860B', '#ef4444', '#f97316'];
            
            // Chart Status
            const sLabels = Object.keys(statusCounts);
            const sData = Object.values(statusCounts);
            const sColors = sLabels.map((l,i) => {
                let lb = l.toLowerCase();
                if(lb.includes('accept') || lb.includes('actif')) return '#00B4A0';
                if(lb.includes('rejet') || lb.includes('refus')) return '#ef4444';
                if(lb.includes('attente')) return '#0A2540';
                if(lb.includes('cours')) return '#B8860B';
                return colorsPalette[i % colorsPalette.length];
            });
            
            const buildLegend = (labels, data, amounts, total, colors, containerId) => {
                const container = document.getElementById(containerId);
                if(!container) return;
                container.innerHTML = '';
                labels.forEach((lbl, i) => {
                    let pct = total > 0 ? ((data[i] / total) * 100).toFixed(1) : 0;
                    let color = colors[i];
                    let valStr = amounts ? amounts[lbl].toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DT' : data[i] + ' unités';
                    
                    // Same as .nx-chart-legend-row
                    container.innerHTML += `
                        <div class="nx-chart-legend-row" style="--lc:\${color}">
                            <span class="nx-chart-legend-dot"></span>
                            <span class="nx-chart-legend-label">\${lbl}</span>
                            <strong>\${data[i]}</strong>
                            <span class="nx-chart-legend-pct">\${pct}%</span>
                            <span class="nx-chart-legend-val">\${valStr}</span>
                        </div>
                    `;
                });
            };

            buildLegend(sLabels, sData, statusAmounts, cTotal, sColors, 'stat-credit-status');

            if (window.Chart) {
                if(chart1) chart1.destroy();
                chart1 = new Chart(document.getElementById('nx-chart-status-canvas'), {
                    type: 'doughnut',
                    data: {
                        labels: sLabels,
                        datasets: [{
                            data: sData,
                            backgroundColor: sColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                            hoverOffset: 12
                        }]
                    },
                    options: { 
                        cutout: '62%', 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false }, tooltip: { enabled: true } },
                        animation: { animateRotate: true, duration: 700 } 
                    }
                });
            }

            // Chart 2 (Couverture)
            const gLabels = ['Capital Couvert', 'Capital Non Couvert'];
            const gData = [gTotalAmount, unsecuredAmount];
            const gColors = ['#00B4A0', '#ef4444'];

            buildLegend(gLabels, [gTotalAmount > 0 ? 1 : 0, cTotalAmount > gTotalAmount ? 1 : 0], {
                'Capital Couvert': gTotalAmount, 
                'Capital Non Couvert': unsecuredAmount
            }, (gTotalAmount > 0 ? 1 : 0) + (cTotalAmount > gTotalAmount ? 1 : 0), gColors, 'stat-garantie-overview');

            if (window.Chart) {
                if(chart2) chart2.destroy();
                chart2 = new Chart(document.getElementById('nx-chart-garantie-canvas'), {
                    type: 'doughnut',
                    data: {
                        labels: gLabels,
                        datasets: [{
                            data: gData,
                            backgroundColor: gColors,
                            borderColor: '#fff',
                            borderWidth: 3,
                            hoverOffset: 12
                        }]
                    },
                    options: { 
                        cutout: '62%', 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false }, tooltip: { enabled: true } },
                        animation: { animateRotate: true, duration: 700 } 
                    }
                });
            }
        });
    }
JS;

$content = str_replace("    </nav>", "\n    </nav>\n".$htmlInject, $content);
$content = str_replace("})();\n</script>", "\n".$jsInject."\n})();\n</script>", $content);
$content = str_replace("<script>", $chartLoad."<script>", $content);

file_put_contents($file, $content);
echo "Perfectly matched stats layout!";
