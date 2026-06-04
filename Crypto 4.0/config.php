<?php
/**
 * config.php
 * Configuration centralisée pour NEO CRYPTO DASH v4.0
 * Branche: dossier-crypto-4.0-setup
 * 
 * SÉCURITÉ :
 * - Plus de clé API en dur dans le code
 * - Chargement via variables d'environnement (.env ou panel Hostinger)
 * - .gitignore protège les secrets
 * 
 * Fonctionnalités conservées :
 * - Rotation & retry API Mistral
 * - Mapping tâches → modèles
 * - Config trading / technique / cache / UI
 * - Helpers globaux (appLog, formatLargeNumber, etc.)
 */

// Empêcher l'exécution directe
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

// ============================================================================
// CHARGEMENT SÉCURISÉ DES SECRETS (.env + getenv)
// ============================================================================

function loadEnvFile(string $path): void {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Ne pas écraser si déjà défini dans l'environnement
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }
}

// Charger .env s'il existe (dev local)
loadEnvFile(ROOT_DIR . '/.env');

// ============================================================================
// CONFIGURATION GÉNÉRALE
// ============================================================================

define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('LOG_FILE', ROOT_DIR . '/logs/app.log');
define('ERROR_LOG', ROOT_DIR . '/logs/error.log');
define('API_LOG', ROOT_DIR . '/logs/api_usage.log');
define('CACHE_DIR', ROOT_DIR . '/cache');
define('DATA_DIR', ROOT_DIR . '/data');
define('EXPORTS_DIR', ROOT_DIR . '/exports');

// Créer les dossiers nécessaires (sécurisé Hostinger 0755)
$dirsToCreate = [CACHE_DIR, DATA_DIR, EXPORTS_DIR, ROOT_DIR . '/logs'];
foreach ($dirsToCreate as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// CLÉ API MISTRAL - SÉCURISÉE (jamais en dur)
// ============================================================================

define('MISTRAL_API_KEY', getenv('MISTRAL_API_KEY') ?: '');
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

if (empty(MISTRAL_API_KEY)) {
    // Log seulement si on est en mode web (pas CLI pendant les tests)
    if (php_sapi_name() !== 'cli') {
        error_log('ATTENTION: MISTRAL_API_KEY non définie. Crée un fichier .env ou définis la variable d\'environnement.');
    }
}

// ============================================================================
// MODÈLES MISTRAL RÉELS (mis à jour 2026)
// Utilise uniquement des model_id valides sur https://api.mistral.ai
// ============================================================================

define('MISTRAL_MODELS', [
    'mistral-large-latest' => [
        'name' => 'Mistral Large Latest',
        'max_tokens' => 128000,
        'temperature_default' => 0.3,
        'category' => 'flagship'
    ],
    'mistral-small-latest' => [
        'name' => 'Mistral Small Latest',
        'max_tokens' => 32000,
        'temperature_default' => 0.3,
        'category' => 'fast'
    ],
    'codestral-latest' => [
        'name' => 'Codestral Latest',
        'max_tokens' => 32000,
        'temperature_default' => 0.2,
        'category' => 'code'
    ],
    'ministral-8b-latest' => [
        'name' => 'Ministral 8B',
        'max_tokens' => 32000,
        'temperature_default' => 0.3,
        'category' => 'edge'
    ],
    'pixtral-large-latest' => [
        'name' => 'Pixtral Large (Vision)',
        'max_tokens' => 128000,
        'temperature_default' => 0.3,
        'category' => 'vision'
    ]
]);

// ============================================================================
// MAPPING DES TÂCHES → MODÈLES (mis à jour avec modèles réels)
// ============================================================================

define('TASK_MODEL_MAPPING', [
    'global_analysis'        => 'mistral-large-latest',
    'individual_analysis'    => 'mistral-small-latest',
    'deep_analysis'          => 'mistral-large-latest',
    'blog_post'              => 'mistral-small-latest',
    'newsletter'             => 'mistral-small-latest',
    'social_media'           => 'mistral-small-latest',
    'sentiment_analysis'     => 'mistral-small-latest',
    'risk_analysis'          => 'mistral-large-latest',
    'correlation_analysis'   => 'mistral-small-latest',
    'whale_detection'        => 'mistral-small-latest',
    'arbitrage_opportunity'  => 'mistral-small-latest',
    'macro_insights'         => 'mistral-large-latest',
    'prediction_engine'      => 'mistral-large-latest',
    'news_summary'           => 'mistral-small-latest',
    'defi_analysis'          => 'mistral-small-latest',
    'nft_analysis'           => 'mistral-small-latest',
    'tax_report'             => 'mistral-small-latest',
    'performance_review'     => 'mistral-small-latest',
    'technical_indicators'   => 'mistral-small-latest',
    'trade_signal'           => 'mistral-small-latest',
    'portfolio_optimization' => 'mistral-large-latest',
    'rebalancing_advice'     => 'mistral-small-latest',
    'code_generation'        => 'codestral-latest',
    'code_review'            => 'codestral-latest',
    'data_extraction'        => 'mistral-small-latest',
    'api_integration'        => 'codestral-latest',
    'price_alert'            => 'mistral-small-latest',
    'market_alert'           => 'mistral-small-latest',
    'news_alert'             => 'mistral-small-latest'
]);

// ============================================================================
// PARAMÈTRES DE ROTATION & RESILIENCE API
// ============================================================================

define('API_ROTATION_CONFIG', [
    'max_retries'               => 3,
    'retry_delay_ms'            => 1000,
    'blacklist_duration_seconds'=> 300,
    'rate_limit_per_minute'     => 60,
    'timeout_seconds'           => 30,
    'user_agent'                => 'NEOCryptoDash/4.0 (Hostinger; Production)',
    'fallback_model'            => 'mistral-small-latest'
]);

// ============================================================================
// PARAMÈTRES DE TRADING VIRTUEL (1M€ de départ)
// ============================================================================

define('TRADING_CONFIG', [
    'initial_capital'      => 1000000,
    'investment_per_trade' => 5000,
    'max_position_size'    => 50000,
    'max_positions'        => 20,
    'stop_loss_percent'    => 15,
    'take_profit_percent'  => 25,
    'rebalance_threshold'  => 10,
    'default_buy_score'    => 65,
    'default_sell_score'   => 35,
    'trailing_stop_enabled'=> true,
    'dca_enabled'          => true,
    'dca_levels'           => 3
]);

// ============================================================================
// PARAMÈTRES D'ANALYSE TECHNIQUE
// ============================================================================

define('TECHNICAL_CONFIG', [
    'rsi_period'            => 14,
    'macd_fast'             => 12,
    'macd_slow'             => 26,
    'macd_signal'           => 9,
    'ema_short'             => 12,
    'ema_long'              => 26,
    'bollinger_period'      => 20,
    'bollinger_std'         => 2,
    'volatility_lookback'   => 7,
    'trend_lookback'        => 7,
    'score_trend_weight'    => 0.35,
    'score_volatility_weight'=> 0.20,
    'score_rsi_weight'      => 0.25,
    'score_volume_weight'   => 0.20
]);

// ============================================================================
// PARAMÈTRES DE CACHE ET PERFORMANCE
// ============================================================================

define('CACHE_CONFIG', [
    'coin_data_ttl'             => 600,
    'analysis_ttl'              => 3600,
    'global_analysis_ttl'       => 7200,
    'portfolio_update_interval' => 3600,
    'max_historical_days'       => 90,
    'sparkline_points'          => 168,
    'batch_size'                => 25
]);

// ============================================================================
// CONFIGURATION DE L'INTERFACE UTILISATEUR
// ============================================================================

define('UI_CONFIG', [
    'app_name'           => 'NEO CRYPTO DASH',
    'app_version'        => '4.0.0',
    'app_tagline'        => 'IA Mistral · Analyses RL · Portefeuille virtuel 1M€',
    'theme_primary'      => '#3b82f6',
    'theme_success'      => '#10b981',
    'theme_danger'       => '#ef4444',
    'theme_warning'      => '#f59e0b',
    'theme_dark'         => '#111827',
    'items_per_page'     => 25,
    'enable_animations'  => true,
    'enable_notifications'=> true
]);

// ============================================================================
// FONCTIONS UTILITAIRES GLOBALES (conservées et améliorées)
// ============================================================================

/**
 * Logger centralisé compatible Hostinger
 */
function appLog(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message" . PHP_EOL;

    $target = LOG_FILE;
    if ($level === 'ERROR' || $level === 'CRITICAL') {
        $target = ERROR_LOG;
    } elseif ($level === 'API') {
        $target = API_LOG;
    }

    @file_put_contents($target, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Gestion d'erreur centralisée
 */
function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
    appLog("PHP Error [$errno]: $errstr in $errfile on line $errline", 'ERROR');
    return false;
}
set_error_handler('handleError');

/**
 * Nettoyer les anciennes entrées de cache
 */
function cleanupCache(): void {
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $cutoff = time() - (CACHE_CONFIG['max_historical_days'] * 86400);
        $pdo->exec("DELETE FROM historical_snapshots WHERE snapshot_time < $cutoff");
        $pdo->exec("DELETE FROM global_analysis WHERE generated_at < $cutoff");
        $pdo->exec("DELETE FROM coin_analysis_history WHERE timestamp < $cutoff");
        $pdo->exec("DELETE FROM api_usage_logs WHERE timestamp < $cutoff");

        appLog("Cache cleanup completed - deleted entries older than " . CACHE_CONFIG['max_historical_days'] . " days");
    } catch (Exception $e) {
        appLog("Cache cleanup failed: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Vérifier et initialiser la base de données
 */
function ensureDatabaseInitialized(): void {
    if (!file_exists(DB_FILE)) {
        require_once ROOT_DIR . '/init_db.php';
        if (function_exists('initializeDatabase')) {
            initializeDatabase();
        }
    }
}

/**
 * Formater un nombre avec séparateurs français
 */
function formatNumber($number, int $decimals = 2): string {
    return number_format((float)$number, $decimals, ',', ' ');
}

/**
 * Formater une grande valeur marché (K, M, Md, B, T) - version améliorée
 */
function formatLargeNumber($number): string {
    $number = (float)$number;
    if ($number >= 1e12) return round($number / 1e12, 2) . ' T€';
    if ($number >= 1e9)  return round($number / 1e9, 2)  . ' Md€';
    if ($number >= 1e6)  return round($number / 1e6, 2)  . ' M€';
    if ($number >= 1e3)  return round($number / 1e3, 2)  . ' K€';
    return round($number, 2) . '€';
}

/**
 * Obtenir le modèle optimal pour une tâche donnée
 */
function getModelForTask(string $taskName): string {
    return TASK_MODEL_MAPPING[$taskName] ?? API_ROTATION_CONFIG['fallback_model'];
}

/**
 * Calculer un score de confiance basé sur l'historique (placeholder améliorable)
 */
function calculateConfidenceScore(string $coinId, PDO $pdo): int {
    try {
        $stmt = $pdo->prepare("SELECT AVG(accuracy_score) as avg_accuracy, COUNT(*) as count 
                               FROM coin_analysis_history 
                               WHERE coin_id = ? AND accuracy_score IS NOT NULL 
                               LIMIT 20");
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['count'] < 3) {
            return 50; // valeur par défaut raisonnable
        }

        $weight = min(1.0, $result['count'] / 20.0);
        return (int) round(($result['avg_accuracy'] * $weight) + (50 * (1 - $weight)));
    } catch (Exception $e) {
        return 50;
    }
}

// ============================================================================
// CHARGEMENT AUTOMATIQUE AU DÉMARRAGE
// ============================================================================

date_default_timezone_set('Europe/Paris');

appLog('═══════════════════════════════════════════════════════');
appLog('NEO CRYPTO DASH v' . UI_CONFIG['app_version'] . ' configuration loaded (SECURE MODE)');
appLog('Timezone: Europe/Paris | PHP: ' . phpversion());
appLog('Database: ' . DB_FILE);
if (empty(MISTRAL_API_KEY)) {
    appLog('ATTENTION: Aucune clé Mistral définie !', 'WARNING');
} else {
    appLog('Mistral API Key: configurée (longueur ' . strlen(MISTRAL_API_KEY) . ')');
}
appLog('═══════════════════════════════════════════════════════');

?>
