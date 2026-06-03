<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use App\Core\Database;
use App\Core\CacheManager;

header('Content-Type: application/json');

$cache = new CacheManager();
$db = Database::getInstance();

// Récupération des métriques
$metrics = [
    'timestamp' => time(),
    'system' => [
        'uptime' => 'N/A',
        'memory_usage' => memory_get_usage(true),
        'cache_hits' => $cache->getStats()['hits'] ?? 0,
        'cache_misses' => $cache->getStats()['misses'] ?? 0,
        'cache_hit_rate' => $cache->getHitRate() ?? 0
    ],
    'trading' => [],
    'api' => [
        'mistral_calls' => 0,
        'coingecko_calls' => 0
    ],
    'performance' => []
];

// Stats de trading depuis la BDD
try {
    $stmt = $db->query("SELECT 
        COUNT(*) as total_trades,
        SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END) as losing_trades,
        AVG(pnl) as avg_pnl,
        MAX(pnl) as max_profit,
        MIN(pnl) as max_loss,
        SUM(pnl) as total_pnl
        FROM trades WHERE status = 'closed'");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $winRate = $stats['total_trades'] > 0 
            ? ($stats['winning_trades'] / $stats['total_trades']) * 100 
            : 0;
        
        $metrics['trading'] = [
            'total_trades' => (int)$stats['total_trades'],
            'winning_trades' => (int)$stats['winning_trades'],
            'losing_trades' => (int)$stats['losing_trades'],
            'win_rate' => round($winRate, 2),
            'avg_pnl' => round((float)$stats['avg_pnl'], 2),
            'max_profit' => round((float)$stats['max_profit'], 2),
            'max_loss' => round((float)$stats['max_loss'], 2),
            'total_pnl' => round((float)$stats['total_pnl'], 2),
            'profit_factor' => $stats['losing_trades'] > 0 
                ? round(abs($stats['winning_trades'] / $stats['losing_trades']), 2) 
                : null
        ];
    }
} catch (Exception $e) {
    $metrics['trading']['error'] = $e->getMessage();
}

// Stats API (depuis logs ou cache)
$logFile = ROOT_DIR . '/logs/api_usage.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $apiCalls = ['mistral' => 0, 'coingecko' => 0];
    
    foreach ($lines as $line) {
        if (strpos($line, 'Mistral') !== false) $apiCalls['mistral']++;
        if (strpos($line, 'CoinGecko') !== false) $apiCalls['coingecko']++;
    }
    
    $metrics['api'] = [
        'mistral_calls' => $apiCalls['mistral'],
        'coingecko_calls' => $apiCalls['coingecko'],
        'total_calls' => array_sum($apiCalls)
    ];
}

// Performance globale
if (!empty($metrics['trading']['total_pnl'])) {
    $initialCapital = 10000;
    $roi = (($metrics['trading']['total_pnl'] + $initialCapital) / $initialCapital - 1) * 100;
    $metrics['performance'] = [
        'roi' => round($roi, 2),
        'sharpe_ratio' => null,
        'max_drawdown' => null,
        'trading_days' => 0
    ];
}

echo json_encode($metrics, JSON_PRETTY_PRINT);
