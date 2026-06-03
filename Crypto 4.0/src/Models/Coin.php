<?php
/**
 * src/Models/Coin.php
 * Modèle de données pour une crypto-monnaie
 */

namespace Crypto\Models;

class Coin {
    private int $id;
    private string $symbol;
    private string $name;
    private ?float $currentPrice;
    private ?float $marketCap;
    private ?float $volume24h;
    private ?float $priceChange24h;
    
    public function __construct(
        int $id,
        string $symbol,
        string $name,
        ?float $currentPrice = null,
        ?float $marketCap = null,
        ?float $volume24h = null,
        ?float $priceChange24h = null
    ) {
        $this->id = $id;
        $this->symbol = $symbol;
        $this->name = $name;
        $this->currentPrice = $currentPrice;
        $this->marketCap = $marketCap;
        $this->volume24h = $volume24h;
        $this->priceChange24h = $priceChange24h;
    }
    
    public function getId(): int {
        return $this->id;
    }
    
    public function getSymbol(): string {
        return $this->symbol;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getCurrentPrice(): ?float {
        return $this->currentPrice;
    }
    
    public function setCurrentPrice(?float $price): void {
        $this->currentPrice = $price;
    }
    
    public function getMarketCap(): ?float {
        return $this->marketCap;
    }
    
    public function getVolume24h(): ?float {
        return $this->volume24h;
    }
    
    public function getPriceChange24h(): ?float {
        return $this->priceChange24h;
    }
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'name' => $this->name,
            'current_price' => $this->currentPrice,
            'market_cap' => $this->marketCap,
            'volume_24h' => $this->volume24h,
            'price_change_24h' => $this->priceChange24h
        ];
    }
}
