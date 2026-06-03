<?php
/**
 * src/Services/HistoryService.php
 * Moteur de gestion de l'historique, des audits et des revues de marché.
 * Gère l'archivage des analyses, le calcul de performance et la génération de rapports.
 */

namespace Crypto\Services;

use Crypto\Core\Database;
use PDO;
use PDOException;

class HistoryService {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = Database::getInstance()->getConnection();
        } catch (PDOException $e) {
            throw new \Exception("Connexion DB échouée dans HistoryService: " . $e->getMessage());
        }
    }

    /**
     * Archive une analyse récente depuis la table 'individual_analysis' vers 'analyses_history'
     * Évite les doublons trop rapprochés (moins de 5 minutes).
     */
    public function archiveAnalysis($coinId, $symbol) {
        try {
            // Récupérer la dernière analyse en cours (colonne coin_id dans individual_analysis)
            $stmt = $this->pdo->prepare("SELECT * FROM individual_analysis WHERE coin_id = ? ORDER BY generated_at DESC LIMIT 1");
            $stmt->execute([$coinId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) return false;

            // Vérifier si on a déjà archivé cette version spécifique récemment
            $check = $this->pdo->prepare("SELECT id FROM analyses_history WHERE crypto_id = ? AND created_at > datetime('now', '-5 minutes')");
            $check->execute([$coinId]);
            if ($check->fetch()) return false; // Déjà archivé récemment

            // Déterminer le conseil basé sur le score
            $score = (int)$current['score'];
            $conseil = 'ATTENTE';
            if ($score >= 65) $conseil = 'ACHAT';
            elseif ($score <= 35) $conseil = 'VENTE';

            // Snapshot des données techniques (adapter aux colonnes réelles)
            $sparkline = '[]'; // Pas de sparkline dans individual_analysis actuel
            $rsi = 50; // Valeur par défaut
            
            // Insérer dans l'historique
            $insert = $this->pdo->prepare("
                INSERT INTO analyses_history 
                (crypto_id, symbol, score, conseil, rsi, macd, moving_average, sparkline_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $insert->execute([
                $coinId,
                $symbol,
                $score,
                $conseil,
                $rsi,
                $current['macd'] ?? null,
                $current['moving_average'] ?? null,
                $sparkline
            ]);

            return true;
        } catch (\Exception $e) {
            appLog('Archive error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Génère un rapport de performance pour une période donnée
     */
    public function generatePerformanceReport($startDate, $endDate) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    symbol,
                    COUNT(*) as total_analyses,
                    AVG(score) as avg_score,
                    SUM(CASE WHEN conseil = 'ACHAT' THEN 1 ELSE 0 END) as buy_signals,
                    SUM(CASE WHEN conseil = 'VENTE' THEN 1 ELSE 0 END) as sell_signals
                FROM analyses_history
                WHERE created_at BETWEEN ? AND ?
                GROUP BY symbol
                ORDER BY avg_score DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            appLog('Performance report error: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Nettoie les anciennes analyses (plus de 90 jours)
     */
    public function cleanupOldAnalyses($days = 90) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM analyses_history WHERE created_at < datetime('now', ? || ' days')");
            $stmt->execute(["-{$days}"]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            appLog('Cleanup error: ' . $e->getMessage(), 'ERROR');
            return 0;
        }
    }
}
