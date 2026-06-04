<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique & Performance - NEO CRYPTO DASH</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #e2e8f0;
            --accent: #38bdf8;
            --success: #4ade80;
            --danger: #f87171;
            --warning: #fbbf24;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: var(--accent);
            border-bottom: 2px solid #334155;
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-size: 2em;
        }
        
        h2 {
            color: var(--accent);
            font-size: 1.4em;
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        /* Dashboard Grid */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--card);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            border: 1px solid #334155;
        }
        
        .stat-value {
            font-size: 2.2em;
            font-weight: bold;
            margin: 10px 0;
            color: white;
        }
        
        .stat-label {
            color: #94a3b8;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .good { color: var(--success); }
        .bad { color: var(--danger); }
        .warning { color: var(--warning); }
        
        /* Filters */
        .filters {
            background: #334155;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filters label {
            font-weight: bold;
            color: var(--accent);
        }
        
        select, input, button {
            background: #1e293b;
            border: 1px solid #475569;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.95em;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        button {
            background: var(--accent);
            cursor: pointer;
            font-weight: bold;
            border: none;
            transition: opacity 0.2s;
        }
        
        button:hover {
            opacity: 0.9;
        }
        
        /* Tables */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: var(--card);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        
        th {
            background: #334155;
            color: var(--accent);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #334155;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-ACHAT {
            background: rgba(74, 222, 128, 0.15);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .badge-VENTE {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .badge-ATTENTE {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid #94a3b8;
        }
        
        .badge-VALIDE {
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        
        .badge-ERREUR {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        
        .badge-EN_ATTENTE {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid #94a3b8;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-bottom: 30px;
        }
        
        /* Reviews Section */
        .review-item {
            border-bottom: 1px solid #334155;
            padding: 20px 0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .review-date {
            color: var(--accent);
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .review-period {
            font-size: 0.85em;
            color: #94a3b8;
        }
        
        .review-content {
            background: #334155;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .review-advice {
            margin-top: 10px;
            padding: 10px;
            background: rgba(56, 189, 248, 0.1);
            border-left: 3px solid var(--accent);
            border-radius: 4px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            select, button {
                width: 100%;
            }
        }
        
        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        
        .analysis-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85em;
            color: #94a3b8;
        }
        
        .analysis-text:hover {
            white-space: normal;
            position: absolute;
            background: var(--card);
            padding: 10px;
            border-radius: 6px;
            z-index: 100;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>📜 Historique & Agenda des Analyses</h1>

    <!-- KPIs Dashboard -->
    <div class="dashboard">
        <div class="card">
            <div class="stat-label">Fiabilité Globale du Bot</div>
            <div class="stat-value" id="global-accuracy">--%</div>
            <small style="color: #94a3b8;">Basé sur les audits passés</small>
        </div>
        <div class="card">
            <div class="stat-label">Total Analyses Archivées</div>
            <div class="stat-value" id="total-analyses">0</div>
            <small style="color: #94a3b8;">Depuis le lancement</small>
        </div>
        <div class="card">
            <div class="stat-label">Dernière Revue de Marché</div>
            <div class="stat-value" style="font-size: 1.3em;" id="last-review-date">--</div>
            <small id="last-review-picks" style="color: #94a3b8;">Top Picks: --</small>
        </div>
    </div>

    <!-- Graphique Évolution Score -->
    <div class="card">
        <h2>📈 Évolution des Scores et Prix</h2>
        <div class="filters">
            <label for="chartCryptoSelect">Crypto à analyser :</label>
            <select id="chartCryptoSelect">
                <option value="">Chargement...</option>
            </select>
            <button onclick="loadChart()">Actualiser Graphique</button>
        </div>
        <div class="chart-container">
            <canvas id="scoreChart"></canvas>
        </div>
    </div>

    <!-- Tableau de Fiabilité par Crypto -->
    <div class="card">
        <h2>🏆 Performance par Crypto (Fiabilité)</h2>
        <div class="table-container">
            <table id="reliabilityTable">
                <thead>
                    <tr>
                        <th>Crypto</th>
                        <th>Total Audits</th>
                        <th>Succès</th>
                        <th>Échecs</th>
                        <th>Taux de Réussite</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" class="loading">Chargement des données...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historique Détaillé -->
    <div class="card">
        <h2>🕒 Timeline des Analyses</h2>
        <div class="filters">
            <div>
                <label for="filterCrypto">Crypto :</label>
                <select id="filterCrypto">
                    <option value="all">Toutes</option>
                </select>
            </div>
            <div>
                <label for="filterConseil">Conseil :</label>
                <select id="filterConseil">
                    <option value="all">Tous</option>
                    <option value="ACHAT">Achat</option>
                    <option value="VENTE">Vente</option>
                    <option value="ATTENTE">Attente</option>
                </select>
            </div>
            <div>
                <label for="filterValidite">Statut :</label>
                <select id="filterValidite">
                    <option value="all">Tout</option>
                    <option value="validé">Validé (Juste)</option>
                    <option value="erreur">Erreur (Faux)</option>
                    <option value="en_attente">En attente</option>
                </select>
            </div>
            <button onclick="loadHistory()" style="margin-top: 24px;">Filtrer</button>
        </div>
        <div class="table-container">
            <table id="historyTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Crypto</th>
                        <th>Score</th>
                        <th>Conseil</th>
                        <th>Prix Entrée</th>
                        <th>Prix Audit</th>
                        <th>Résultat</th>
                        <th>Analyse IA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="8" class="loading">Chargement de l'historique...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Section Agenda / Reviews -->
    <div class="card" style="margin-top: 30px;">
        <h2>📅 Agenda & Revues de Marché</h2>
        <div id="reviewsList">
            <div class="loading">Chargement des revues...</div>
        </div>
    </div>
</div>

<script>
// Variables globales
let scoreChartInstance = null;
let allCryptos = [];
let fullHistoryData = [];
let reliabilityData = [];
let reviewsData = [];

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    initializeData();
});

async function initializeData() {
    await loadAllData();
    populateCryptoSelects();
    loadChart();
    renderReliabilityTable();
    renderHistoryTable();
    renderReviews();
    updateDashboard();
}

function loadAllData() {
    // Les données sont injectées directement par PHP
    allCryptos = <?= json_encode($uniqueCryptos ?? []) ?>;
    fullHistoryData = <?= json_encode($fullHistory ?? []) ?>;
    reliabilityData = <?= json_encode($reliabilityStats ?? []) ?>;
    reviewsData = <?= json_encode($reviews ?? []) ?>;
    const globalStats = <?= json_encode($globalStats ?? ['total' => 0, 'accuracy' => 0]) ?>;
    
    document.getElementById('total-analyses').innerText = globalStats.total;
    document.getElementById('global-accuracy').innerText = globalStats.accuracy + '%';
    
    const accElement = document.getElementById('global-accuracy');
    if (globalStats.accuracy >= 60) {
        accElement.className = 'stat-value good';
    } else if (globalStats.accuracy >= 40) {
        accElement.className = 'stat-value warning';
    } else {
        accElement.className = 'stat-value bad';
    }
}

function populateCryptoSelects() {
    const chartSel = document.getElementById('chartCryptoSelect');
    const filterSel = document.getElementById('filterCrypto');
    
    // Vider les options existantes sauf la première
    chartSel.innerHTML = '';
    filterSel.innerHTML = '<option value="all">Toutes</option>';
    
    if (allCryptos.length === 0) {
        chartSel.innerHTML = '<option value="">Aucune donnée</option>';
        return;
    }
    
    allCryptos.forEach(c => {
        chartSel.add(new Option(c.symbol, c.crypto_id));
        filterSel.add(new Option(c.symbol, c.crypto_id));
    });
}

function loadChart() {
    const cryptoId = document.getElementById('chartCryptoSelect').value;
    if (!cryptoId) return;
    
    const ctx = document.getElementById('scoreChart').getContext('2d');
    
    // Filtrer les données pour cette crypto
    const historyForCrypto = fullHistoryData
        .filter(h => h.crypto_id === cryptoId)
        .slice(0, 30) // 30 derniers points
        .reverse(); // Ordre chronologique
    
    if (historyForCrypto.length === 0) {
        alert("Aucune donnée historique pour cette crypto");
        return;
    }
    
    // Détruire l'ancien graphique s'il existe
    if (scoreChartInstance) {
        scoreChartInstance.destroy();
    }
    
    // Normaliser les prix pour l'affichage sur le même axe Y que le score (optionnel)
    // Ici on utilise deux axes Y séparés
    const labels = historyForCrypto.map(d => {
        const date = new Date(d.created_at);
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit' });
    });
    
    const scores = historyForCrypto.map(d => d.score);
    const prices = historyForCrypto.map(d => parseFloat(d.price_at_analysis) || 0);
    
    scoreChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Score IA (0-100)',
                    data: scores,
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Prix (€)',
                    data: prices,
                    borderColor: '#fbbf24',
                    backgroundColor: 'rgba(251, 191, 36, 0.1)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    labels: { color: '#e2e8f0' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y.toFixed(2);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#94a3b8', maxRotation: 45 },
                    grid: { color: '#334155' }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Score IA',
                        color: '#38bdf8'
                    },
                    ticks: { color: '#38bdf8' },
                    grid: { color: '#334155' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Prix (€)',
                        color: '#fbbf24'
                    },
                    ticks: { 
                        color: '#fbbf24',
                        callback: function(value) {
                            return value.toFixed(2) + '€';
                        }
                    },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

function renderReliabilityTable() {
    const tbody = document.querySelector('#reliabilityTable tbody');
    
    if (reliabilityData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="loading">Aucune donnée d\'audit disponible</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    let totalSuccess = 0;
    let totalAudits = 0;
    
    reliabilityData.forEach(row => {
        totalSuccess += parseInt(row.correct_count || 0);
        totalAudits += parseInt(row.total_audits || 0);
        
        const accuracy = parseFloat(row.accuracy_percent || 0);
        let accuracyClass = 'bad';
        if (accuracy >= 60) accuracyClass = 'good';
        else if (accuracy >= 40) accuracyClass = 'warning';
        
        const successes = parseInt(row.correct_count || 0);
        const failures = (parseInt(row.total_audits || 0)) - successes;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${row.symbol}</strong></td>
            <td>${row.total_audits}</td>
            <td class="good">${successes}</td>
            <td class="bad">${failures}</td>
            <td class="${accuracyClass}" style="font-weight:bold; font-size:1.1em;">${accuracy}%</td>
        `;
        tbody.appendChild(tr);
    });
    
    // Mettre à jour le dashboard avec la fiabilité globale calculée
    if (totalAudits > 0) {
        const globalAcc = ((totalSuccess / totalAudits) * 100).toFixed(2);
        document.getElementById('global-accuracy').innerText = globalAcc + '%';
        
        const accElement = document.getElementById('global-accuracy');
        if (globalAcc >= 60) accElement.className = 'stat-value good';
        else if (globalAcc >= 40) accElement.className = 'stat-value warning';
        else accElement.className = 'stat-value bad';
    }
}

function renderHistoryTable() {
    const cryptoFilter = document.getElementById('filterCrypto').value;
    const conseilFilter = document.getElementById('filterConseil').value;
    const validiteFilter = document.getElementById('filterValidite').value;
    
    // Appliquer les filtres
    let filtered = fullHistoryData;
    
    if (cryptoFilter !== 'all') {
        filtered = filtered.filter(h => h.crypto_id === cryptoFilter);
    }
    if (conseilFilter !== 'all') {
        filtered = filtered.filter(h => h.conseil === conseilFilter);
    }
    if (validiteFilter !== 'all') {
        if (validiteFilter === 'validé') {
            filtered = filtered.filter(h => h.was_correct === 1);
        } else if (validiteFilter === 'erreur') {
            filtered = filtered.filter(h => h.was_correct === 0);
        } else if (validiteFilter === 'en_attente') {
            filtered = filtered.filter(h => h.was_correct === null || h.was_correct === undefined);
        }
    }
    
    const tbody = document.querySelector('#historyTable tbody');
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="loading">Aucune analyse ne correspond aux filtres</td></tr>';
        return;
    }
    
    tbody.innerHTML = '';
    
    // Limiter à 50 entrées pour la performance
    const displayData = filtered.slice(0, 50);
    
    displayData.forEach(row => {
        let badgeRes = '<span class="badge badge-EN_ATTENTE">En attente</span>';
        if (row.was_correct === 1) {
            badgeRes = '<span class="badge badge-VALIDE">SUCCÈS</span>';
        } else if (row.was_correct === 0) {
            badgeRes = '<span class="badge badge-ERREUR">ÉCHEC</span>';
        }
        
        const badgeConseil = `<span class="badge badge-${row.conseil}">${row.conseil}</span>`;
        
        const priceAudit = row.price_at_audit ? parseFloat(row.price_at_audit).toFixed(2) + ' €' : '-';
        const priceEntry = row.price_at_analysis ? parseFloat(row.price_at_analysis).toFixed(2) + ' €' : '-';
        
        // Formater la date
        const dateObj = new Date(row.created_at);
        const dateFormatted = dateObj.toLocaleDateString('fr-FR', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${dateFormatted}</td>
            <td><strong>${row.symbol}</strong></td>
            <td style="font-weight:bold; color:${getScoreColor(row.score)}">${row.score}</td>
            <td>${badgeConseil}</td>
            <td>${priceEntry}</td>
            <td>${priceAudit}</td>
            <td>${badgeRes}</td>
            <td>
                <div class="analysis-text" title="${escapeHtml(row.analyse_text || '')}">
                    ${escapeHtml(row.analyse_text || 'N/A')}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function getScoreColor(score) {
    if (score >= 70) return '#4ade80';
    if (score >= 50) return '#fbbf24';
    return '#f87171';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderReviews() {
    const container = document.getElementById('reviewsList');
    
    if (!reviewsData || reviewsData.length === 0) {
        container.innerHTML = '<div class="loading">Aucune revue de marché générée pour le moment.</div>';
        return;
    }
    
    container.innerHTML = '';
    
    reviewsData.forEach(r => {
        const dateObj = new Date(r.created_at);
        const dateFormatted = dateObj.toLocaleDateString('fr-FR', { 
            day: '2-digit', 
            month: 'long', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const periodStart = r.period_start ? new Date(r.period_start).toLocaleDateString('fr-FR') : 'N/A';
        const periodEnd = r.period_end ? new Date(r.period_end).toLocaleDateString('fr-FR') : 'N/A';
        
        let topPicksDisplay = 'Aucun';
        if (r.top_picks) {
            try {
                const picks = JSON.parse(r.top_picks);
                if (picks && picks.length > 0) {
                    topPicksDisplay = picks.join(', ');
                }
            } catch (e) {
                topPicksDisplay = r.top_picks;
            }
        }
        
        const reviewDiv = document.createElement('div');
        reviewDiv.className = 'review-item';
        reviewDiv.innerHTML = `
            <div class="review-header">
                <span class="review-date">📊 Revue du ${dateFormatted}</span>
                <span class="review-period">Période: ${periodStart} au ${periodEnd}</span>
            </div>
            <div>${r.review_text}</div>
            <div class="review-advice">
                <strong>💡 Conseil Global:</strong> ${r.global_advice}<br>
                <strong>🎯 Top Picks:</strong> ${topPicksDisplay}
            </div>
        `;
        container.appendChild(reviewDiv);
    });
    
    // Mettre à jour le dashboard avec la dernière revue
    if (reviewsData.length > 0) {
        const lastReview = reviewsData[0];
        const dateObj = new Date(lastReview.created_at);
        document.getElementById('last-review-date').innerText = dateObj.toLocaleDateString('fr-FR');
        
        let picksText = '--';
        if (lastReview.top_picks) {
            try {
                const picks = JSON.parse(lastReview.top_picks);
                if (picks && picks.length > 0) {
                    picksText = picks.join(', ');
                }
            } catch (e) {}
        }
        document.getElementById('last-review-picks').innerText = 'Top Picks: ' + picksText;
    }
}

function loadHistory() {
    renderHistoryTable();
}

function updateDashboard() {
    // Le dashboard est déjà mis à jour dans loadAllData et renderReliabilityTable
}
</script>

<?php
// --- INJECTION DES DONNÉES PHP DANS LE JAVASCRIPT ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/HistoryEngine.php';

try {
    $engine = new HistoryEngine();
    
    // Récupérer toutes les données nécessaires
    $uniqueCryptos = $engine->getUniqueCryptos();
    $fullHistory = $engine->getFullHistory();
    $reliabilityStats = $engine->getReliabilityStats();
    $reviews = $engine->getReviews();
    $globalStats = $engine->getGlobalStats();
    
} catch (Exception $e) {
    // En cas d'erreur, fournir des tableaux vides
    $uniqueCryptos = [];
    $fullHistory = [];
    $reliabilityStats = [];
    $reviews = [];
    $globalStats = ['total' => 0, 'accuracy' => 0];
    
    echo "<!-- Erreur lors du chargement des données: " . htmlspecialchars($e->getMessage()) . " -->";
}
?>

</body>
</html>
