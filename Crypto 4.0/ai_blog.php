<?php
/**
 * ai_blog.php
 * - Génère un article de blog expliquant les performances du portefeuille
 *   et les ajustements des prompts / seuils d'achat/vente.
 * - Appelé manuellement depuis un bouton dans l'interface ou via AJAX quotidien.
 */

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
require_once ROOT_DIR . '/lib/MistralClient.php';

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $cash = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetchColumn();
    $holdings = $pdo->query("SELECT COUNT(*) FROM holdings")->fetchColumn();
    $totalPortfolio = $cash;
    $sumHold = $pdo->query("SELECT SUM(h.amount * c.current_price) FROM holdings h JOIN coins c ON h.coin_id = c.id")->fetchColumn();
    if ($sumHold) $totalPortfolio += $sumHold;
    $perf = round(($totalPortfolio - 1000000) / 1000000 * 100, 2);
    
    $thresholds = $pdo->query("SELECT param, value FROM rl_thresholds")->fetchAll(PDO::FETCH_KEY_PAIR);
    $buyScore = $thresholds['buy_score'] ?? 65;
    $sellScore = $thresholds['sell_score'] ?? 35;
    
    $prompt = "Rédige un article de blog pour investisseurs crypto. Sujet : 'Performances du portefeuille NEO DASH et évolution des stratégies IA'. 
Portefeuille actuel : $totalPortfolio € (performance $perf% depuis l'origine). 
Seuils d'achat/vente actuels : achat si score >= $buyScore, vente si score <= $sellScore. 
Explique comment l'auto‑apprentissage par renforcement a ajusté ces seuils, et donne des conseils pédagogiques. 
Style engageant, 300-400 mots. Titre accrocheur.";
    
    $messages = [
        ['role' => 'system', 'content' => 'Tu es un blogueur financier spécialisé IA et crypto. Écris en français.'],
        ['role' => 'user', 'content' => $prompt]
    ];
    
    $article = callMistral($messages, 'labs-mistral-small-creative', 700);
    if ($article) {
        $lines = explode("\n", $article);
        $title = trim($lines[0]);
        if (strlen($title) > 100) $title = substr($title, 0, 100);
        $stmt = $pdo->prepare("INSERT INTO ai_blog_posts (title, content, created_at, tags) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $article, time(), 'auto, rl, portfolio']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'title' => $title]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Erreur génération blog']);
    }
} catch (Exception $e) {
    appLog("Erreur ai_blog.php: " . $e->getMessage(), 'ERROR');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'ERREUR: ' . $e->getMessage()]);
}
?>
