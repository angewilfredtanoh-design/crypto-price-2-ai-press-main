<?php
/**
 * config.local.php - Configuration Locale (NON VERSIONNÉE)
 * 
 * Ce fichier permet de surcharger les paramètres de config.php
 * sans modifier la configuration principale.
 * 
 * IMPORTANT: Ce fichier doit être ajouté à .gitignore
 * pour ne jamais commiter vos clés API ou paramètres sensibles.
 * 
 * Exemples d'utilisation:
 * 
 * // Surcharger les clés API Mistral
 * define('DEFAULT_MISTRAL_API_KEYS', [
 *     'votre_cle_api_principale',
 *     'votre_cle_api_secondaire'
 * ]);
 * 
 * // Modifier la configuration de trading
 * define('TRADING_CONFIG', array_merge(TRADING_CONFIG, [
 *     'initial_capital' => 500000,
 *     'max_positions' => 10,
 *     'stop_loss_percent' => 10
 * ]));
 * 
 * // Activer/désactiver des fonctionnalités
 * define('DEBUG_MODE', true);
 * define('MAINTENANCE_MODE', false);
 * 
 * // Personnaliser l'interface
 * define('UI_CONFIG', array_merge(UI_CONFIG, [
 *     'items_per_page' => 50,
 *     'enable_notifications' => false
 * ]));
 */

// ============================================================================
// EXEMPLE DE CONFIGURATION LOCALE (À PERSONNALISER)
// ============================================================================

// Mode débogage (désactivé par défaut)
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

// Mode maintenance (désactivé par défaut)
if (!defined('MAINTENANCE_MODE')) {
    define('MAINTENANCE_MODE', false);
}

// ============================================================================
// SURCHARGE DES CLÉS API MISTRAL
// Décommentez et remplacez par vos vraies clés
// ============================================================================

/*
define('DEFAULT_MISTRAL_API_KEYS', [
    'votre_premiere_cle_api_mistral',
    'votre_deuxieme_cle_api_mistral',
    'votre_troisieme_cle_api_mistral'
]);
*/

// ============================================================================
// SURCHARGE DE LA CONFIGURATION DE TRADING
// Décommentez pour personnaliser
// ============================================================================

/*
define('TRADING_CONFIG', array_merge(TRADING_CONFIG, [
    'initial_capital' => 500000,        // Capital initial personnalisé
    'investment_per_trade' => 2500,     // Montant par trade
    'max_position_size' => 25000,       // Taille max d'une position
    'max_positions' => 10,              // Nombre max de positions (10-15 recommandé)
    'stop_loss_percent' => 10,          // Stop-loss à -10%
    'take_profit_percent' => 20,        // Take-profit à +20%
    'rebalance_threshold' => 15,        // Seuil de rééquilibrage
    'default_buy_score' => 70,          // Score minimum pour acheter
    'default_sell_score' => 30,         // Score maximum pour vendre
    'trailing_stop_enabled' => false,   // Désactiver le trailing stop
    'dca_enabled' => false              // Désactiver le DCA
]));
*/

// ============================================================================
// SURCHARGE DES PARAMÈTRES DE CACHE
// Décommentez pour personnaliser
// ============================================================================

/*
define('CACHE_CONFIG', array_merge(CACHE_CONFIG, [
    'coin_data_ttl' => 300,             // Cache données coins: 5 min
    'analysis_ttl' => 1800,             // Cache analyses: 30 min
    'global_analysis_ttl' => 3600,      // Cache analyse globale: 1h
    'portfolio_update_interval' => 1800 // Mise à jour portfolio: 30 min
]));
*/

// ============================================================================
// SURCHARGE DE L'INTERFACE UTILISATEUR
// Décommentez pour personnaliser
// ============================================================================

/*
define('UI_CONFIG', array_merge(UI_CONFIG, [
    'items_per_page' => 50,             // Éléments par page
    'enable_animations' => false,       // Désactiver animations
    'enable_notifications' => false     // Désactiver notifications
]));
*/

// ============================================================================
// CONFIGURATION SPÉCIFIQUE HOSTINGER / PRODUCTION
// ============================================================================

// Limites de taux API personnalisées
/*
define('API_ROTATION_CONFIG', array_merge(API_ROTATION_CONFIG, [
    'max_retries' => 5,                 // Plus de retries en production
    'retry_delay_ms' => 2000,           // Délai plus long entre retries
    'rate_limit_per_minute' => 30,      // Limite conservative
    'timeout_seconds' => 45             // Timeout plus long
]));
*/

// ============================================================================
// LOGGER PERSONNALISÉ POUR ENVIRONNEMENT LOCAL
// ============================================================================

// Niveau de log minimum (INFO, WARNING, ERROR, DEBUG)
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', DEBUG_MODE ? 'DEBUG' : 'INFO');
}

// Fichier de log supplémentaire pour le débogage
if (DEBUG_MODE && !defined('DEBUG_LOG_FILE')) {
    define('DEBUG_LOG_FILE', ROOT_DIR . '/logs/debug.log');
}

// ============================================================================
// VALIDATION DE LA CONFIGURATION
// ============================================================================

if (defined('DEFAULT_MISTRAL_API_KEYS') && is_array(DEFAULT_MISTRAL_API_KEYS)) {
    $validKeys = array_filter(DEFAULT_MISTRAL_API_KEYS, function($key) {
        return !empty(trim($key)) && strlen(trim($key)) >= 32;
    });
    
    if (empty($validKeys)) {
        appLog("ATTENTION: Aucune clé API Mistral valide dans config.local.php", 'WARNING');
    } else {
        appLog("Configuration locale chargée avec " . count($validKeys) . " clé(s) API valide(s)", 'INFO');
    }
}

appLog("config.local.php chargé avec succès", DEBUG_MODE ? 'DEBUG' : 'INFO');
