<?php
/**
 * src/Models/Analysis.php
 * Modèle de données pour une analyse crypto
 */

namespace Crypto\Models;

class Analysis {
    private ?int $id;
    private int $coinId;
    private string $symbol;
    private int $score;
    private string $conseil;
    private ?string $summary;
    private ?string $technicalAnalysis;
    private ?string $fundamentalAnalysis;
    private ?float $rsi;
    private ?float $macd;
    private ?float $movingAverage;
    private \DateTime $generatedAt;
    
    public function __construct(
        int $coinId,
        string $symbol,
        int $score,
        string $conseil,
        ?string $summary = null,
        ?\DateTime $generatedAt = null
    ) {
        $this->id = null;
        $this->coinId = $coinId;
        $this->symbol = $symbol;
        $this->score = $score;
        $this->conseil = $conseil;
        $this->summary = $summary;
        $this->generatedAt = $generatedAt ?? new \DateTime();
    }
    
    public function setId(int $id): void {
        $this->id = $id;
    }
    
    public function getId(): ?int {
        return $this->id;
    }
    
    public function getCoinId(): int {
        return $this->coinId;
    }
    
    public function getSymbol(): string {
        return $this->symbol;
    }
    
    public function getScore(): int {
        return $this->score;
    }
    
    public function setScore(int $score): void {
        $this->score = $score;
        $this->updateConseil();
    }
    
    public function getConseil(): string {
        return $this->conseil;
    }
    
    private function updateConseil(): void {
        if ($this->score >= 65) {
            $this->conseil = 'ACHAT';
        } elseif ($this->score <= 35) {
            $this->conseil = 'VENTE';
        } else {
            $this->conseil = 'ATTENTE';
        }
    }
    
    public function getSummary(): ?string {
        return $this->summary;
    }
    
    public function setSummary(?string $summary): void {
        $this->summary = $summary;
    }
    
    public function getTechnicalAnalysis(): ?string {
        return $this->technicalAnalysis;
    }
    
    public function setTechnicalAnalysis(?string $analysis): void {
        $this->technicalAnalysis = $analysis;
    }
    
    public function getFundamentalAnalysis(): ?string {
        return $this->fundamentalAnalysis;
    }
    
    public function setFundamentalAnalysis(?string $analysis): void {
        $this->fundamentalAnalysis = $analysis;
    }
    
    public function getRsi(): ?float {
        return $this->rsi;
    }
    
    public function setRsi(?float $rsi): void {
        $this->rsi = $rsi;
    }
    
    public function getMacd(): ?float {
        return $this->macd;
    }
    
    public function setMacd(?float $macd): void {
        $this->macd = $macd;
    }
    
    public function getMovingAverage(): ?float {
        return $this->movingAverage;
    }
    
    public function setMovingAverage(?float $ma): void {
        $this->movingAverage = $ma;
    }
    
    public function getGeneratedAt(): \DateTime {
        return $this->generatedAt;
    }
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'coin_id' => $this->coinId,
            'symbol' => $this->symbol,
            'score' => $this->score,
            'conseil' => $this->conseil,
            'summary' => $this->summary,
            'technical_analysis' => $this->technicalAnalysis,
            'fundamental_analysis' => $this->fundamentalAnalysis,
            'rsi' => $this->rsi,
            'macd' => $this->macd,
            'moving_average' => $this->movingAverage,
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s')
        ];
    }
}
