<?php

namespace Crypto4\Services;

/**
 * Service de backtesting pour tester les stratégies de trading sur données historiques.
 */
class BacktestService
{
    private float $initialCapital;
    private float $currentCapital;
    private int $position = 0; // 0 = aucun, 1 = long, -1 = short
    private float $entryPrice = 0.0;
    
    private array $trades = [];
    private array $equityCurve = [];
    
    public function __construct(float $initialCapital = 10000.0)
    {
        $this->initialCapital = $initialCapital;
        $this->currentCapital = $initialCapital;
    }
    
    /**
     * Exécute le backtest sur des données historiques avec une stratégie donnée.
     */
    public function run(array $data, string $strategy): array
    {
        $this->reset();
        
        foreach ($data as $index => $candle) {
            // Calcul du signal de trading
            $signal = $this->calculateSignal($data, $index, $strategy);
            
            // Exécution des ordres
            $this->executeTrading($candle, $signal);
            
            // Mise à jour de la courbe de capital
            $equity = $this->calculateEquity($candle['close']);
            $this->equityCurve[] = [
                'date' => $candle['date'],
                'price' => $candle['close'],
                'equity' => $equity
            ];
        }
        
        // Clôture de toute position ouverte à la fin
        if ($this->position !== 0 && !empty($data)) {
            $lastCandle = end($data);
            $this->closePosition($lastCandle['close'], $lastCandle['date']);
        }
        
        return $this->generateReport();
    }
    
    /**
     * Calcule le signal de trading selon la stratégie.
     */
    private function calculateSignal(array $data, int $index, string $strategy): int
    {
        switch ($strategy) {
            case 'SMA_Cross':
                return $this->smaCrossStrategy($data, $index);
            case 'RSI':
                return $this->rsiStrategy($data, $index);
            case 'MACD':
                return $this->macdStrategy($data, $index);
            default:
                return 0;
        }
    }
    
    /**
     * Stratégie de croisement de moyennes mobiles (SMA).
     * Achat quand SMA courte > SMA longue, vente quand l'inverse.
     */
    private function smaCrossStrategy(array $data, int $index): int
    {
        if ($index < 20) return 0; // Pas assez de données
        
        $shortPeriod = 10;
        $longPeriod = 20;
        
        $smaShort = $this->calculateSMA($data, $index, $shortPeriod);
        $smaLong = $this->calculateSMA($data, $index, $longPeriod);
        
        $prevSmaShort = $this->calculateSMA($data, $index - 1, $shortPeriod);
        $prevSmaLong = $this->calculateSMA($data, $index - 1, $longPeriod);
        
        // Croisement haussier (Golden Cross)
        if ($prevSmaShort <= $prevSmaLong && $smaShort > $smaLong) {
            return 1; // Achat
        }
        
        // Croisement baissier (Death Cross)
        if ($prevSmaShort >= $prevSmaLong && $smaShort < $smaLong) {
            return -1; // Vente
        }
        
        return 0; // Maintien
    }
    
    /**
     * Stratégie basée sur le RSI.
     */
    private function rsiStrategy(array $data, int $index): int
    {
        if ($index < 14) return 0;
        
        $rsi = $this->calculateRSI($data, $index, 14);
        
        if ($rsi < 30) return 1; // Survente -> Achat
        if ($rsi > 70) return -1; // Surachat -> Vente
        
        return 0;
    }
    
    /**
     * Stratégie MACD (simplifiée).
     */
    private function macdStrategy(array $data, int $index): int
    {
        if ($index < 26) return 0;
        
        // Implémentation simplifiée
        $ema12 = $this->calculateEMA($data, $index, 12);
        $ema26 = $this->calculateEMA($data, $index, 26);
        $macd = $ema12 - $ema26;
        
        $prevEma12 = $this->calculateEMA($data, $index - 1, 12);
        $prevEma26 = $this->calculateEMA($data, $index - 1, 26);
        $prevMacd = $prevEma12 - $prevEma26;
        
        if ($prevMacd <= 0 && $macd > 0) return 1;
        if ($prevMacd >= 0 && $macd < 0) return -1;
        
        return 0;
    }
    
    /**
     * Exécute les ordres d'achat/vente.
     */
    private function executeTrading(array $candle, int $signal): void
    {
        if ($signal === 1 && $this->position <= 0) {
            // Achat
            if ($this->position === -1) {
                $this->closePosition($candle['close'], $candle['date']);
            }
            $this->openPosition(1, $candle['close'], $candle['date']);
        } elseif ($signal === -1 && $this->position >= 0) {
            // Vente
            if ($this->position === 1) {
                $this->closePosition($candle['close'], $candle['date']);
            }
            $this->openPosition(-1, $candle['close'], $candle['date']);
        }
    }
    
    private function openPosition(int $type, float $price, string $date): void
    {
        $this->position = $type;
        $this->entryPrice = $price;
    }
    
    private function closePosition(float $price, string $date): void
    {
        if ($this->position === 0) return;
        
        $pnlPercent = ($price - $this->entryPrice) / $this->entryPrice;
        if ($this->position === -1) {
            $pnlPercent = -$pnlPercent; // Inverse pour short
        }
        
        $pnlAmount = $this->currentCapital * $pnlPercent;
        $this->currentCapital += $pnlAmount;
        
        $this->trades[] = [
            'entry_date' => $date,
            'exit_date' => $date,
            'entry_price' => $this->entryPrice,
            'exit_price' => $price,
            'type' => $this->position === 1 ? 'LONG' : 'SHORT',
            'pnl' => $pnlAmount,
            'pnl_percent' => $pnlPercent * 100,
            'win' => $pnlAmount > 0
        ];
        
        $this->position = 0;
        $this->entryPrice = 0.0;
    }
    
    private function calculateEquity(float $currentPrice): float
    {
        if ($this->position === 0) {
            return $this->currentCapital;
        }
        
        $unrealizedPnl = ($currentPrice - $this->entryPrice) / $this->entryPrice;
        if ($this->position === -1) {
            $unrealizedPnl = -$unrealizedPnl;
        }
        
        return $this->currentCapital * (1 + $unrealizedPnl);
    }
    
    /**
     * Génère le rapport de performance.
     */
    private function generateReport(): array
    {
        $finalCapital = $this->currentCapital;
        $pnl = $finalCapital - $this->initialCapital;
        $roi = ($pnl / $this->initialCapital) * 100;
        
        $tradesCount = count($this->trades);
        $winningTrades = count(array_filter($this->trades, fn($t) => $t['win']));
        $winRate = $tradesCount > 0 ? ($winningTrades / $tradesCount) * 100 : 0;
        
        // Calcul du drawdown maximum
        $maxDrawdown = $this->calculateMaxDrawdown();
        
        // Calcul du ratio de Sharpe (simplifié)
        $sharpeRatio = $this->calculateSharpeRatio();
        
        return [
            'initial_capital' => $this->initialCapital,
            'final_capital' => $finalCapital,
            'pnl' => $pnl,
            'roi' => $roi,
            'trades_count' => $tradesCount,
            'winning_trades' => $winningTrades,
            'losing_trades' => $tradesCount - $winningTrades,
            'win_rate' => $winRate,
            'max_drawdown' => $maxDrawdown,
            'sharpe_ratio' => $sharpeRatio,
            'equity_curve' => $this->equityCurve,
            'trades' => $this->trades
        ];
    }
    
    private function calculateMaxDrawdown(): float
    {
        if (empty($this->equityCurve)) return 0.0;
        
        $peak = $this->equityCurve[0]['equity'];
        $maxDrawdown = 0.0;
        
        foreach ($this->equityCurve as $point) {
            $equity = $point['equity'];
            if ($equity > $peak) {
                $peak = $equity;
            }
            $drawdown = ($peak - $equity) / $peak * 100;
            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
            }
        }
        
        return $maxDrawdown;
    }
    
    private function calculateSharpeRatio(): float
    {
        if (count($this->equityCurve) < 2) return 0.0;
        
        $returns = [];
        for ($i = 1; $i < count($this->equityCurve); $i++) {
            $prevEquity = $this->equityCurve[$i - 1]['equity'];
            $currEquity = $this->equityCurve[$i]['equity'];
            $returns[] = ($currEquity - $prevEquity) / $prevEquity;
        }
        
        $avgReturn = array_sum($returns) / count($returns);
        $variance = 0.0;
        foreach ($returns as $return) {
            $variance += pow($return - $avgReturn, 2);
        }
        $stdDev = sqrt($variance / count($returns));
        
        if ($stdDev == 0) return 0.0;
        
        // Annualisation (assuming daily data)
        return ($avgReturn / $stdDev) * sqrt(252);
    }
    
    private function reset(): void
    {
        $this->currentCapital = $this->initialCapital;
        $this->position = 0;
        $this->entryPrice = 0.0;
        $this->trades = [];
        $this->equityCurve = [];
    }
    
    // === Helpers Mathématiques ===
    
    private function calculateSMA(array $data, int $index, int $period): float
    {
        $sum = 0.0;
        for ($i = 0; $i < $period; $i++) {
            $sum += $data[$index - $i]['close'];
        }
        return $sum / $period;
    }
    
    private function calculateEMA(array $data, int $index, int $period): float
    {
        $multiplier = 2 / ($period + 1);
        $ema = $data[$index]['close'];
        
        for ($i = 1; $i < $period && ($index - $i) >= 0; $i++) {
            $ema = ($data[$index - $i]['close'] - $ema) * $multiplier + $ema;
        }
        
        return $ema;
    }
    
    private function calculateRSI(array $data, int $index, int $period): float
    {
        $gains = [];
        $losses = [];
        
        for ($i = 1; $i <= $period && ($index - $i) >= 0; $i++) {
            $change = $data[$index - $i + 1]['close'] - $data[$index - $i]['close'];
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }
        
        $avgGain = array_sum($gains) / count($gains);
        $avgLoss = array_sum($losses) / count($losses);
        
        if ($avgLoss == 0) return 100;
        
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }
    
    /**
     * Rendu ASCII de la courbe de capital.
     */
    public static function renderEquityCurve(array $equityCurve, int $width = 60, int $height = 10): string
    {
        if (empty($equityCurve)) return "Aucune donnée";
        
        $equities = array_column($equityCurve, 'equity');
        $minEquity = min($equities);
        $maxEquity = max($equities);
        $range = $maxEquity - $minEquity;
        
        if ($range == 0) $range = 1;
        
        $step = max(1, floor(count($equityCurve) / $width));
        $output = "";
        
        for ($row = $height; $row >= 0; $row--) {
            $line = "";
            $threshold = $minEquity + ($range * $row / $height);
            
            for ($col = 0; $col < $width; $col++) {
                $idx = $col * $step;
                if (!isset($equityCurve[$idx])) {
                    $line .= " ";
                    continue;
                }
                
                $equity = $equityCurve[$idx]['equity'];
                $normalized = ($equity - $minEquity) / $range;
                
                if ($normalized * $height >= $row - 0.5 && $normalized * $height < $row + 0.5) {
                    $line .= "●";
                } elseif ($normalized * $height >= $row) {
                    $line .= " ";
                } else {
                    $line .= "│";
                }
            }
            
            $label = number_format($threshold, 0);
            $output .= sprintf("%8s ┤%s\n", $label, $line);
        }
        
        $output .= str_repeat("─", $width + 10) . "\n";
        $output .= "         " . $equityCurve[0]['date'] . " → " . end($equityCurve)['date'] . "\n";
        
        return $output;
    }
}
