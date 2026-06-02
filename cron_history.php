<?php
/**
 * cron_history.php
 * Script d'automatisation pour l'archivage et l'audit des analyses.
 * À exécuter via Cron toutes les 6 heures.
 * 
 * Commande crontab recommandée :
 * 0 */6 * * * php /workspace/cron_history.php >> /workspace/logs/history_cron.log 2>&1
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
    
    // Récupérer toutes les cryptos suivies dans individual_analysis
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $cryptos = $pdo->query("SELECT crypto_id, symbol FROM individual_analysis")->fetchAll(PDO::FETCH_ASSOC);

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
