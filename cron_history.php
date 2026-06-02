<?php
/**
 * cron_history.php
 * Script d'automatisation pour l'archivage et l'audit des analyses.
 * Executez ce script toutes les 6 heures via Cron.
 * 
 * Exemple de commande crontab :
 * Toutes les 6 heures: php __DIR__/cron_history.php >> logs/history_cron.log 2>&1
 */

// Configuration du temps d'exécution
set_time_limit(300); // 5 minutes max
error_reporting(E_ALL);
ini_set('display_errors', 0); // Pas d'affichage direct, tout dans les logs

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/HistoryEngine.php';

// Fonction de logging
function logMessage($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

logMessage("=== Démarrage tâche historique ===");

try {
    $engine = new HistoryEngine();

    // 1. Archiver les analyses actuelles
    logMessage("Archivage des analyses en cours...");
    
    // Récupérer toutes les cryptos suivies dans individual_analysis (colonne coin_id)
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cryptos = $pdo->query("SELECT DISTINCT coin_id as crypto_id, 
        CASE 
            WHEN coin_id = 'bitcoin' THEN 'BTC'
            WHEN coin_id = 'ethereum' THEN 'ETH'
            WHEN coin_id = 'binancecoin' THEN 'BNB'
            WHEN coin_id = 'ripple' THEN 'XRP'
            WHEN coin_id = 'cardano' THEN 'ADA'
            WHEN coin_id = 'solana' THEN 'SOL'
            WHEN coin_id = 'dogecoin' THEN 'DOGE'
            WHEN coin_id = 'polkadot' THEN 'DOT'
            ELSE UPPER(coin_id)
        END as symbol 
        FROM individual_analysis")->fetchAll(PDO::FETCH_ASSOC);

    $countArchive = 0;
    foreach ($cryptos as $c) {
        if ($engine->archiveAnalysis($c['crypto_id'], $c['symbol'])) {
            $countArchive++;
            logMessage("  -> Archivé: {$c['symbol']} ({$c['crypto_id']})");
        }
    }
    logMessage("$countArchive nouvelles analyses archivées.");

    // 2. Lancer l'audit rétrospectif
    logMessage("Lancement de l'audit de performance...");
    $auditCount = $engine->runAudit();
    logMessage("Audit terminé: $auditCount analyses évaluées.");

    // 3. Générer une revue de marché hebdomadaire (si on est lundi entre 0h et 1h)
    $dayOfWeek = (int)date('N'); // 1 (lundi) à 7 (dimanche)
    $hour = (int)date('H');
    
    if ($dayOfWeek == 1 && $hour >= 0 && $hour < 2) {
        logMessage("Génération de la revue de marché hebdomadaire...");
        $engine->generateMarketReview();
        logMessage("Revue hebdomadaire générée avec succès.");
    } else {
        logMessage("Pas de revue hebdomadaire aujourd'hui (jour: $dayOfWeek, heure: $hour).");
    }

    logMessage("=== Tâche historique terminée avec succès ===");

} catch (Exception $e) {
    logMessage("ERREUR CRITIQUE: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
?>
