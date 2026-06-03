<?php
/**
 * trading_engine.php
 * Moteur de trading automatique virtuel
 * Exécute les achats/ventes basés sur les analyses IA
 * Doit être exécuté régulièrement via cron (ex: toutes les heures)
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

require_once ROOT_DIR . '/config.php';
require_once ROOT_DIR . '/lib/TradingLogic.php';
require_once ROOT_DIR . '/lib/MistralClient.php';

// Empêcher l'exécution simultanée multiple
$lockFile = ROOT_DIR . '/cache/trading_engine.lock';
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 3600) {
    die("Trading engine already running or locked\n");
}
file_put_contents($lockFile, time());

try {
    $tradingLogic = new TradingLogic();
    $mistralClient = new MistralClient();
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    appLog('=== TRADING ENGINE STARTED ===');
    
    // ============================================================================
    // ÉTAPE 1 : Mettre à jour les prix des positions
    // ============================================================================
    appLog('Updating position prices...');
    $tradingLogic->updatePositionPrices();
    $tradingLogic->updatePortfolioValue();
    
    // ============================================================================
    // ÉTAPE 2 : Récupérer les analyses avec score >= 65 (ACHAT) ou <= 35 (VENTE)
    // ============================================================================
    appLog('Scanning for trading opportunities...');
    
    // Seuils dynamiques depuis la BDD ou défaut
    $buyThreshold = 65;
    $sellThreshold = 35;
    
    $stmt = $pdo->query("SELECT * FROM individual_analysis WHERE score IS NOT NULL AND generated_at > " . (time() - 7200));
    $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $portfolio = $tradingLogic->getPortfolio();
    $activePositions = $tradingLogic->getActivePositions();
    $criteriaWeights = $tradingLogic->getCurrentCriteriaWeights();
    
    $cash = $portfolio['current_cash'];
    $investmentPerTrade = TRADING_CONFIG['investment_per_trade'] ?? 5000;
    // Limite stricte de positions actives (max 15 au lieu de 20 pour réduire le risque)
    $maxPositions = min(15, TRADING_CONFIG['max_positions'] ?? 15);
    
    // Stop-loss virtuel : -15% sur une position
    $stopLossPercent = -15;
    
    $tradesExecuted = 0;
    
    foreach ($analyses as $analysis) {
        $coinId = $analysis['coin_id'];
        $score = (int)$analysis['score'];
        $advice = $analysis['advice'] ?? '';
        $analysisText = $analysis['analysis_text'] ?? '';
        
        // Récupérer les infos de la crypto
        $coinStmt = $pdo->prepare("SELECT id, symbol, name, current_price FROM coins WHERE id = ?");
        $coinStmt->execute([$coinId]);
        $coin = $coinStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coin || $coin['current_price'] <= 0) continue;
        
        $symbol = strtoupper($coin['symbol']);
        $currentPrice = $coin['current_price'];
        
        // ========================================================================
        // LOGIQUE D'ACHAT (score >= 65)
        // ========================================================================
        if ($score >= $buyThreshold) {
            // Vérifier si on a déjà une position
            $existingPosition = $tradingLogic->getPosition($coinId);
            
            if ($existingPosition) {
                // Position existante : on pourrait moyenner (optionnel)
                appLog("SKIP BUY $symbol: Position already exists");
                continue;
            }
            
            // Vérifier limites
            $positionCount = count($activePositions);
            if ($positionCount >= $maxPositions) {
                appLog("SKIP BUY $symbol: Max positions reached ($positionCount/$maxPositions)");
                continue;
            }
            
            if ($cash < $investmentPerTrade) {
                appLog("SKIP BUY $symbol: Insufficient cash ($cash < $investmentPerTrade)");
                continue;
            }
            
            // Calculer la quantité
            $quantity = $investmentPerTrade / $currentPrice;
            
            // Demander justification à Mistral
            $prompt = "Tu es un trader IA expert. Tu vas acheter $symbol (ID: $coinId) à $currentPrice €.
Score IA: $score/100
Analyse technique: $analysisText
Conseil IA: $advice

Poids des critères actuels:
- RSI: {$criteriaWeights['rsi_weight']}%
- Sparkline/Trend: {$criteriaWeights['sparkline_weight']}%
- Volume: {$criteriaWeights['volume_weight']}%
- Market Cap: {$criteriaWeights['market_cap_weight']}%

Justifie cet achat en 2-3 phrases techniques et précises. Mentionne les indicateurs clés.";
            
            $justification = $mistralClient->callMistral($prompt, 'mistral-small-2603');
            
            // Exécuter l'achat
            appLog("EXECUTING BUY: $symbol x $quantity @ $currentPrice € (Score: $score)");
            
            $tradingLogic->openOrUpdatePosition(
                $coinId, 
                $symbol, 
                $coin['name'], 
                $quantity, 
                $currentPrice, 
                $investmentPerTrade
            );
            
            $tradeId = $tradingLogic->recordTrade(
                $coinId,
                $symbol,
                'BUY',
                $quantity,
                $currentPrice,
                $investmentPerTrade,
                $score,
                $analysisText,
                $justification
            );
            
            // Mettre à jour le cash
            $stmt = $pdo->prepare("UPDATE virtual_portfolio SET current_cash = current_cash - ? WHERE id = 1");
            $stmt->execute([$investmentPerTrade]);
            
            $tradesExecuted++;
            appLog("BUY executed for $symbol. Trade ID: $tradeId");
        }
        
        // ========================================================================
        // LOGIQUE DE VENTE (score <= 35 OU STOP-LOSS)
        // ========================================================================
        $existingPosition = $tradingLogic->getPosition($coinId);
        
        // Vérifier le stop-loss virtuel (-15%)
        $stopLossTriggered = false;
        if ($existingPosition && $existingPosition['pnl_percent'] <= $stopLossPercent) {
            $stopLossTriggered = true;
            appLog("STOP-LOSS TRIGGERED: $symbol - P&L: {$existingPosition['pnl_percent']}% (seuil: $stopLossPercent%)", 'WARNING');
        }
        
        if ($score <= $sellThreshold || $stopLossTriggered) {
            if (!$existingPosition) {
                // Pas de position à vendre
                continue;
            }
            
            // Calculer la valeur de vente
            $quantity = $existingPosition['quantity'];
            $sellValue = $quantity * $currentPrice;
            
            // Déterminer la raison de la vente
            $saleReason = $stopLossTriggered ? "Stop-loss automatique" : "Vente par score bas";
            
            // Demander justification à Mistral
            $investedAmount = $existingPosition['invested_amount'];
            $unrealizedPnl = $existingPosition['unrealized_pnl'];
            $pnlPercent = $existingPosition['pnl_percent'];
            
            $prompt = "Tu es un trader IA expert. Tu vas vendre ta position $symbol (ID: $coinId) à $currentPrice €.\n";
            if ($stopLossTriggered) {
                $prompt .= "** STOP-LOSS DÉCLENCHÉ **\n";
                $prompt .= "Perte actuelle: $unrealizedPnl € (" . number_format($pnlPercent, 2) . "%)\n";
                $prompt .= "Seuil de stop-loss: $stopLossPercent%\n\n";
            } else {
                $prompt .= "Score IA actuel: $score/100 (seuil de vente: $sellThreshold)\n";
            }
            $prompt .= "Quantité: $quantity\n
Prix moyen d'achat: {$existingPosition['avg_buy_price']} €\n
P&L non réalisé: $unrealizedPnl € (" . number_format($pnlPercent, 2) . "%)\n
\nJustifie cette vente en 2-3 phrases. Explique si c'est un stop-loss, take-profit, ou changement de tendance.";
            
            try {
                $justification = $mistralClient->callMistral($prompt, 'mistral-small-2603');
            } catch (Exception $e) {
                appLog("Error getting AI justification for SELL $symbol: " . $e->getMessage(), 'ERROR');
                $justification = "Vente automatique - Justification IA indisponible";
            }
            
            // Exécuter la vente
            appLog("EXECUTING SELL: $symbol x $quantity @ $currentPrice € (Score: $score, P&L: $unrealizedPnl €, Reason: $saleReason)");
            
            try {
                $result = $tradingLogic->closePosition($coinId, $currentPrice, $quantity);
                
                if ($result) {
                    // Calculer la période de détention
                    $holdingPeriodHours = max(1, (time() - $existingPosition['first_purchase']) / 3600);
                    
                    $tradeId = $tradingLogic->recordTrade(
                        $coinId,
                        $symbol,
                        'SELL',
                        $quantity,
                        $currentPrice,
                        $sellValue,
                        $score,
                        "$saleReason - Score: $score",
                        $justification,
                        $existingPosition['id']
                    );
                    
                    // Mettre à jour le trade avec le PnL réalisé
                    $tradingLogic->updateTradePnl($tradeId, $result['realized_pnl'], $result['pnl_percent'], $holdingPeriodHours);
                    
                    // Mettre à jour le cash
                    $stmt = $pdo->prepare("UPDATE virtual_portfolio SET current_cash = current_cash + ? WHERE id = 1");
                    $stmt->execute([$sellValue]);
                    
                    $tradesExecuted++;
                    appLog("SELL executed for $symbol. Trade ID: $tradeId, Realized P&L: {$result['realized_pnl']} €, Reason: $saleReason");
                } else {
                    appLog("SELL failed for $symbol: closePosition returned false", 'ERROR');
                }
            } catch (Exception $e) {
                appLog("CRITICAL ERROR executing SELL for $symbol: " . $e->getMessage(), 'CRITICAL');
            }
        }
    }
    
    // ============================================================================
    // ÉTAPE 3 : Audit RL des trades anciens (plus de 24h)
    // ============================================================================
    appLog('Running RL audit on old trades...');
    
    $auditStmt = $pdo->query("SELECT id FROM virtual_trades 
        WHERE timestamp < " . (time() - 86400) . " 
        AND id NOT IN (SELECT trade_id FROM trade_audits)
        ORDER BY timestamp ASC LIMIT 10");
    
    $oldTrades = $auditStmt->fetchAll(PDO::FETCH_COLUMN);
    $auditedCount = 0;
    
    foreach ($oldTrades as $tradeId) {
        if ($tradingLogic->auditTrade($tradeId)) {
            $auditedCount++;
            appLog("Audit completed for trade ID: $tradeId");
        }
    }
    
    appLog("RL Audit: $auditedCount trades audited");
    
    // ============================================================================
    // ÉTAPE 4 : Générer un article de blog récapitulatif (optionnel)
    // ============================================================================
    if ($tradesExecuted > 0) {
        appLog('Generating blog summary...');
        
        $recentTrades = $tradingLogic->getTradeHistory(5);
        $tradeSummary = "";
        foreach ($recentTrades as $trade) {
            $tradeSummary .= "- {$trade['action']} {$trade['coin_symbol']} @ {$trade['price']} € (Score: {$trade['score_trigger']})\n";
        }
        
        $prompt = "Résume l'activité de trading récente en un court article de blog (150-200 mots).
        
Activité récente :
$tradeSummary

Performance du portefeuille : " . number_format($portfolio['performance_percent'], 2) . "%
Taux de réussite : " . number_format($portfolio['win_rate'], 1) . "%

Ton style : professionnel, pédagogique, orienté données.";
        
        try {
            $blogContent = $mistralClient->callMistral($prompt, 'labs-mistral-small-creative');
            
            // Enregistrer l'article
            $title = "Bulletin Trading IA - " . date('d/m/Y H:i');
            $stmt = $pdo->prepare("INSERT INTO ai_blog_posts (title, content, created_at, tags, category, sentiment, reading_time_minutes) VALUES (?, ?, ?, 'trading,IA,crypto', 'trading', 'neutral', 2)");
            $stmt->execute([$title, $blogContent, time()]);
            
            appLog('Blog post generated successfully');
        } catch (Exception $e) {
            appLog('Blog generation failed: ' . $e->getMessage(), 'WARNING');
        }
    }
    
    // ============================================================================
    // FINALISATION
    // ============================================================================
    $tradingLogic->updatePortfolioStats();
    $tradingLogic->updatePortfolioValue();
    
    appLog("=== TRADING ENGINE COMPLETED ===");
    appLog("Trades executed: $tradesExecuted | Audits performed: $auditedCount");
    
} catch (Exception $e) {
    appLog('TRADING ENGINE CRITICAL ERROR: ' . $e->getMessage(), 'CRITICAL');
    // Enregistrer la stack trace pour débogage
    appLog('Stack trace: ' . $e->getTraceAsString(), 'CRITICAL');
} finally {
    // Supprimer le lock
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

echo json_encode([
    'status' => 'success',
    'timestamp' => time(),
    'trades_executed' => $tradesExecuted ?? 0,
    'audits_performed' => $auditedCount ?? 0
]);
?>
