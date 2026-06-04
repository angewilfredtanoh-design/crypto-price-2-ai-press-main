#!/usr/bin/env php
<?php

/**
 * Exporteur de métriques Prometheus
 * Génère des métriques au format Prometheus pour Grafana
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Crypto4\Services\PrometheusMetricsExporter;
use Crypto4\Services\HealthCheckService;
use Crypto4\Core\Database;

// Mode de sortie
$mode = $argv[1] ?? 'stdout'; // stdout ou file
$outputFile = $argv[2] ?? (DATA_DIR ?? '/tmp') . '/metrics.prom';

try {
    $exporter = new PrometheusMetricsExporter('crypto4');
    
    // === MÉTRIQUES DE SANTÉ ===
    $healthCheck = new HealthCheckService();
    $healthCheck->runAll();
    $checks = $healthCheck->getChecks();
    
    foreach ($checks as $component => $info) {
        $statusValue = match($info['status']) {
            'healthy' => 1,
            'warning' => 0.5,
            'degraded' => 0.3,
            'critical' => 0,
            'unhealthy' => 0,
            'skipped' => -1,
            default => 0
        };
        
        $exporter->gauge(
            'component_health_status',
            $statusValue,
            ['component' => $component, 'status' => $info['status']]
        );
        
        if (isset($info['response_time_ms']) && $info['response_time_ms'] !== null) {
            $exporter->histogram(
                'component_response_time_seconds',
                $info['response_time_ms'] / 1000,
                [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1],
                ['component' => $component]
            );
        }
    }
    
    // === MÉTRIQUES BASE DE DONNÉES ===
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Nombre de trades
        $stmt = $pdo->query("SELECT COUNT(*) FROM trades");
        $tradeCount = $stmt->fetchColumn();
        $exporter->gauge('database_trades_total', $tradeCount);
        
        // Nombre de positions ouvertes
        $stmt = $pdo->query("SELECT COUNT(*) FROM positions WHERE status = 'open'");
        $openPositions = $stmt->fetchColumn();
        $exporter->gauge('database_open_positions', $openPositions);
        
        // P&L total
        $stmt = $pdo->query("SELECT SUM(pnl) FROM trades WHERE pnl IS NOT NULL");
        $totalPnl = $stmt->fetchColumn() ?: 0;
        $exporter->gauge('trading_pnl_total_eur', (float)$totalPnl);
        
    } catch (\Exception $e) {
        // DB non disponible
    }
    
    // === MÉTRIQUES SYSTÈME ===
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    $exporter->gauge('system_memory_usage_bytes', $memoryUsage);
    $exporter->gauge('system_memory_peak_bytes', $memoryPeak);
    
    $diskFree = disk_free_space(DATA_DIR ?? '/tmp');
    $diskTotal = disk_total_space(DATA_DIR ?? '/tmp');
    $exporter->gauge('system_disk_free_bytes', $diskFree);
    $exporter->gauge('system_disk_total_bytes', $diskTotal);
    
    // === MÉTRIQUES CACHE ===
    try {
        $cache = \Crypto4\Services\CacheService::getInstance();
        $stats = method_exists($cache, 'getStats') ? $cache->getStats() : [];
        
        if (isset($stats['hits'])) {
            $exporter->counter('cache_hits_total', $stats['hits']);
        }
        if (isset($stats['misses'])) {
            $exporter->counter('cache_misses_total', $stats['misses']);
        }
        if (isset($stats['keys_count'])) {
            $exporter->gauge('cache_keys_count', $stats['keys_count']);
        }
    } catch (\Exception $e) {
        // Cache non disponible
    }
    
    // Ajouter les descriptions
    $exporter
        ->help('component_health_status', 'Statut de santé des composants (1=healthy, 0.5=warning, 0=critical)')
        ->help('component_response_time_seconds', 'Temps de réponse des composants en secondes')
        ->help('database_trades_total', 'Nombre total de trades exécutés')
        ->help('database_open_positions', 'Nombre de positions actuellement ouvertes')
        ->help('trading_pnl_total_eur', 'Profit & Loss total en EUR')
        ->help('system_memory_usage_bytes', 'Mémoire RAM utilisée par le processus')
        ->help('system_disk_free_bytes', 'Espace disque disponible')
        ->help('cache_hits_total', 'Nombre total de hits du cache')
        ->help('cache_misses_total', 'Nombre total de misses du cache');
    
    // Sortie
    $output = $exporter->render();
    
    if ($mode === 'file') {
        file_put_contents($outputFile, $output);
        echo "✅ Métriques exportées vers: {$outputFile}\n";
        echo "📊 Total métriques: " . $exporter->count() . "\n";
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $output;
    }
    
} catch (\Exception $e) {
    fwrite(STDERR, "❌ Erreur: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
