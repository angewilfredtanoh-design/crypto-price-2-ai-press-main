<?php

namespace Crypto4\Core;

/**
 * Générateur de données de marché synthétiques pour le backtesting.
 * Génère des prix réalistes basés sur un mouvement brownien géométrique.
 */
class MarketDataGenerator
{
    /**
     * Génère un historique de prix fictif mais réaliste.
     * 
     * @param string $symbol Nom du symbole (ex: BTCUSDT)
     * @param int $days Nombre de jours à générer
     * @param float $startPrice Prix de départ
     * @param float $volatility Volatilité quotidienne (ex: 0.05 pour 5%)
     * @return array Liste de bougies OHLCV
     */
    public static function generateHistory(string $symbol, int $days, float $startPrice = 50000.0, float $volatility = 0.03): array
    {
        $data = [];
        $currentPrice = $startPrice;
        $now = time();
        $interval = 86400; // 1 jour en secondes

        for ($i = $days; $i > 0; $i--) {
            $timestamp = $now - ($i * $interval);
            
            // Mouvement aléatoire (Random Walk)
            $changePercent = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $volatility;
            $open = $currentPrice;
            $close = $open * (1 + $changePercent);
            
            // Calcul High/Low basé sur Open/Close
            $high = max($open, $close) * (1 + (mt_rand() / mt_getrandmax()) * ($volatility / 2));
            $low = min($open, $close) * (1 - (mt_rand() / mt_getrandmax()) * ($volatility / 2));
            
            // Volume aléatoire corrélé à la volatilité
            $volume = (mt_rand(1000000, 50000000) / 100) * (1 + abs($changePercent) * 10);

            $data[] = [
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'symbol' => $symbol,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => round($volume, 2)
            ];

            $currentPrice = $close;
        }

        return $data;
    }
}
