#!/usr/bin/env php
<?php

/**
 * Script CLI pour exécuter le backtesting sur des données historiques.
 * 
 * Usage: 
 *   php cli/backtest.php --data=data/market_data.json --strategy=SMA_Cross
 *   php cli/backtest.php --days=365 --strategy=SMA_Cross (génère les données automatiquement)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Crypto4\Core\MarketDataGenerator;
use Crypto4\Services\BacktestService;
use Crypto4\Core\Logger;

// Configuration par défaut
$options = [
    'data_file' => null,
    'symbol' => 'BTCUSDT',
    'days' => 365,
    'start_price' => 50000.0,
    'volatility' => 0.03,
    'strategy' => 'SMA_Cross',
    'initial_capital' => 10000.0,
    'output' => 'reports/backtest_result.json'
];

// Parsing des arguments
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2));
        if (count($parts) === 2) {
            $key = str_replace('-', '_', $parts[0]);
            $options[$key] = $parts[1];
        }
    }
}

// Conversion des types
$options['days'] = (int)$options['days'];
$options['start_price'] = (float)$options['start_price'];
$options['volatility'] = (float)$options['volatility'];
$options['initial_capital'] = (float)$options['initial_capital'];

echo "=== Système de Backtesting Crypto 4.0 ===\n";
echo "Stratégie: {$options['strategy']}\n";
echo "Capital initial: {$options['initial_capital']} $\n";

$marketData = [];

// Charger ou générer les données
if (!empty($options['data_file']) && file_exists($options['data_file'])) {
    echo "Chargement des données depuis: {$options['data_file']}\n";
    $marketData = json_decode(file_get_contents($options['data_file']), true);
} else {
    echo "Génération de {$options['days']} jours de données pour {$options['symbol']}...\n";
    $marketData = MarketDataGenerator::generateHistory(
        $options['symbol'],
        $options['days'],
        $options['start_price'],
        $options['volatility']
    );
}

if (empty($marketData)) {
    Logger::error("Aucune donnée disponible");
    echo "❌ Erreur: Aucune donnée disponible\n";
    exit(1);
}

echo "Données chargées: " . count($marketData) . " bougies\n\n";

try {
    // Initialisation du service de backtest
    $backtest = new BacktestService($options['initial_capital']);
    
    echo "Exécution du backtesting avec la stratégie '{$options['strategy']}'...\n\n";
    
    // Exécution du backtest
    $result = $backtest->run($marketData, $options['strategy']);
    
    // Affichage des résultats
    echo "===========================================\n";
    echo "           RÉSULTATS DU BACKTEST          \n";
    echo "===========================================\n\n";
    
    echo "📊 Performance:\n";
    echo "  Capital final: " . number_format($result['final_capital'], 2) . " $\n";
    echo "  Profit/Perte: " . number_format($result['pnl'], 2) . " $\n";
    echo "  ROI: " . number_format($result['roi'], 2) . "%\n";
    echo "  Nombre de trades: {$result['trades_count']}\n";
    echo "  Taux de réussite: " . number_format($result['win_rate'], 2) . "%\n\n";
    
    if (isset($result['sharpe_ratio'])) {
        echo "📈 Indicateurs de risque:\n";
        echo "  Ratio de Sharpe: " . number_format($result['sharpe_ratio'], 2) . "\n";
        echo "  Drawdown max: " . number_format($result['max_drawdown'], 2) . "%\n\n";
    }
    
    // Sauvegarde du rapport
    $outputDir = dirname($options['output']);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    file_put_contents($options['output'], json_encode($result, JSON_PRETTY_PRINT));
    echo "💾 Rapport sauvegardé: " . realpath($options['output']) . "\n";
    
    // Génération d'un graphique texte simple
    echo "\n📉 Évolution du capital:\n";
    echo BacktestService::renderEquityCurve($result['equity_curve'], 60, 10);
    
} catch (Exception $e) {
    Logger::error("Erreur lors du backtest: " . $e->getMessage());
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
