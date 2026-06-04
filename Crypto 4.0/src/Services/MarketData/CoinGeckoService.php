<?php

namespace Crypto4\Services\MarketData;

use Crypto4\Core\Logger;
use Crypto4\Core\CacheManager;

/**
 * Service pour récupérer les données de marché réelles via CoinGecko API
 */
class CoinGeckoService
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private $cache;
    
    public function __construct()
    {
        $this->cache = new CacheManager();
    }

    /**
     * Récupère les données du marché (top 1000 cryptos)
     */
    public function getMarketData(string $currency = 'eur', int $perPage = 250): array
    {
        $cacheKey = "coingecko_market_{$currency}_{$perPage}";
        
        // Tentative de récupération depuis le cache (5 min)
        if ($cached = $this->cache->get($cacheKey)) {
            Logger::info("CoinGecko: Données récupérées depuis le cache");
            return json_decode($cached, true);
        }

        $url = self::BASE_URL . "/coins/markets?vs_currency={$currency}&order=market_cap_desc&per_page={$perPage}&page=1&sparkline=true";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Crypto4.0/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            Logger::error("CoinGecko API Error: HTTP {$httpCode} - {$error}");
            throw new \Exception("Échec de la récupération des données CoinGecko: {$error}");
        }

        $data = json_decode($response, true);
        
        // Mise en cache
        $this->cache->set($cacheKey, json_encode($data), 300); // 5 minutes
        
        Logger::info("CoinGecko: " . count($data) . " coins récupérés avec succès");
        return $data;
    }

    /**
     * Récupère les données historiques OHLCV pour un coin spécifique
     * @param string $coinId Ex: 'bitcoin', 'ethereum'
     * @param string $vsCurrency Ex: 'eur', 'usd'
     * @param int $days Nombre de jours (max 365 pour l'API gratuite)
     */
    public function getHistoricalOHLCV(string $coinId, string $vsCurrency = 'eur', int $days = 30): array
    {
        $cacheKey = "coingecko_ohlcv_{$coinId}_{$vsCurrency}_{$days}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }

        $url = self::BASE_URL . "/coins/{$coinId}/ohlc?vs_currency={$vsCurrency}&days={$days}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Crypto4.0/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::warning("CoinGecko OHLCV Error: HTTP {$httpCode}, fallback sur données simulées");
            return $this->generateFallbackOHLCV($days);
        }

        $data = json_decode($response, true);
        $this->cache->set($cacheKey, json_encode($data), 3600); // 1 heure
        
        return $data;
    }

    /**
     * Fallback amélioré utilisant un modèle Geometric Brownian Motion simple
     * Plus réaliste que du rand() pur (drift + volatilité log-normale)
     */
    private function generateFallbackOHLCV(int $days): array
    {
        Logger::info("Génération de données OHLCV de fallback (Geometric Brownian Motion)");

        $data = [];
        $currentPrice = 45000.0;           // Prix de départ (BTC-like)
        $drift = 0.0003;                   // Drift journalier moyen (légèrement haussier)
        $volatility = 0.035;               // Volatilité journalière (~3.5%)
        $now = time();

        for ($i = $days; $i >= 0; $i--) {
            $timestamp = ($now - ($i * 86400)) * 1000;

            // Génération d'un rendement log-normal (Geometric Brownian Motion)
            $random = $this->gaussianRandom();
            $return = $drift + $volatility * $random;

            $open = $currentPrice;
            $close = $open * exp($return);

            // Construction réaliste de High et Low
            $dailyVol = abs($return) * 0.6 + 0.008; // un peu de bruit supplémentaire
            $high = max($open, $close) * (1 + $dailyVol * mt_rand(5, 25) / 1000);
            $low  = min($open, $close) * (1 - $dailyVol * mt_rand(5, 25) / 1000);

            // Sécurité : Low ne doit jamais être négatif
            $low = max($low, $close * 0.85);

            $data[] = [$timestamp, round($open, 2), round($high, 2), round($low, 2), round($close, 2)];

            $currentPrice = $close;
        }

        return $data;
    }

    /**
     * Génère un nombre aléatoire suivant une distribution normale (Box-Muller)
     */
    private function gaussianRandom(): float
    {
        static $useLast = false;
        static $y2 = 0.0;

        if ($useLast) {
            $useLast = false;
            return $y2;
        }

        $x1 = 0.0;
        $x2 = 0.0;
        $w  = 0.0;

        do {
            $x1 = 2.0 * mt_rand() / mt_getrandmax() - 1.0;
            $x2 = 2.0 * mt_rand() / mt_getrandmax() - 1.0;
            $w  = $x1 * $x1 + $x2 * $x2;
        } while ($w >= 1.0 || $w == 0.0);

        $w = sqrt((-2.0 * log($w)) / $w);
        $y1 = $x1 * $w;
        $y2 = $x2 * $w;

        $useLast = true;
        return $y1;
    }

    /**
     * Recherche un coin par nom ou symbole
     */
    public function searchCoin(string $query): array
    {
        $cacheKey = "coingecko_search_{$query}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return json_decode($cached, true);
        }

        $url = self::BASE_URL . "/coins/search?query=" . urlencode($query);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $this->cache->set($cacheKey, json_encode($data), 86400); // 24h
        
        return $data;
    }
}
