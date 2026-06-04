<?php

namespace App\Tests;

use App\Services\MarketData\CoinGeckoService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service CoinGecko
 */
class CoinGeckoServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        $this->service = new CoinGeckoService();
    }

    public function testGetMarketDataStructure(): void
    {
        // Test avec fallback en cas d'échec API
        $data = $this->service->getMarketData('eur', 10);
        
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
        
        // Vérifier la structure d'un élément
        if (isset($data[0])) {
            $coin = $data[0];
            $this->assertArrayHasKey('id', $coin);
            $this->assertArrayHasKey('symbol', $coin);
            $this->assertArrayHasKey('current_price', $coin);
        }
    }

    public function testSearchCoin(): void
    {
        $results = $this->service->searchCoin('bitcoin');
        
        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));
        
        // Le premier résultat devrait être Bitcoin
        $firstResult = $results[0];
        $this->assertEquals('bitcoin', $firstResult['id']);
    }

    public function testHistoricalOHLCVStructure(): void
    {
        // Test avec fallback si API échoue
        $ohlcv = $this->service->getHistoricalOHLCV('bitcoin', 'eur', 7);
        
        $this->assertIsArray($ohlcv);
        $this->assertGreaterThan(0, count($ohlcv));
        
        // Chaque élément devrait être [timestamp, open, high, low, close]
        $firstCandle = $ohlcv[0];
        $this->assertCount(5, $firstCandle);
        $this->assertIsNumeric($firstCandle[0]); // timestamp
        $this->assertIsNumeric($firstCandle[1]); // open
    }

    public function testCacheFunctionality(): void
    {
        // Premier appel (peut être lent ou API)
        $start1 = microtime(true);
        $data1 = $this->service->getMarketData('eur', 5);
        $time1 = microtime(true) - $start1;
        
        // Deuxième appel (devrait être instantané depuis le cache)
        $start2 = microtime(true);
        $data2 = $this->service->getMarketData('eur', 5);
        $time2 = microtime(true) - $start2;
        
        // Les données devraient être identiques
        $this->assertEquals($data1, $data2);
        
        // Le deuxième appel devrait être beaucoup plus rapide
        // (Note: ce test peut échouer si le cache est désactivé ou Redis lent)
        $this->assertLessThan($time1, $time2);
    }
}
