<?php
/**
 * src/Utils/Helpers.php
 * Fonctions utilitaires pour Crypto 4.0
 */

namespace Crypto\Utils;

class Helpers {
    /**
     * Formater un prix avec la devise appropriée
     */
    public static function formatPrice(float $price, string $currency = 'USD', int $decimals = 2): string {
        $symbol = match($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' '
        };
        
        return $symbol . number_format($price, $decimals);
    }
    
    /**
     * Formater un pourcentage avec couleur HTML
     */
    public static function formatPercentage(float $value, bool $withColor = true): string {
        $sign = $value >= 0 ? '+' : '';
        $formatted = $sign . number_format($value, 2) . '%';
        
        if (!$withColor) {
            return $formatted;
        }
        
        $color = $value >= 0 ? 'green' : 'red';
        return "<span style='color: {$color};'>{$formatted}</span>";
    }
    
    /**
     * Calculer le RSI (Relative Strength Index)
     */
    public static function calculateRSI(array $prices, int $period = 14): float {
        if (count($prices) < $period + 1) {
            return 50.0; // Valeur par défaut si pas assez de données
        }
        
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i <= $period; $i++) {
            $change = $prices[count($prices) - $i] - $prices[count($prices) - $i - 1];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }
        
        $avgGain = array_sum($gains) / $period;
        $avgLoss = array_sum($losses) / $period;
        
        if ($avgLoss == 0) {
            return 100.0;
        }
        
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));
        
        return round($rsi, 2);
    }
    
    /**
     * Calculer la moyenne mobile simple
     */
    public static function calculateSMA(array $prices, int $period = 20): float {
        if (count($prices) < $period) {
            return array_sum($prices) / count($prices);
        }
        
        $slice = array_slice($prices, -$period, $period);
        return round(array_sum($slice) / $period, 2);
    }
    
    /**
     * Calculer le MACD (Moving Average Convergence Divergence)
     */
    public static function calculateMACD(array $prices): array {
        $ema12 = self::calculateEMA($prices, 12);
        $ema26 = self::calculateEMA($prices, 26);
        
        $macdLine = $ema12 - $ema26;
        $signalLine = self::calculateEMA([$macdLine], 9);
        $histogram = $macdLine - $signalLine;
        
        return [
            'macd' => round($macdLine, 4),
            'signal' => round($signalLine, 4),
            'histogram' => round($histogram, 4)
        ];
    }
    
    /**
     * Calculer une moyenne mobile exponentielle (EMA)
     */
    private static function calculateEMA(array $prices, int $period): float {
        if (count($prices) < $period) {
            return array_sum($prices) / count($prices);
        }
        
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($prices, 0, $period)) / $period;
        
        for ($i = $period; $i < count($prices); $i++) {
            $ema = ($prices[$i] - $ema) * $multiplier + $ema;
        }
        
        return $ema;
    }
    
    /**
     * Nettoyer et valider une entrée utilisateur
     */
    public static function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Générer un token CSRF sécurisé
     */
    public static function generateCSRFToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Vérifier un token CSRF
     */
    public static function verifyCSRFToken(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
