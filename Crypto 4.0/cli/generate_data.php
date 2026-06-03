#!/usr/bin/env php
<?php

/**
 * Script CLI pour générer des données de marché factices.
 * Utile pour le backtesting sans base de données réelle.
 * 
 * Usage: php cli/generate_data.php --symbol=BTCUSDT --days=365 --output=data.json
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Crypto4\Core\MarketDataGenerator;
use Crypto4\Core\Logger;

// Configuration par défaut
$options = [
    'symbol' => 'BTCUSDT',
    'days' => 365,
    'start_price' => 50000.0,
    'volatility' => 0.03,
    'output' => 'data/market_data.json'
];

// Parsing des arguments
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2));
        if (count($parts) === 2) {
            $key = str_replace('_', '-', $parts[0]);
            // Normalisation des clés
            $map = [
                'symbol' => 'symbol',
                'days' => 'days',
                'start-price' => 'start_price',
                'start_price' => 'start_price',
                'volatility' => 'volatility',
                'output' => 'output'
            ];
            if (isset($map[$key])) {
                $options[$map[$key]] = $parts[1];
            }
        }
    }
}

// Conversion des types
$options['days'] = (int)$options['days'];
$options['start_price'] = (float)$options['start_price'];
$options['volatility'] = (float)$options['volatility'];

echo "=== Générateur de Données de Marché ===\n";
echo "Symbole: {$options['symbol']}\n";
echo "Jours: {$options['days']}\n";
echo "Prix de départ: {$options['start_price']} $\n";
echo "Volatilité: " . ($options['volatility'] * 100) . "%\n";
echo "Fichier de sortie: {$options['output']}\n\n";

try {
    echo "Génération des données en cours...\n";
    $data = MarketDataGenerator::generateHistory(
        $options['symbol'],
        $options['days'],
        $options['start_price'],
        $options['volatility']
    );

    // Création du dossier de sortie si nécessaire
    $outputDir = dirname($options['output']);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // Sauvegarde en JSON
    file_put_contents($options['output'], json_encode($data, JSON_PRETTY_PRINT));

    echo "\n✅ Succès!\n";
    echo "Données générées: " . count($data) . " bougies\n";
    echo "Première bougie: {$data[0]['date']} ({$data[0]['open']} $)\n";
    echo "Dernière bougie: " . end($data)['date'] . " (" . end($data)['close'] . " $)\n";
    echo "Fichier sauvegardé: " . realpath($options['output']) . "\n";

    // Statistiques rapides
    $prices = array_column($data, 'close');
    $minPrice = min($prices);
    $maxPrice = max($prices);
    $avgPrice = array_sum($prices) / count($prices);
    
    echo "\n📊 Statistiques:\n";
    echo "  Prix minimum: " . number_format($minPrice, 2) . " $\n";
    echo "  Prix maximum: " . number_format($maxPrice, 2) . " $\n";
    echo "  Prix moyen: " . number_format($avgPrice, 2) . " $\n";
    echo "  Variation totale: " . number_format((end($prices) - $prices[0]) / $prices[0] * 100, 2) . "%\n";

} catch (Exception $e) {
    Logger::error("Erreur lors de la génération: " . $e->getMessage());
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
