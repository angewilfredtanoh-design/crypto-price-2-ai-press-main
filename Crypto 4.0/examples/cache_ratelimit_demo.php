<?php
/**
 * Exemple d'utilisation du Rate Limiting et du Cache Intelligent
 * 
 * Ce fichier démontre comment utiliser les nouvelles fonctionnalités :
 * - CacheManager (Redis ou Fichier)
 * - RateLimiter (Token Bucket, Sliding Window, Fixed Window)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Crypto\Core\CacheManager;
use Crypto\Core\RateLimiter;
use Crypto\Core\RateLimitExceededException;
use Crypto\Core\Logger;

// Configuration
$config = [
    'cache' => [
        'redis_enabled' => false, // Mettre à true si Redis est disponible
        'redis_host' => '127.0.0.1',
        'redis_port' => 6379,
        'file_cache_dir' => __DIR__ . '/cache/',
        'prefix' => 'crypto4_'
    ],
    'rate_limiter' => [
        'default_strategy' => RateLimiter::STRATEGY_TOKEN_BUCKET,
        'default_limit' => 10,      // 10 requêtes
        'default_window' => 60      // par 60 secondes
    ]
];

echo "=== Démonstration Cache & Rate Limiting ===\n\n";

// 1. Initialisation du Cache Manager
echo "1. Initialisation du Cache Manager...\n";
$cache = new CacheManager($config['cache']);
echo "   Type de cache: " . ($cache->isUsingRedis() ? 'Redis' : 'Fichier') . "\n\n";

// 2. Initialisation du Rate Limiter
echo "2. Initialisation du Rate Limiter...\n";
$rateLimiter = new RateLimiter($cache, $config['rate_limiter']);
echo "   Stratégie: {$config['rate_limiter']['default_strategy']}\n";
echo "   Limite: {$config['rate_limiter']['default_limit']} requêtes / {$config['rate_limiter']['default_window']}s\n\n";

// 3. Démonstration du Cache
echo "3. Test du Cache...\n";
$cacheKey = 'test_data';
$cacheValue = ['message' => 'Hello World', 'timestamp' => time()];

// Setter une valeur
$cache->set($cacheKey, $cacheValue, 300); // TTL de 5 minutes
echo "   ✓ Valeur mise en cache: " . json_encode($cacheValue) . "\n";

// Getter la valeur
$cachedValue = $cache->get($cacheKey);
echo "   ✓ Valeur récupérée: " . json_encode($cachedValue) . "\n";

// Stats du cache
$stats = $cache->getStats();
echo "   Stats: {$stats['keys_count']} clés, {$stats['memory_usage']} utilisés\n\n";

// 4. Démonstration du Rate Limiting
echo "4. Test du Rate Limiting (15 tentatives)...\n";
$userId = 'user_123';
$resource = 'api_trading';

for ($i = 1; $i <= 15; $i++) {
    try {
        $result = $rateLimiter->isAllowed($userId, $resource);
        
        if ($result['allowed']) {
            echo "   ✓ Requête #{$i}: AUTORISÉE (restant: {$result['remaining']})\n";
        } else {
            echo "   ✗ Requête #{$i}: REFUSÉE (retry after: {$result['retry_after']}s)\n";
        }
    } catch (RateLimitExceededException $e) {
        echo "   ✗ Requête #{$i}: EXCEPTION ({$e->getMessage()})\n";
        echo "      Headers: " . json_encode($e->getHeaders()) . "\n";
    }
}

echo "\n5. Statistiques finales du Rate Limiter...\n";
$status = $rateLimiter->getStatus($userId, $resource);
echo "   Remaining: {$status['remaining']}\n";
echo "   Reset: " . date('Y-m-d H:i:s', $status['reset']) . "\n";
echo "   Strategy: {$status['strategy']}\n\n";

// 6. Test avec différentes stratégies
echo "6. Test des différentes stratégies...\n";

$strategies = [
    RateLimiter::STRATEGY_TOKEN_BUCKET => 'Token Bucket',
    RateLimiter::STRATEGY_SLIDING_WINDOW => 'Sliding Window',
    RateLimiter::STRATEGY_FIXED_WINDOW => 'Fixed Window'
];

foreach ($strategies as $strategy => $name) {
    $testId = "test_" . uniqid();
    $result = $rateLimiter->isAllowed($testId, 'test', 5, 60, $strategy);
    echo "   {$name}: " . ($result['allowed'] ? '✓ OK' : '✗ FAIL') . "\n";
}

echo "\n=== Démonstration terminée ===\n";

// 7. Nettoyage
echo "\n7. Nettoyage du cache...\n";
$cleaned = $cache->cleanup();
echo "   {$cleaned} entrées expirées supprimées\n";

echo "\nPour activer Redis:\n";
echo "  1. Installez Redis: sudo apt-get install redis-server\n";
echo "  2. Installez l'extension PHP: pecl install redis\n";
echo "  3. Modifiez la config: 'redis_enabled' => true\n";
