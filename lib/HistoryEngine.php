<?php
/**
 * HistoryEngine.php
 * Moteur de gestion de l'historique, des audits et des revues de marché.
 * Gère l'archivage des analyses, le calcul de performance et la génération de rapports.
 */

class HistoryEngine {
    private $pdo;

    public function __construct() {
        if (!defined('DB_FILE')) {
            require_once __DIR__ . '/../config.php';
        }
        
        try {
            $this->pdo = new PDO("sqlite:" . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Connexion DB échouée dans HistoryEngine: " . $e->getMessage());
        }
    }

    /**
     * Archive une analyse récente depuis la table 'individual_analysis' vers 'analyses_history'
     * Évite les doublons trop rapprochés (moins de 5 minutes).
     */
    public function archiveAnalysis($cryptoId, $symbol) {
        try {
            // Récupérer la dernière analyse en cours
            $stmt = $this->pdo->prepare("SELECT * FROM individual_analysis WHERE crypto_id = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$cryptoId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) return false;

            // Vérifier si on a déjà archivé cette version spécifique récemment
            $check = $this->pdo->prepare("SELECT id FROM analyses_history WHERE crypto_id = ? AND created_at > datetime('now', '-5 minutes')");
            $check->execute([$cryptoId]);
            if ($check->fetch()) return false; // Déjà archivé récemment

            // Déterminer le conseil basé sur le score
            $score = (int)$current['score'];
            $conseil = 'ATTENTE';
            if ($score >= 65) $conseil = 'ACHAT';
            elseif ($score <= 35) $conseil = 'VENTE';

            // Snapshot des données techniques
            $sparkline = $current['sparkline_7d'] ?? '[]';
            $rsi = $current['rsi_14'] ?? 50;
            $vol = $current['volume_24h'] ?? 0;
            $price = $current['price'] ?? 0;
            $analysisText = $current['analysis_summary'] ?? 'Analyse non disponible';

            $insert = $this->pdo->prepare("
                INSERT INTO analyses_history 
                (crypto_id, symbol, score, conseil, analyse_text, sparkline_snapshot, rsi_snapshot, volume_snapshot, price_at_analysis, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            return $insert->execute([
                $cryptoId,
                $symbol,
                $score,
                $conseil,
                $analysisText,
                $sparkline,
                $rsi,
                $vol,
                $price,
                $current['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Erreur archiveAnalysis: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Audit rétrospectif : Compare le prix d'achat théorique vs prix actuel
     * Met à jour was_correct (1 si gain, 0 si perte) pour les analyses de plus de 24h
     */
    public function runAudit() {
        echo "Lancement de l'audit historique...\n";
        
        try {
            // Récupérer les analyses non auditées datant de plus de 24h
            $stmt = $this->pdo->query("
                SELECT * FROM analyses_history 
                WHERE was_correct IS NULL 
                AND created_at < datetime('now', '-24 hours')
                LIMIT 100
            ");
            
            $histories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $updatedCount = 0;

            foreach ($histories as $h) {
                $currentPrice = $this->getCurrentPrice($h['crypto_id']);
                if (!$currentPrice) continue;

                $entryPrice = (float)$h['price_at_analysis'];
                $advice = $h['conseil'];
                
                $isCorrect = 0; // Par défaut faux
                $gainPercent = 0;

                if ($advice === 'ACHAT') {
                    // Si on a dit achat, c'est bon si le prix a monté
                    if ($currentPrice > $entryPrice) $isCorrect = 1;
                    $gainPercent = (($currentPrice - $entryPrice) / $entryPrice) * 100;
                } elseif ($advice === 'VENTE') {
                    // Si on a dit vente, c'est bon si le prix a baissé
                    if ($currentPrice < $entryPrice) $isCorrect = 1;
                    $gainPercent = (($entryPrice - $currentPrice) / $entryPrice) * 100;
                } else {
                    // Pour ATTENTE, logique complexe (on ignore pour l'instant ou neutre)
                    $isCorrect = null; 
                }

                if ($isCorrect !== null) {
                    $update = $this->pdo->prepare("
                        UPDATE analyses_history 
                        SET was_correct = ?, price_at_audit = ?, audit_date = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $update->execute([$isCorrect, $currentPrice, $h['id']]);
                    $updatedCount++;
                    
                    echo "Audit ID {$h['id']} ({$h['symbol']}): Conseil {$advice} -> " . ($isCorrect ? "SUCCÈS" : "ÉCHEC") . "\n";
                }
            }
            echo "Audit terminé. $updatedCount analyses évaluées.\n";
            return $updatedCount;
        } catch (Exception $e) {
            error_log("Erreur runAudit: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Helper pour récupérer le prix actuel depuis la table principale
     */
    private function getCurrentPrice($cryptoId) {
        try {
            $stmt = $this->pdo->prepare("SELECT price FROM individual_analysis WHERE crypto_id = ? LIMIT 1");
            $stmt->execute([$cryptoId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ? (float)$res['price'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Calcule la fiabilité par crypto
     * Retourne un tableau avec symbol, total_audits, correct_count, accuracy_percent
     */
    public function getReliabilityStats($limit = 20) {
        try {
            $sql = "
                SELECT 
                    symbol,
                    COUNT(*) as total_audits,
                    SUM(was_correct) as correct_count,
                    ROUND((SUM(was_correct) * 100.0 / COUNT(*)), 2) as accuracy_percent
                FROM analyses_history
                WHERE was_correct IS NOT NULL
                GROUP BY symbol
                ORDER BY accuracy_percent DESC
                LIMIT $limit
            ";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Génère une revue de marché globale
     * Simule un appel IA pour résumer la période
     */
    public function generateMarketReview() {
        try {
            // Récupérer les top picks basés sur les scores actuels élevés
            $stmt = $this->pdo->query("SELECT symbol, score FROM individual_analysis WHERE score >= 70 ORDER BY score DESC LIMIT 5");
            $topPicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $jsonPicks = json_encode(array_column($topPicks, 'symbol'));
            
            // Génération dynamique du texte selon l'état du marché
            $countHigh = count($topPicks);
            $dateNow = date('Y-m-d');
            
            if ($countHigh > 3) {
                $reviewText = "Le marché présente aujourd'hui de nombreuses opportunités haussières. Plusieurs actifs majeurs affichent des signaux techniques forts (RSI favorable, volumes en hausse). C'est potentiellement le moment d'être agressif sur l'allocation.";
                $globalAdvice = "Opportunités d'achat multiples identifiées sur : " . implode(', ', array_column($topPicks, 'symbol')) . ".";
            } elseif ($countHigh > 0) {
                $reviewText = "Le marché est mitigé. Seuls quelques actifs se détachent du lot avec des signaux positifs. La prudence reste de mise sur les altcoins mineures.";
                $globalAdvice = "Achat sélectif recommandé sur : " . implode(', ', array_column($topPicks, 'symbol')) . ". Surveiller le reste.";
            } else {
                $reviewText = "Le marché est actuellement faible ou en consolidation. Aucun signal d'achat clair n'émerge des analyses automatiques. Il est préférable de conserver ses positions ou de réduire l'exposition.";
                $globalAdvice = "Attente recommandée. Pas de nouveaux achats pour le moment.";
            }

            $insert = $this->pdo->prepare("
                INSERT INTO market_reviews (review_text, global_advice, top_picks, period_start, period_end)
                VALUES (?, ?, ?, datetime('now', '-7 days'), datetime('now'))
            ");
            
            $success = $insert->execute([$reviewText, $globalAdvice, $jsonPicks]);
            echo "Revue de marché générée avec succès.\n";
            return $success;
        } catch (Exception $e) {
            error_log("Erreur generateMarketReview: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtient l'historique complet pour l'affichage
     */
    public function getFullHistory($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['crypto_id']) && $filters['crypto_id'] !== 'all') {
            $where[] = "crypto_id = ?";
            $params[] = $filters['crypto_id'];
        }
        if (!empty($filters['conseil']) && $filters['conseil'] !== 'all') {
            $where[] = "conseil = ?";
            $params[] = $filters['conseil'];
        }
        if (isset($filters['was_correct']) && $filters['was_correct'] !== 'all') {
            if ($filters['was_correct'] === 'validé') {
                $where[] = "was_correct = 1";
            } elseif ($filters['was_correct'] === 'erreur') {
                $where[] = "was_correct = 0";
            } elseif ($filters['was_correct'] === 'en_attente') {
                $where[] = "was_correct IS NULL";
            }
        }
        
        $sql = "SELECT * FROM analyses_history WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC LIMIT 200";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les données pour le graphique d'une crypto spécifique
     */
    public function getChartData($cryptoId, $limit = 30) {
        $sql = "SELECT created_at, score, price_at_analysis as price FROM analyses_history WHERE crypto_id = ? ORDER BY created_at DESC LIMIT $limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cryptoId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_reverse($data); // Ordre chronologique
    }
    
    /**
     * Liste toutes les cryptos uniques dans l'historique
     */
    public function getUniqueCryptos() {
        $sql = "SELECT DISTINCT crypto_id, symbol FROM analyses_history ORDER BY symbol ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les dernières revues de marché
     */
    public function getReviews($limit = 5) {
        $sql = "SELECT * FROM market_reviews ORDER BY created_at DESC LIMIT $limit";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Stats globales pour le dashboard
     */
    public function getGlobalStats() {
        $sqlTotal = "SELECT COUNT(*) as total FROM analyses_history";
        $sqlAccuracy = "SELECT ROUND(SUM(was_correct) * 100.0 / COUNT(*), 2) as accuracy FROM analyses_history WHERE was_correct IS NOT NULL";
        
        $total = $this->pdo->query($sqlTotal)->fetchColumn();
        $accuracy = $this->pdo->query($sqlAccuracy)->fetchColumn();
        
        return [
            'total' => $total,
            'accuracy' => $accuracy !== null ? $accuracy : 0
        ];
    }
}
?>
