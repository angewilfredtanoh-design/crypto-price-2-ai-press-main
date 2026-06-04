# 🚀 Crypto 4.0 - Backtesting Automatique

## Vue d'ensemble

Le système de backtesting de Crypto 4.0 permet de tester automatiquement des stratégies de trading sur des données historiques **sans avoir besoin d'une base de données réelle**. 

## ✨ Fonctionnalités

### Génération de Données Synthétiques
- **Mouvement brownien géométrique** pour des prix réalistes
- Paramètres configurables : volatilité, prix de départ, période
- Format OHLCV complet (Open, High, Low, Close, Volume)

### Stratégies de Trading Implémentées
1. **SMA Cross** - Croisement de moyennes mobiles (10/20 périodes)
2. **RSI** - Indice de force relative (seuils 30/70)
3. **MACD** - Convergence/Divergence des moyennes mobiles

### Métriques de Performance
- ROI (Return on Investment)
- Ratio de Sharpe annualisé
- Drawdown maximum
- Taux de réussite des trades
- Courbe de capital complète

## 📁 Structure du Projet

```
Crypto 4.0/
├── src/
│   ├── Core/
│   │   ├── MarketDataGenerator.php    # Générateur de données
│   │   ├── CacheManager.php           # Cache Redis/Fichier
│   │   ├── RateLimiter.php            # Limitation de débit
│   │   ├── Database.php               # Connexion DB
│   │   ├── Logger.php                 # Journalisation
│   │   └── MistralClient.php          # API Mistral AI
│   └── Services/
│       ├── BacktestService.php        # Moteur de backtesting
│       ├── TradingService.php         # Logique de trading
│       └── HistoryService.php         # Historique
├── cli/
│   ├── generate_data.php              # Générer des données factices
│   └── backtest.php                   # Lancer un backtest
├── data/                              # Données générées
├── reports/                           # Rapports de backtest
├── logs/                              # Logs système
└── composer.json                      # Dependencies PHP
```

## 🛠️ Installation

### Prérequis
- PHP 8.2+
- Composer

### Étapes

```bash
cd "Crypto 4.0"

# Installer les dépendances
composer install

# (Optionnel) Initialiser la base de données
php init_db.php
```

## 🎯 Utilisation

### 1. Générer des Données de Marché

```bash
# Générer 365 jours de données BTC
php cli/generate_data.php --symbol=BTCUSDT --days=365 --output=data/btc_365.json

# Avec volatilité personnalisée
php cli/generate_data.php --symbol=ETHUSDT --days=200 --start-price=3000 --volatility=0.05
```

**Options :**
- `--symbol` : Symbole du crypto-actif (défaut: BTCUSDT)
- `--days` : Nombre de jours à générer (défaut: 365)
- `--start-price` : Prix de départ en USD (défaut: 50000)
- `--volatility` : Volatilité quotidienne 0.01-0.10 (défaut: 0.03)
- `--output` : Fichier de sortie JSON (défaut: data/market_data.json)

### 2. Lancer un Backtest

```bash
# Backtest avec génération automatique des données
php cli/backtest.php --strategy=SMA_Cross --days=100 --initial-capital=10000

# Backtest sur données existantes
php cli/backtest.php --data=data/btc_365.json --strategy=RSI

# Tester différentes stratégies
php cli/backtest.php --strategy=MACD --days=365 --initial-capital=5000
```

**Options :**
- `--strategy` : Stratégie à tester (SMA_Cross, RSI, MACD)
- `--data` : Fichier de données JSON (optionnel, généré si absent)
- `--days` : Nombre de jours (si génération auto)
- `--symbol` : Symbole (si génération auto)
- `--initial-capital` : Capital de départ en USD (défaut: 10000)
- `--output` : Fichier de rapport JSON (défaut: reports/backtest_result.json)

## 📊 Exemple de Résultat

```
=== Système de Backtesting Crypto 4.0 ===
Stratégie: SMA_Cross
Capital initial: 10000 $

📊 Performance:
  Capital final: 12,101.22 $
  Profit/Perte: 2,101.22 $
  ROI: 21.01%
  Nombre de trades: 2
  Taux de réussite: 50.00%

📈 Indicateurs de risque:
  Ratio de Sharpe: 2.20
  Drawdown max: 9.28%

💾 Rapport sauvegardé: reports/backtest_result.json
```

## 📄 Format des Rapports

Les rapports sont sauvegardés en JSON avec :
- Statistiques de performance complètes
- Historique détaillé de tous les trades
- Courbe de capital (equity curve) point par point

## 🔧 Personnalisation

### Ajouter une Nouvelle Stratégie

1. Créez une méthode dans `src/Services/BacktestService.php` :

```php
private function maStrategie(array $data, int $index): int
{
    // Votre logique ici
    // Retournez: 1 (achat), -1 (vente), 0 (maintien)
    return 0;
}
```

2. Ajoutez le cas dans `calculateSignal()` :

```php
case 'MA_STRATEGIE':
    return $this->maStrategie($data, $index);
```

3. Testez avec :
```bash
php cli/backtest.php --strategy=MA_STRATEGIE --days=200
```

## ⚠️ Avertissements

- Les données générées sont **synthétiques** et ne reflètent pas le marché réel
- Ce système est destiné à l'**apprentissage** et au **test d'algorithmes**
- Ne pas utiliser pour du trading réel sans validation approfondie
- Les performances passées (même simulées) ne préjugent pas des résultats futurs

## 📝 Licence

Projet éducatif Crypto 4.0 - Usage libre pour apprentissage
