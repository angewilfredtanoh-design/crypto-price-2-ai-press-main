<?php
/**
 * lib/TradingLogic.php
 * Logique métier complète pour le système de trading virtuel
 * Gestion des positions, trades, audits RL et ajustement des critères
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__FILE__)));
}

require_once ROOT_DIR . '/config.php';
require_once ROOT_DIR . '/lib/MistralClient.php';

class TradingLogic {
    private $pdo;
    private $mistralClient;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->mistralClient = new MistralClient();
        } catch (Exception $e) {
            appLog('TradingLogic initialization failed: ' . $e->getMessage(), 'CRITICAL');
            throw $e;
        }
    }
    
    // ============================================================================
    // GESTION DU PORTEFEUILLE
    // ============================================================================
    
    /**
     * Obtenir l'état du portefeuille virtuel
     */
    public function getPortfolio() {
        $stmt = $this->pdo->query("SELECT * FROM virtual_portfolio WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour la valeur totale du portefeuille
     */
    public function updatePortfolioValue() {
        try {
            $portfolio = $this->getPortfolio();
            if (!$portfolio) return false;
            
            // Calculer la valeur des positions actives
            $stmt = $this->pdo->query("SELECT SUM(current_value) as total_invested, SUM(unrealized_pnl) as total_unrealized FROM virtual_positions WHERE is_active = 1");
            $positions = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totalInvested = $positions['total_invested'] ?? 0;
            $totalUnrealized = $positions['total_unrealized'] ?? 0;
            $currentCash = $portfolio['current_cash'];
            $totalValue = $currentCash + $totalInvested;
            $performancePercent = (($totalValue - $portfolio['initial_capital']) / $portfolio['initial_capital']) * 100;
            
            $stmt = $this->pdo->prepare("UPDATE virtual_portfolio SET 
                total_value = ?, 
                total_invested = ?, 
                total_unrealized_pnl = ?, 
                performance_percent = ?, 
                last_update = ? 
                WHERE id = 1");
            $stmt->execute([$totalValue, $totalInvested, $totalUnrealized, $performancePercent, time()]);
            
            return true;
        } catch (Exception $e) {
            appLog('updatePortfolioValue failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    // ============================================================================
    // GESTION DES POSITIONS
    // ============================================================================
    
    /**
     * Obtenir toutes les positions actives
     */
    public function getActivePositions() {
        $stmt = $this->pdo->query("SELECT * FROM virtual_positions WHERE is_active = 1 ORDER BY current_value DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir une position spécifique
     */
    public function getPosition($coinId) {
        $stmt = $this->pdo->prepare("SELECT * FROM virtual_positions WHERE coin_id = ? AND is_active = 1");
        $stmt->execute([$coinId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Créer ou mettre à jour une position (achat)
     */
    public function openOrUpdatePosition($coinId, $symbol, $name, $quantity, $price, $totalValue) {
        try {
            $position = $this->getPosition($coinId);
            $now = time();
            
            if ($position) {
                // Position existante : moyenne pondérée
                $totalQuantity = $position['quantity'] + $quantity;
                $totalCost = ($position['quantity'] * $position['avg_buy_price']) + $totalValue;
                $newAvgPrice = $totalCost / $totalQuantity;
                
                $stmt = $this->pdo->prepare("UPDATE virtual_positions SET 
                    quantity = ?, 
                    avg_buy_price = ?, 
                    invested_amount = invested_amount + ?, 
                    last_purchase = ?,
                    current_price = ?,
                    current_value = ?,
                    unrealized_pnl = (current_value - invested_amount),
                    pnl_percent = ((current_value - invested_amount) / invested_amount) * 100
                    WHERE coin_id = ? AND is_active = 1");
                $stmt->execute([$totalQuantity, $newAvgPrice, $totalValue, $now, $price, $totalQuantity * $price, $coinId]);
                
                return $this->getPosition($coinId);
            } else {
                // Nouvelle position
                $stmt = $this->pdo->prepare("INSERT INTO virtual_positions 
                    (coin_id, coin_symbol, coin_name, quantity, avg_buy_price, current_price, invested_amount, current_value, unrealized_pnl, pnl_percent, first_purchase, last_purchase, position_size_percent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, 0)");
                $stmt->execute([$coinId, $symbol, $name, $quantity, $price, $price, $totalValue, $totalValue, $now, $now]);
                
                return $this->getPosition($coinId);
            }
        } catch (Exception $e) {
            appLog('openOrUpdatePosition failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Fermer une position (vente)
     */
    public function closePosition($coinId, $sellPrice, $sellQuantity) {
        try {
            $position = $this->getPosition($coinId);
            if (!$position) return false;
            
            $sellValue = $sellQuantity * $sellPrice;
            $costBasis = $sellQuantity * $position['avg_buy_price'];
            $realizedPnl = $sellValue - $costBasis;
            $pnlPercent = ($costBasis > 0) ? ($realizedPnl / $costBasis) * 100 : 0;
            
            $remainingQuantity = $position['quantity'] - $sellQuantity;
            
            if ($remainingQuantity <= 0.0001) {
                // Position complètement fermée
                $stmt = $this->pdo->prepare("UPDATE virtual_positions SET 
                    is_active = 0, 
                    closed_at = ?, 
                    current_price = ?,
                    unrealized_pnl = 0
                    WHERE coin_id = ? AND is_active = 1");
                $stmt->execute([time(), $sellPrice, $coinId]);
            } else {
                // Position partiellement fermée
                $newInvested = $position['invested_amount'] - $costBasis;
                $newValue = $remainingQuantity * $sellPrice;
                
                $stmt = $this->pdo->prepare("UPDATE virtual_positions SET 
                    quantity = ?, 
                    invested_amount = ?, 
                    current_value = ?, 
                    current_price = ?,
                    unrealized_pnl = (current_value - invested_amount),
                    pnl_percent = ((current_value - invested_amount) / invested_amount) * 100
                    WHERE coin_id = ? AND is_active = 1");
                $stmt->execute([$remainingQuantity, $newInvested, $newValue, $sellPrice, $coinId]);
            }
            
            return [
                'realized_pnl' => $realizedPnl,
                'pnl_percent' => $pnlPercent,
                'closed' => ($remainingQuantity <= 0.0001)
            ];
        } catch (Exception $e) {
            appLog('closePosition failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Mettre à jour les prix de toutes les positions
     */
    public function updatePositionPrices() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM virtual_positions WHERE is_active = 1");
            $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($positions as $position) {
                // Récupérer le prix actuel depuis la table coins
                $coinStmt = $this->pdo->prepare("SELECT current_price FROM coins WHERE id = ?");
                $coinStmt->execute([$position['coin_id']]);
                $coin = $coinStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coin && $coin['current_price'] > 0) {
                    $currentPrice = $coin['current_price'];
                    $currentValue = $position['quantity'] * $currentPrice;
                    $unrealizedPnl = $currentValue - $position['invested_amount'];
                    $pnlPercent = ($position['invested_amount'] > 0) ? ($unrealizedPnl / $position['invested_amount']) * 100 : 0;
                    
                    $updateStmt = $this->pdo->prepare("UPDATE virtual_positions SET 
                        current_price = ?, 
                        current_value = ?, 
                        unrealized_pnl = ?, 
                        pnl_percent = ? 
                        WHERE id = ?");
                    $updateStmt->execute([$currentPrice, $currentValue, $unrealizedPnl, $pnlPercent, $position['id']]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            appLog('updatePositionPrices failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    // ============================================================================
    // GESTION DES TRADES
    // ============================================================================
    
    /**
     * Enregistrer un trade
     */
    public function recordTrade($coinId, $symbol, $action, $quantity, $price, $totalValue, $score, $aiReasoning, $aiJustification = null, $positionId = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO virtual_trades 
                (portfolio_id, coin_id, coin_symbol, action, quantity, price, total_value, score_trigger, ai_reasoning, ai_justification, timestamp, position_id) 
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$coinId, $symbol, $action, $quantity, $price, $totalValue, $score, $aiReasoning, $aiJustification, time(), $positionId]);
            
            $tradeId = $this->pdo->lastInsertId();
            
            // Mettre à jour les statistiques du portefeuille
            $this->updatePortfolioStats();
            
            return $tradeId;
        } catch (Exception $e) {
            appLog('recordTrade failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Mettre à jour un trade avec le PnL réalisé
     */
    public function updateTradePnl($tradeId, $realizedPnl, $pnlPercent, $holdingPeriodHours) {
        try {
            $stmt = $this->pdo->prepare("UPDATE virtual_trades SET 
                realized_pnl = ?, 
                pnl_percent = ?, 
                holding_period_hours = ? 
                WHERE id = ?");
            $stmt->execute([$realizedPnl, $pnlPercent, $holdingPeriodHours, $tradeId]);
            
            return true;
        } catch (Exception $e) {
            appLog('updateTradePnl failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtenir l'historique des trades
     */
    public function getTradeHistory($limit = 50) {
        $stmt = $this->pdo->query("SELECT * FROM virtual_trades ORDER BY timestamp DESC LIMIT $limit");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour les statistiques du portefeuille
     */
    public function updatePortfolioStats() {
        try {
            $stmt = $this->pdo->query("SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) as winning_trades,
                SUM(CASE WHEN realized_pnl < 0 THEN 1 ELSE 0 END) as losing_trades,
                SUM(realized_pnl) as total_realized_pnl,
                MAX(realized_pnl) as best_trade,
                MIN(realized_pnl) as worst_trade,
                AVG(CASE WHEN realized_pnl > 0 THEN realized_pnl ELSE NULL END) as avg_win,
                AVG(CASE WHEN realized_pnl < 0 THEN realized_pnl ELSE NULL END) as avg_loss
                FROM virtual_trades WHERE realized_pnl IS NOT NULL AND realized_pnl != 0");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totalTrades = $stats['total_trades'] ?? 0;
            $winningTrades = $stats['winning_trades'] ?? 0;
            $losingTrades = $stats['losing_trades'] ?? 0;
            $winRate = ($totalTrades > 0) ? ($winningTrades / $totalTrades) * 100 : 0;
            $profitFactor = ($stats['avg_loss'] != 0 && $stats['avg_loss'] < 0) ? abs($stats['avg_win'] / $stats['avg_loss']) : 0;
            
            $stmt = $this->pdo->prepare("UPDATE virtual_portfolio SET 
                total_trades = ?, 
                winning_trades = ?, 
                losing_trades = ?, 
                win_rate = ?, 
                total_realized_pnl = ?, 
                best_trade = ?, 
                worst_trade = ?, 
                avg_win = ?, 
                avg_loss = ?, 
                profit_factor = ?,
                last_update = ? 
                WHERE id = 1");
            $stmt->execute([
                $totalTrades, $winningTrades, $losingTrades, $winRate,
                $stats['total_realized_pnl'] ?? 0,
                $stats['best_trade'] ?? 0,
                $stats['worst_trade'] ?? 0,
                $stats['avg_win'] ?? 0,
                $stats['avg_loss'] ?? 0,
                $profitFactor,
                time()
            ]);
            
            return true;
        } catch (Exception $e) {
            appLog('updatePortfolioStats failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    // ============================================================================
    // AUDIT RL ET AJUSTEMENT DES CRITÈRES
    // ============================================================================
    
    /**
     * Auditer un trade passé
     */
    public function auditTrade($tradeId) {
        try {
            // Récupérer le trade
            $stmt = $this->pdo->prepare("SELECT * FROM virtual_trades WHERE id = ?");
            $stmt->execute([$tradeId]);
            $trade = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$trade) return false;
            
            // Vérifier si déjà audité
            $auditStmt = $this->pdo->prepare("SELECT id FROM trade_audits WHERE trade_id = ?");
            $auditStmt->execute([$tradeId]);
            if ($auditStmt->fetch()) return false; // Déjà audité
            
            // Récupérer le prix actuel
            $coinStmt = $this->pdo->prepare("SELECT current_price FROM coins WHERE id = ?");
            $coinStmt->execute([$trade['coin_id']]);
            $coin = $coinStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coin || $coin['current_price'] == 0) return false;
            
            $currentPrice = $coin['current_price'];
            $deltaPrice = $currentPrice - $trade['price'];
            $deltaPercent = ($trade['price'] > 0) ? ($deltaPrice / $trade['price']) * 100 : 0;
            
            // Déterminer le résultat
            $result = 'BREAK_EVEN';
            $verdict = 'NEUTRE';
            $pnlRealized = 0;
            
            if ($trade['action'] === 'BUY') {
                $pnlRealized = $deltaPrice * $trade['quantity'];
                if ($deltaPercent > 2) {
                    $result = 'WIN';
                    $verdict = 'BON';
                } elseif ($deltaPercent < -2) {
                    $result = 'LOSS';
                    $verdict = 'MAUVAIS';
                }
            } else { // SELL
                $pnlRealized = -$deltaPrice * $trade['quantity'];
                if ($deltaPercent < -2) {
                    $result = 'WIN';
                    $verdict = 'BON';
                } elseif ($deltaPercent > 2) {
                    $result = 'LOSS';
                    $verdict = 'MAUVAIS';
                }
            }
            
            // Demander à Mistral d'analyser le trade
            $prompt = $this->buildAuditPrompt($trade, $currentPrice, $deltaPercent, $result, $verdict);
            $analysis = $this->mistralClient->callMistral($prompt, 'mistral-medium-2508');
            
            // Extraire les recommandations et ajustements (simplifié)
            $recommendations = $analysis;
            $criteriaAdjustments = json_encode(['auto_adjusted' => true, 'timestamp' => time()]);
            
            // Enregistrer l'audit
            $stmt = $this->pdo->prepare("INSERT INTO trade_audits 
                (trade_id, coin_id, action, trade_price, audit_price, delta_price, delta_percent, result, pnl_realized, verdict, ai_analysis, ai_recommendations, criteria_adjustments, audited_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tradeId, $trade['coin_id'], $trade['action'], 
                $trade['price'], $currentPrice, $deltaPrice, $deltaPercent,
                $result, $pnlRealized, $verdict,
                $analysis, $recommendations, $criteriaAdjustments, time()
            ]);
            
            $auditId = $this->pdo->lastInsertId();
            
            // Si le trade était mauvais, ajuster les critères
            if ($verdict === 'MAUVAIS') {
                $this->adjustCriteriaWeights($auditId, $trade);
            }
            
            return $auditId;
        } catch (Exception $e) {
            appLog('auditTrade failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Construire le prompt pour l'audit RL
     */
    private function buildAuditPrompt($trade, $currentPrice, $deltaPercent, $result, $verdict) {
        $action = $trade['action'];
        $coin = $trade['coin_symbol'];
        $tradePrice = $trade['price'];
        $score = $trade['score_trigger'];
        $reasoning = $trade['ai_reasoning'];
        
        return "Tu es un système de trading IA expert en apprentissage par renforcement.
        
ANALYSE DE TRADE PASSÉ :
- Crypto : $coin
- Action : $action à $tradePrice €
- Score IA déclencheur : $score/100
- Raisonnement initial : $reasoning
- Prix actuel : $currentPrice €
- Variation depuis le trade : " . number_format($deltaPercent, 2) . "%
- Résultat : $result
- Verdict : $verdict

TÂCHE :
1. Analyse objectivement si la décision était correcte
2. Identifie les erreurs dans le raisonnement initial
3. Explique ce qui a fonctionné ou échoué
4. Propose des ajustements concrets pour les futurs trades
5. Recommande des modifications aux critères de scoring (RSI, sparkline, volume, market cap)

Sois précis, technique, et orienté amélioration continue.";
    }
    
    /**
     * Ajuster les pondérations des critères après un trade perdant
     */
    public function adjustCriteriaWeights($auditId, $trade) {
        try {
            // Récupérer les poids actuels
            $stmt = $this->pdo->query("SELECT * FROM criteria_weights ORDER BY id DESC LIMIT 1");
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) return false;
            
            // Analyser le raisonnement pour identifier le critère dominant
            $reasoning = strtolower($trade['ai_reasoning'] ?? '');
            $dominantCriterion = 'rsi'; // Par défaut
            
            if (strpos($reasoning, 'rsi') !== false) {
                $dominantCriterion = 'rsi';
            } elseif (strpos($reasoning, 'trend') !== false || strpos($reasoning, 'sparkline') !== false) {
                $dominantCriterion = 'sparkline';
            } elseif (strpos($reasoning, 'volume') !== false) {
                $dominantCriterion = 'volume';
            } elseif (strpos($reasoning, 'market cap') !== false || strpos($reasoning, 'capitalisation') !== false) {
                $dominantCriterion = 'market_cap';
            }
            
            // Réduire de 5% le critère dominant
            $reduction = 5;
            $newWeights = [
                'rsi_weight' => $current['rsi_weight'],
                'sparkline_weight' => $current['sparkline_weight'],
                'volume_weight' => $current['volume_weight'],
                'market_cap_weight' => $current['market_cap_weight']
            ];
            
            if (isset($newWeights[$dominantCriterion . '_weight'])) {
                $newWeights[$dominantCriterion . '_weight'] = max(5, $newWeights[$dominantCriterion . '_weight'] - $reduction);
            }
            
            // Recalculer pour que le total fasse 100%
            $total = array_sum($newWeights);
            if ($total != 100 && $total > 0) {
                $factor = 100 / $total;
                foreach ($newWeights as $key => $value) {
                    $newWeights[$key] = round($value * $factor, 2);
                }
            }
            
            // Demander à Mistral de valider les ajustements
            $prompt = "Les poids de critères étaient : RSI={$current['rsi_weight']}%, Sparkline={$current['sparkline_weight']}%, Volume={$current['volume_weight']}%, Market Cap={$current['market_cap_weight']}%.
            
Un trade perdant vient d'être détecté. Le critère dominant était : $dominantCriterion.
Nouveaux poids proposés : RSI={$newWeights['rsi_weight']}%, Sparkline={$newWeights['sparkline_weight']}%, Volume={$newWeights['volume_weight']}%, Market Cap={$newWeights['market_cap_weight']}%.

Valide-tu ces ajustements ? Propose une brève justification.";
            
            $aiValidation = $this->mistralClient->callMistral($prompt, 'mistral-small-2603');
            
            // Enregistrer les nouveaux poids
            $stmt = $this->pdo->prepare("INSERT INTO criteria_weights 
                (rsi_weight, sparkline_weight, volume_weight, market_cap_weight, total_weight, last_adjustment, adjustment_reason, adjusted_by_ai, trade_audit_id) 
                VALUES (?, ?, ?, ?, 100, ?, ?, ?, ?)");
            $stmt->execute([
                $newWeights['rsi_weight'],
                $newWeights['sparkline_weight'],
                $newWeights['volume_weight'],
                $newWeights['market_cap_weight'],
                time(),
                "Ajustement après trade perdant sur $dominantCriterion",
                $aiValidation,
                $auditId
            ]);
            
            appLog("Criteria weights adjusted: $dominantCriterion reduced by $reduction%");
            
            return $newWeights;
        } catch (Exception $e) {
            appLog('adjustCriteriaWeights failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Obtenir les poids actuels des critères
     */
    public function getCurrentCriteriaWeights() {
        $stmt = $this->pdo->query("SELECT * FROM criteria_weights ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Auditer tous les trades non audités
     */
    public function auditAllPendingTrades() {
        try {
            $stmt = $this->pdo->query("SELECT id FROM virtual_trades WHERE id NOT IN (SELECT trade_id FROM trade_audits) ORDER BY timestamp ASC");
            $trades = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $audited = 0;
            foreach ($trades as $tradeId) {
                if ($this->auditTrade($tradeId)) {
                    $audited++;
                }
            }
            
            return $audited;
        } catch (Exception $e) {
            appLog('auditAllPendingTrades failed: ' . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
}
?>
