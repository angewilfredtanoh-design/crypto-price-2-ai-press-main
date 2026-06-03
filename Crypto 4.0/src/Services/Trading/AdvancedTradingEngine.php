<?php

namespace App\Services\Trading;

use App\Core\Logger;

/**
 * Moteur de trading avancé avec Trailing Stop, DCA intelligent et Position Sizing (Kelly Criterion)
 */
class AdvancedTradingEngine
{
    private $tradingFees = 0.001; // 0.1% par défaut (Binance/Kraken standard)
    private $slippage = 0.0005;   // 0.05% de slippage estimé
    
    /**
     * Calcule la taille de position optimale selon le Kelly Criterion
     * @param float $winRate Taux de victoire (0.0 à 1.0)
     * @param float $avgWin Gain moyen (ex: 0.05 pour 5%)
     * @param float $avgLoss Perte moyenne (ex: 0.02 pour 2%)
     * @param float $capital Capital total disponible
     * @return float Pourcentage du capital à investir
     */
    public function calculateKellyPosition(float $winRate, float $avgWin, float $avgLoss, float $capital): float
    {
        if ($avgLoss == 0) return 0;
        
        // Formule de Kelly: f* = (p * b - q) / b
        // où p = probabilité de gain, q = probabilité de perte, b = ratio gain/perte
        $b = $avgWin / $avgLoss;
        $p = $winRate;
        $q = 1 - $winRate;
        
        $kellyFraction = ($p * $b - $q) / $b;
        
        // On utilise souvent "Half-Kelly" pour réduire la volatilité
        $kellyFraction = max(0, min($kellyFraction / 2, 0.25)); // Max 25% du capital
        
        $positionSize = $capital * $kellyFraction;
        
        Logger::info(sprintf(
            "Kelly Criterion: WinRate=%.2f, AvgWin=%.4f, AvgLoss=%.4f => Position=%.2f%% (%.2f€)",
            $winRate, $avgWin, $avgLoss, $kellyFraction * 100, $positionSize
        ));
        
        return $positionSize;
    }

    /**
     * Calcule la taille de position basée sur la volatilité (Volatility Targeting)
     * @param float $volatility Volatilité annuelle (ex: 0.60 pour 60%)
     * @param float $targetVol Volatilité cible (ex: 0.15 pour 15%)
     * @param float $capital Capital disponible
     * @return float Position size ajustée
     */
    public function calculateVolatilityPosition(float $volatility, float $targetVol, float $capital): float
    {
        if ($volatility == 0) return $capital;
        
        $adjustmentFactor = $targetVol / $volatility;
        $adjustmentFactor = max(0.1, min($adjustmentFactor, 2.0)); // Entre 10% et 200%
        
        $positionSize = $capital * $adjustmentFactor;
        
        Logger::info(sprintf(
            "Volatility Targeting: Vol=%.2f, Target=%.2f => Factor=%.2f, Position=%.2f€",
            $volatility, $targetVol, $adjustmentFactor, $positionSize
        ));
        
        return $positionSize;
    }

    /**
     * Implémente un Trailing Stop dynamique
     * @param float $entryPrice Prix d'entrée
     * @param float $currentPrice Prix actuel
     * @param float $trailPercent Pourcentage de trailing (ex: 0.05 pour 5%)
     * @param float $highestPrice Plus haut prix atteint depuis l'entrée
     * @return array ['stopLoss' => float, 'triggered' => bool]
     */
    public function calculateTrailingStop(float $entryPrice, float $currentPrice, float $trailPercent, float $highestPrice): array
    {
        // Le stop loss trail le prix au plus haut moins le pourcentage
        $dynamicStop = $highestPrice * (1 - $trailPercent);
        
        // Le stop ne peut jamais être en dessous du prix d'entrée (pour sécuriser les gains)
        $stopLoss = max($dynamicStop, $entryPrice * (1 - $trailPercent * 2));
        
        $triggered = $currentPrice <= $stopLoss;
        
        return [
            'stopLoss' => round($stopLoss, 2),
            'triggered' => $triggered,
            'profitLocked' => $stopLoss > $entryPrice ? (($stopLoss - $entryPrice) / $entryPrice) * 100 : 0
        ];
    }

    /**
     * Stratégie DCA (Dollar Cost Averaging) intelligente
     * Achète plus quand le prix baisse significativement
     * @param float $basePrice Prix de référence (premier achat ou moyenne)
     * @param float $currentPrice Prix actuel
     * @param float $baseAmount Montant de base prévu pour chaque achat
     * @param int $dropThreshold Seuil de baisse pour augmenter l'achat (ex: 5%)
     * @return float Montant à investir maintenant
     */
    public function calculateDCAAmount(float $basePrice, float $currentPrice, float $baseAmount, int $dropThreshold = 5): float
    {
        $dropPercent = (($basePrice - $currentPrice) / $basePrice) * 100;
        
        if ($dropPercent <= 0) {
            // Prix en hausse ou stable : on achète le montant de base ou rien
            return $baseAmount * 0.5; // On réduit si le prix monte
        }
        
        // Multiplicateur basé sur la profondeur de la chute
        $multiplier = 1 + floor($dropPercent / $dropThreshold);
        $multiplier = min($multiplier, 5); // Max 5x le montant de base
        
        $dcaAmount = $baseAmount * $multiplier;
        
        Logger::info(sprintf(
            "Smart DCA: Drop=%.2f%%, Multiplier=%dx => Amount=%.2f€",
            $dropPercent, $multiplier, $dcaAmount
        ));
        
        return $dcaAmount;
    }

    /**
     * Calcule le P&L réel incluant frais et slippage
     */
    public function calculateRealPnL(float $entryPrice, float $exitPrice, float $quantity, string $side): float
    {
        $grossProfit = ($side === 'buy') 
            ? ($exitPrice - $entryPrice) * $quantity
            : ($entryPrice - $exitPrice) * $quantity;
        
        // Frais d'entrée + de sortie
        $totalFees = ($entryPrice * $quantity * $this->tradingFees) 
                   + ($exitPrice * $quantity * $this->tradingFees);
        
        // Slippage estimé
        $slippageCost = ($entryPrice * $quantity * $this->slippage)
                      + ($exitPrice * $quantity * $this->slippage);
        
        $netProfit = $grossProfit - $totalFees - $slippageCost;
        
        return round($netProfit, 2);
    }

    /**
     * Simule un trade complet avec toutes les fonctionnalités avancées
     */
    public function simulateTrade(array $params): array
    {
        $entryPrice = $params['entry_price'];
        $quantity = $params['quantity'];
        $capital = $params['capital'];
        $winRate = $params['win_rate'] ?? 0.55;
        $avgWin = $params['avg_win'] ?? 0.05;
        $avgLoss = $params['avg_loss'] ?? 0.02;
        $volatility = $params['volatility'] ?? 0.60;
        $trailPercent = $params['trail_percent'] ?? 0.05;
        
        // 1. Calcul de la position optimale (Kelly vs Volatility)
        $kellySize = $this->calculateKellyPosition($winRate, $avgWin, $avgLoss, $capital);
        $volSize = $this->calculateVolatilityPosition($volatility, 0.15, $capital);
        
        // On prend le minimum des deux pour être conservateur
        $optimalSize = min($kellySize, $volSize);
        $actualQuantity = $optimalSize / $entryPrice;
        
        // 2. Simulation du scénario
        $scenarios = [];
        
        // Scénario haussier
        $bullExit = $entryPrice * 1.10; // +10%
        $bullHighest = $entryPrice * 1.15; // +15% avant recul
        $bullTrail = $this->calculateTrailingStop($entryPrice, $bullExit, $trailPercent, $bullHighest);
        $bullExitPrice = $bullTrail['triggered'] ? $bullTrail['stopLoss'] : $bullExit;
        $bullPnL = $this->calculateRealPnL($entryPrice, $bullExitPrice, $actualQuantity, 'buy');
        
        // Scénario baissier
        $bearExit = $entryPrice * 0.90; // -10%
        $bearTrail = $this->calculateTrailingStop($entryPrice, $bearExit, $trailPercent, $entryPrice);
        $bearPnL = $this->calculateRealPnL($entryPrice, $bearExit, $actualQuantity, 'buy');
        
        return [
            'optimal_position_size' => round($optimalSize, 2),
            'quantity' => round($actualQuantity, 6),
            'kelly_recommendation' => round($kellySize, 2),
            'volatility_recommendation' => round($volSize, 2),
            'scenarios' => [
                'bullish' => [
                    'exit_price' => round($bullExitPrice, 2),
                    'pnl' => $bullPnL,
                    'roi' => round(($bullPnL / $optimalSize) * 100, 2),
                    'trailing_stop_triggered' => $bullTrail['triggered'],
                    'profit_locked' => round($bullTrail['profitLocked'], 2)
                ],
                'bearish' => [
                    'exit_price' => round($bearExit, 2),
                    'pnl' => $bearPnL,
                    'roi' => round(($bearPnL / $optimalSize) * 100, 2),
                    'stop_loss_hit' => true
                ]
            ],
            'fees_applied' => $this->tradingFees * 100 . '%',
            'slippage_applied' => $this->slippage * 100 . '%'
        ];
    }
}
