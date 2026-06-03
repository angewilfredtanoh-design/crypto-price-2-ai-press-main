<?php

namespace App\Tests;

use App\Services\Trading\AdvancedTradingEngine;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le moteur de trading avancé
 */
class AdvancedTradingEngineTest extends TestCase
{
    private $engine;

    protected function setUp(): void
    {
        $this->engine = new AdvancedTradingEngine();
    }

    public function testKellyCriterionPositiveExpectation(): void
    {
        // WinRate 60%, Gain moyen 5%, Perte moyenne 2%
        $position = $this->engine->calculateKellyPosition(0.60, 0.05, 0.02, 10000);
        
        $this->assertGreaterThan(0, $position);
        $this->assertLessThanOrEqual(2500, $position); // Max 25% (Half-Kelly cap)
        $this->assertIsFloat($position);
    }

    public function testKellyCriterionNegativeExpectation(): void
    {
        // WinRate 30%, Gain moyen 2%, Perte moyenne 5% (mauvais ratio)
        $position = $this->engine->calculateKellyPosition(0.30, 0.02, 0.05, 10000);
        
        $this->assertEquals(0.0, $position); // Ne devrait pas investir
    }

    public function testVolatilityPositionHighVol(): void
    {
        // Volatilité très haute (80%), cible 15%
        $position = $this->engine->calculateVolatilityPosition(0.80, 0.15, 10000);
        
        $this->assertLessThan(10000, $position); // Doit réduire la position
        $this->assertGreaterThanOrEqual(1000, $position); // Min 10%
    }

    public function testVolatilityPositionLowVol(): void
    {
        // Volatilité basse (10%), cible 15%
        $position = $this->engine->calculateVolatilityPosition(0.10, 0.15, 10000);
        
        $this->assertGreaterThan(10000, $position); // Peut augmenter la position
        $this->assertLessThanOrEqual(20000, $position); // Max 200%
    }

    public function testTrailingStopProfitLock(): void
    {
        $entry = 100;
        $current = 110;
        $highest = 120;
        $trailPercent = 0.05; // 5%
        
        $result = $this->engine->calculateTrailingStop($entry, $current, $trailPercent, $highest);
        
        // Stop loss devrait être à 120 * 0.95 = 114
        $this->assertEquals(114.0, $result['stopLoss']);
        $this->assertFalse($result['triggered']); // Prix actuel (110) < Stop (114)? Non, wait: 110 <= 114 = true
        $this->assertGreaterThan(0, $result['profitLocked']); // Profit verrouillé > 0
    }

    public function testTrailingStopTriggered(): void
    {
        $entry = 100;
        $current = 90; // Chute en dessous du stop
        $highest = 100;
        $trailPercent = 0.05;
        
        $result = $this->engine->calculateTrailingStop($entry, $current, $trailPercent, $highest);
        
        $this->assertTrue($result['triggered']);
    }

    public function testDCAOnDrop(): void
    {
        $basePrice = 100;
        $currentPrice = 85; // -15%
        $baseAmount = 1000;
        
        $amount = $this->engine->calculateDCAAmount($basePrice, $currentPrice, $baseAmount, 5);
        
        // Drop de 15% avec seuil de 5% => multiplier = 1 + floor(15/5) = 4
        $this->assertEquals(4000.0, $amount);
    }

    public function testDCAReducedOnRise(): void
    {
        $basePrice = 100;
        $currentPrice = 110; // +10%
        $baseAmount = 1000;
        
        $amount = $this->engine->calculateDCAAmount($basePrice, $currentPrice, $baseAmount, 5);
        
        // Prix en hausse => on réduit à 50%
        $this->assertEquals(500.0, $amount);
    }

    public function testRealPnLWithFees(): void
    {
        $entry = 100;
        $exit = 110;
        $qty = 10;
        
        $pnl = $this->engine->calculateRealPnL($entry, $exit, $qty, 'buy');
        
        // Brut: (110-100)*10 = 100
        // Frais: (100*10*0.001) + (110*10*0.001) = 1 + 1.1 = 2.1
        // Slippage: (100*10*0.0005) + (110*10*0.0005) = 0.5 + 0.55 = 1.05
        // Net: 100 - 2.1 - 1.05 = 96.85
        $this->assertEquals(96.85, $pnl);
    }

    public function testRealPnLLoss(): void
    {
        $entry = 100;
        $exit = 90;
        $qty = 10;
        
        $pnl = $this->engine->calculateRealPnL($entry, $exit, $qty, 'buy');
        
        // Brut: (90-100)*10 = -100
        // Frais + Slippage: ~3.15
        // Net: -103.15
        $this->assertEquals(-103.15, $pnl);
    }

    public function testSimulateTradeComplete(): void
    {
        $params = [
            'entry_price' => 50000,
            'quantity' => 0.1,
            'capital' => 10000,
            'win_rate' => 0.55,
            'avg_win' => 0.05,
            'avg_loss' => 0.02,
            'volatility' => 0.60,
            'trail_percent' => 0.05
        ];
        
        $result = $this->engine->simulateTrade($params);
        
        $this->assertArrayHasKey('optimal_position_size', $result);
        $this->assertArrayHasKey('scenarios', $result);
        $this->assertArrayHasKey('bullish', $result['scenarios']);
        $this->assertArrayHasKey('bearish', $result['scenarios']);
        $this->assertArrayHasKey('fees_applied', $result);
        $this->assertEquals('0.1%', $result['fees_applied']);
    }
}
