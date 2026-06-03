<?php
/**
 * virtual_portfolio.php
 * Page d'affichage du portefeuille virtuel de trading
 * Affiche : portefeuille, positions actives, historique des trades, performance
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

require_once ROOT_DIR . '/config.php';
require_once ROOT_DIR . '/lib/TradingLogic.php';

// Initialiser la BDD si nécessaire
ensureDatabaseInitialized();

$tradingLogic = new TradingLogic();
$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Récupérer les données
$portfolio = $tradingLogic->getPortfolio();
$positions = $tradingLogic->getActivePositions();
$trades = $tradingLogic->getTradeHistory(50);
$criteriaWeights = $tradingLogic->getCurrentCriteriaWeights();

// Mettre à jour les prix avant affichage
$tradingLogic->updatePositionPrices();
$tradingLogic->updatePortfolioValue();
$portfolio = $tradingLogic->getPortfolio(); // Recharger après mise à jour

$totalValue = $portfolio['total_value'];
$initialCapital = $portfolio['initial_capital'];
$performancePercent = $portfolio['performance_percent'];
$performanceAbsolute = $totalValue - $initialCapital;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portefeuille Virtuel - NEO CRYPTO DASH</title>
    <style>
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #111827;
            --light: #f3f4f6;
            --card-bg: #1f2937;
            --text-muted: #9ca3af;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e5e7eb;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(31, 41, 55, 0.8);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .card-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .card-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-subvalue {
            font-size: 1rem;
            color: var(--text-muted);
        }
        
        .positive { color: var(--success); }
        .negative { color: var(--danger); }
        .neutral { color: var(--warning); }
        
        .section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-buy { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-sell { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-win { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .badge-loss { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .criteria-bar {
            display: flex;
            height: 40px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .criteria-segment {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            transition: width 0.3s ease;
        }
        
        .segment-rsi { background: #3b82f6; }
        .segment-sparkline { background: #8b5cf6; }
        .segment-volume { background: #f59e0b; }
        .segment-marketcap { background: #10b981; }
        
        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #2563eb;
        }
        
        .last-update {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .card-value {
                font-size: 2rem;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🚀 Portefeuille Virtuel NEO</h1>
            <p class="subtitle">Trading automatique IA · Capital initial : <?= number_format($initialCapital, 0, ',', ' ') ?>€</p>
            <p class="last-update">Dernière mise à jour : <?= date('d/m/Y H:i:s') ?></p>
        </header>
        
        <!-- Tableau de bord -->
        <div class="dashboard-grid">
            <div class="card">
                <div class="card-title">Valeur Totale</div>
                <div class="card-value"><?= number_format($totalValue, 0, ',', ' ') ?>€</div>
                <div class="card-subvalue <?= $performanceAbsolute >= 0 ? 'positive' : 'negative' ?>">
                    <?= $performanceAbsolute >= 0 ? '+' : '' ?><?= number_format($performanceAbsolute, 0, ',', ' ') ?>€
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Performance</div>
                <div class="card-value <?= $performancePercent >= 0 ? 'positive' : 'negative' ?>">
                    <?= $performancePercent >= 0 ? '+' : '' ?><?= number_format($performancePercent, 2) ?>%
                </div>
                <div class="card-subvalue">Depuis le démarrage</div>
            </div>
            
            <div class="card">
                <div class="card-title">Cash Disponible</div>
                <div class="card-value"><?= number_format($portfolio['current_cash'], 0, ',', ' ') ?>€</div>
                <div class="card-subvalue"><?= count($positions) ?> positions actives</div>
            </div>
            
            <div class="card">
                <div class="card-title">Statistiques</div>
                <div class="card-value" style="font-size: 1.5rem;">
                    <span class="<?= ($portfolio['win_rate'] ?? 0) >= 50 ? 'positive' : 'negative' ?>">
                        <?= number_format($portfolio['win_rate'] ?? 0, 1) ?>%
                    </span>
                </div>
                <div class="card-subvalue">
                    <?= $portfolio['winning_trades'] ?? 0 ?> gains / <?= $portfolio['losing_trades'] ?? 0 ?> pertes<br>
                    Total: <?= $portfolio['total_trades'] ?? 0 ?> trades
                </div>
            </div>
        </div>
        
        <!-- Pondération des critères -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">⚖️ Pondération des Critères IA</h2>
                <button class="refresh-btn" onclick="location.reload()">Actualiser</button>
            </div>
            <p style="color: var(--text-muted); margin-bottom: 15px;">
                Ces poids sont ajustés automatiquement par l'IA après chaque trade perdant pour améliorer la stratégie.
            </p>
            <div class="criteria-bar">
                <div class="criteria-segment segment-rsi" style="width: <?= $criteriaWeights['rsi_weight'] ?>%">
                    RSI <?= number_format($criteriaWeights['rsi_weight'], 0) ?>%
                </div>
                <div class="criteria-segment segment-sparkline" style="width: <?= $criteriaWeights['sparkline_weight'] ?>%">
                    Trend <?= number_format($criteriaWeights['sparkline_weight'], 0) ?>%
                </div>
                <div class="criteria-segment segment-volume" style="width: <?= $criteriaWeights['volume_weight'] ?>%">
                    Volume <?= number_format($criteriaWeights['volume_weight'], 0) ?>%
                </div>
                <div class="criteria-segment segment-marketcap" style="width: <?= $criteriaWeights['market_cap_weight'] ?>%">
                    Cap <?= number_format($criteriaWeights['market_cap_weight'], 0) ?>%
                </div>
            </div>
            <?php if ($criteriaWeights['adjustment_reason']): ?>
                <p style="margin-top: 15px; font-size: 0.9rem; color: var(--text-muted);">
                    <em>Dernier ajustement : <?= $criteriaWeights['adjustment_reason'] ?></em>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Positions actives -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">📊 Positions Actives</h2>
                <span class="badge badge-buy"><?= count($positions) ?> positions</span>
            </div>
            
            <?php if (empty($positions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>Aucune position active</h3>
                    <p>L'IA recherche des opportunités d'achat (score ≥ 65)</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Crypto</th>
                            <th>Quantité</th>
                            <th>Prix Moyen</th>
                            <th>Prix Actuel</th>
                            <th>Valeur</th>
                            <th>P&L</th>
                            <th>P&L %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $pos): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pos['coin_symbol']) ?></strong><br><small style="color: var(--text-muted);"><?= htmlspecialchars($pos['coin_name']) ?></small></td>
                                <td><?= number_format($pos['quantity'], 6) ?></td>
                                <td><?= number_format($pos['avg_buy_price'], 2) ?>€</td>
                                <td><?= number_format($pos['current_price'], 2) ?>€</td>
                                <td><?= number_format($pos['current_value'], 0, ',', ' ') ?>€</td>
                                <td class="<?= $pos['unrealized_pnl'] >= 0 ? 'positive' : 'negative' ?>">
                                    <?= $pos['unrealized_pnl'] >= 0 ? '+' : '' ?><?= number_format($pos['unrealized_pnl'], 0, ',', ' ') ?>€
                                </td>
                                <td class="<?= $pos['pnl_percent'] >= 0 ? 'positive' : 'negative' ?>">
                                    <?= $pos['pnl_percent'] >= 0 ? '+' : '' ?><?= number_format($pos['pnl_percent'], 2) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Historique des trades -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">📜 Historique des Trades</h2>
                <span class="badge"><?= count($trades) ?> trades</span>
            </div>
            
            <?php if (empty($trades)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📜</div>
                    <h3>Aucun trade enregistré</h3>
                    <p>Les trades apparaîtront ici lorsque l'IA exécutera des achats ou ventes</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Crypto</th>
                            <th>Quantité</th>
                            <th>Prix</th>
                            <th>Total</th>
                            <th>Score</th>
                            <th>P&L</th>
                            <th>Justification IA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trades as $trade): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', $trade['timestamp']) ?></td>
                                <td><span class="badge badge-<?= $trade['action'] === 'BUY' ? 'buy' : 'sell' ?>"><?= $trade['action'] ?></span></td>
                                <td><strong><?= htmlspecialchars($trade['coin_symbol']) ?></strong></td>
                                <td><?= number_format($trade['quantity'], 6) ?></td>
                                <td><?= number_format($trade['price'], 2) ?>€</td>
                                <td><?= number_format($trade['total_value'], 0, ',', ' ') ?>€</td>
                                <td>
                                    <span class="<?= $trade['score_trigger'] >= 65 ? 'positive' : ($trade['score_trigger'] <= 35 ? 'negative' : 'neutral') ?>">
                                        <?= $trade['score_trigger'] ?>/100
                                    </span>
                                </td>
                                <td class="<?= ($trade['realized_pnl'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                                    <?= ($trade['realized_pnl'] !== null) ? (($trade['realized_pnl'] >= 0 ? '+' : '') . number_format($trade['realized_pnl'], 0, ',', ' ') . '€') : '-' ?>
                                </td>
                                <td style="max-width: 300px; font-size: 0.85rem; color: var(--text-muted);">
                                    <?= htmlspecialchars(substr($trade['ai_justification'] ?? 'N/A', 0, 100)) ?><?= strlen($trade['ai_justification'] ?? '') > 100 ? '...' : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
