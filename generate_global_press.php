<?php
/**
 * generate_global_press.php
 * Génère une longue revue de presse IA (Mistral Large) à partir
 * des 10 meilleurs scores du moment, et la stocke dans global_analysis.
 * Appelé automatiquement par AJAX depuis index.php.
 */

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
require_once ROOT_DIR . '/lib/MistralClient.php';

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les 10 cryptos avec les meilleurs scores récents
    $top = $pdo->query("SELECT c.name, c.symbol, a.score, a.trend_pct, a.volatility, a.rsi, a.advice
                        FROM coin_analysis_history a
                        JOIN coins c ON a.coin_id = c.id
                        WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = c.id)
                        ORDER BY a.score DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = "Analyse fraîche des 100 premières cryptos. Top 10 scores :\n";
    foreach ($top as $t) {
        $summary .= "- {$t['name']} ({$t['symbol']}) : score {$t['score']}, tendance {$t['trend_pct']}%, volatilité {$t['volatility']}%, RSI {$t['rsi']}\n";
    }
    $summary .= "Génère un long article de presse financière (400-500 mots) sur l'état du marché crypto actuel, les meilleures opportunités d'achat selon ces données, les risques (volatilité, RSI), et conclus par un conseil d'investissement global précis en une phrase.";
    
    $messages = [
        ['role' => 'system', 'content' => 'Tu es un analyste financier reconnu. Réponds en français, style professionnel et factuel. Termine par un conseil clair.'],
        ['role' => 'user', 'content' => $summary]
    ];
    
    $globalAnalysis = callMistral($messages, 'mistral-large-2512', 800);
    if (!$globalAnalysis) $globalAnalysis = "Analyse globale indisponible momentanément. Veuillez réessayer plus tard.";
    
    // Extraire la dernière phrase comme conseil global
    $sentences = preg_split('/(?<=[.!?])\s+/', $globalAnalysis);
    $globalAdvice = end($sentences);
    if (strlen($globalAdvice) < 10) $globalAdvice = $globalAnalysis;
    
    $now = time();
    $stmt = $pdo->prepare("INSERT INTO global_analysis (analysis_text, global_advice, market_summary, generated_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$globalAnalysis, $globalAdvice, $summary, $now]);
    
    // Nettoyer les anciennes (garder 20 dernières)
    $pdo->exec("DELETE FROM global_analysis WHERE id NOT IN (SELECT id FROM global_analysis ORDER BY generated_at DESC LIMIT 20)");
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'OK']);
} catch (Exception $e) {
    appLog("Erreur generate_global_press: " . $e->getMessage(), 'ERROR');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ERREUR: ' . $e->getMessage()]);
}
?>
