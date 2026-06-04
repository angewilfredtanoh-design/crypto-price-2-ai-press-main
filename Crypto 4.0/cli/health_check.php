#!/usr/bin/env php
<?php

/**
 * Script de Health Check CLI
 * Vérifie l'état de santé du système et affiche un rapport
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Crypto4\Services\HealthCheckService;

// Configuration
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║         CRYPTO 4.0 - HEALTH CHECK                      ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

try {
    $healthCheck = new HealthCheckService();
    
    echo "Exécution des checks...\n\n";
    
    // Exécuter tous les checks
    $healthCheck->runAll();
    
    // Afficher les résultats
    $checks = $healthCheck->getChecks();
    $status = $healthCheck->getStatus();
    
    foreach ($checks as $component => $info) {
        $icon = match($info['status']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'degraded' => '⚠️',
            'critical' => '❌',
            'unhealthy' => '❌',
            'skipped' => '⏭️',
            default => '❓'
        };
        
        echo sprintf("%s %-20s [%s] %s\n", 
            $icon,
            strtoupper($component),
            strtoupper($info['status']),
            $info['message']
        );
        
        if (isset($info['response_time_ms']) && $info['response_time_ms'] !== null) {
            echo sprintf("   └─ Response time: %.2f ms\n", $info['response_time_ms']);
        }
        
        if (isset($info['percent_free'])) {
            echo sprintf("   └─ Free space: %.2f%%\n", $info['percent_free']);
        }
        
        if (isset($info['details']) && is_array($info['details'])) {
            foreach ($info['details'] as $detail) {
                $runningIcon = $detail['running'] ? '🟢' : '🔴';
                echo sprintf("   └─ %s %s (PID: %d)\n", 
                    $runningIcon,
                    $detail['name'],
                    $detail['pid']
                );
            }
        }
    }
    
    echo "\n";
    echo "╔════════════════════════════════════════════════════════╗\n";
    $overallIcon = $status === 'healthy' ? '✅' : '❌';
    echo sprintf("║  STATUT GLOBAL: %s %-38s ║\n", $overallIcon, strtoupper($status));
    echo "╚════════════════════════════════════════════════════════╝\n";
    
    // Code de retour
    exit($status === 'healthy' ? 0 : 1);
    
} catch (\Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(2);
}
