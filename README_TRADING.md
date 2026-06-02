# 🚀 SYSTÈME DE TRADING VIRTUEL - NEO CRYPTO DASH v3.0

## 📋 Vue d'ensemble

Système complet de trading automatique virtuel avec :
- Portefeuille de départ : **1 000 000 €**
- Trading automatique basé sur les analyses IA (score ≥ 65 = ACHAT, score ≤ 35 = VENTE)
- Justification des trades par Mistral AI
- Apprentissage par renforcement (RL) avec audit des trades passés
- Ajustement dynamique des pondérations de critères (RSI, Sparkline, Volume, Market Cap)
- Génération automatique d'articles de blog récapitulatifs

---

## 📁 Fichiers créés

### 1. `init_db.php` (modifié)
**Rôle** : Crée les tables SQL nécessaires
**Nouvelles tables** :
- `virtual_portfolio` : État du portefeuille (capital, performance, statistiques)
- `virtual_positions` : Positions actives (crypto, quantité, prix, P&L)
- `virtual_trades` : Historique des trades avec justifications IA
- `trade_audits` : Audits RL des trades passés (verdict, analyse IA)
- `criteria_weights` : Pondération ajustable des critères (25% chacun au départ)

### 2. `lib/TradingLogic.php` (nouveau - 568 lignes)
**Classe principale** avec méthodes :
- `getPortfolio()` : État du portefeuille
- `updatePortfolioValue()` : Calcule la valeur totale
- `getActivePositions()` : Liste des positions actives
- `openOrUpdatePosition()` : Ouvre/met à jour une position (achat)
- `closePosition()` : Ferme une position (vente)
- `updatePositionPrices()` : Met à jour les prix depuis CoinGecko
- `recordTrade()` : Enregistre un trade en BDD
- `updateTradePnl()` : Met à jour le P&L réalisé
- `getTradeHistory()` : Historique des trades
- `updatePortfolioStats()` : Statistiques (win rate, profit factor, etc.)
- `auditTrade()` : Audit RL d'un trade passé
- `adjustCriteriaWeights()` : Ajuste les poids après un trade perdant
- `getCurrentCriteriaWeights()` : Poids actuels des critères
- `auditAllPendingTrades()` : Audite tous les trades en attente

### 3. `trading_engine.php` (nouveau - 301 lignes)
**Moteur de trading automatique** (à exécuter via cron)
**Fonctionnement** :
1. Met à jour les prix des positions
2. Scanne les analyses IA (score ≥ 65 → ACHAT, score ≤ 35 → VENTE)
3. Vérifie les limites (cash, nombre max de positions)
4. Demande une justification à Mistral pour chaque trade
5. Exécute le trade et met à jour la BDD
6. Audit RL des trades de plus de 24h
7. Génère un article de blog récapitulatif si trades exécutés

### 4. `virtual_portfolio.php` (nouveau - 454 lignes)
**Interface utilisateur complète** avec :
- Dashboard : Valeur totale, performance, cash, statistiques
- Barre visuelle des pondérations de critères (RSI, Trend, Volume, Cap)
- Tableau des positions actives avec P&L en temps réel
- Historique des trades avec justifications IA
- Design responsive moderne (dark theme futuriste)

---

## ⚙️ Configuration

### Étape 1 : Initialiser la base de données
```bash
# La première fois, exécutez init_db.php
php /workspace/init_db.php

# Ou accédez-y via le navigateur
http://votre-site.com/init_db.php
```

### Étape 2 : Configurer le cron (automatisation)
```bash
# Éditez le crontab
crontab -e

# Ajoutez cette ligne pour exécuter le moteur toutes les heures
0 * * * * php /workspace/trading_engine.php >> /workspace/logs/trading.log 2>&1

# Pour tester manuellement
php /workspace/trading_engine.php
```

### Étape 3 : Accéder à l'interface
```
http://votre-site.com/virtual_portfolio.php
```

---

## 🔄 Flux de fonctionnement

### Cycle de trading (exécuté每小时 par cron)

```
┌─────────────────────────────────────────────────────────────┐
│  1. MAJ PRIX POSITIONS                                      │
│     ← Récupère les prix depuis la table 'coins'             │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  2. SCAN ANALYSES IA                                        │
│     ← individual_analysis WHERE score >= 65 (ACHAT)         │
│     ← individual_analysis WHERE score <= 35 (VENTE)         │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  3. VÉRIFICATION LIMITES                                    │
│     ✓ Cash disponible >= 5000€                              │
│     ✓ Nombre positions < 20                                 │
│     ✓ Pas de position existante (pour achat)                │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  4. JUSTIFICATION MISTRAL                                   │
│     Prompt : "Justifie cet achat/vente en 2-3 phrases..."   │
│     Modèle : mistral-small-2603                             │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  5. EXÉCUTION TRADE                                         │
│     → virtual_positions (INSERT/UPDATE)                     │
│     → virtual_trades (INSERT)                               │
│     → virtual_portfolio (MAJ cash)                          │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  6. AUDIT RL (trades > 24h)                                 │
│     ← Compare prix trade vs prix actuel                     │
│     → Verdict : BON / MAUVAIS / NEUTRE                      │
│     → Analyse IA : "Pourquoi ce trade a gagné/perdu"        │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  7. AJUSTEMENT CRITÈRES (si verdict = MAUVAIS)              │
│     ← Identifie critère dominant (RSI, trend, volume, cap)  │
│     → Réduit de 5% le poids du critère                      │
│     → Recalcule pour total = 100%                           │
│     → Validation IA des nouveaux poids                      │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  8. BLOG RÉCAPITULATIF (si trades exécutés)                 │
│     Prompt : "Résume l'activité récente..."                 │
│     Modèle : labs-mistral-small-creative                    │
│     → ai_blog_posts (INSERT)                                │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 Structure des données

### Table `virtual_portfolio`
| Colonne | Type | Description |
|---------|------|-------------|
| id | INTEGER | Clé primaire (toujours 1) |
| initial_capital | REAL | Capital de départ (1 000 000 €) |
| current_cash | REAL | Cash disponible |
| total_value | REAL | Valeur totale (cash + positions) |
| performance_percent | REAL | Performance en % |
| total_trades | INTEGER | Nombre total de trades |
| winning_trades | INTEGER | Trades gagnants |
| losing_trades | INTEGER | Trades perdants |
| win_rate | REAL | Taux de réussite (%) |
| profit_factor | REAL | Ratio gains/pertes |

### Table `virtual_positions`
| Colonne | Type | Description |
|---------|------|-------------|
| coin_id | TEXT | ID CoinGecko (bitcoin, ethereum...) |
| quantity | REAL | Quantité détenue |
| avg_buy_price | REAL | Prix moyen d'achat |
| current_price | REAL | Prix actuel |
| unrealized_pnl | REAL | P&L non réalisé |
| pnl_percent | REAL | P&L en % |

### Table `virtual_trades`
| Colonne | Type | Description |
|---------|------|-------------|
| action | TEXT | BUY ou SELL |
| score_trigger | INTEGER | Score IA qui a déclenché le trade |
| ai_reasoning | TEXT | Analyse technique utilisée |
| ai_justification | TEXT | Justification générée par Mistral |
| realized_pnl | REAL | P&L réalisé (pour les ventes) |

### Table `trade_audits`
| Colonne | Type | Description |
|---------|------|-------------|
| trade_id | INTEGER | Référence au trade |
| delta_percent | REAL | Variation depuis le trade |
| result | TEXT | WIN / LOSS / BREAK_EVEN |
| verdict | TEXT | BON / MAUVAIS / NEUTRE |
| ai_analysis | TEXT | Analyse complète par Mistral |
| ai_recommendations | TEXT | Recommandations pour futurs trades |

### Table `criteria_weights`
| Colonne | Type | Valeur par défaut |
|---------|------|-------------------|
| rsi_weight | REAL | 25 |
| sparkline_weight | REAL | 25 |
| volume_weight | REAL | 25 |
| market_cap_weight | REAL | 25 |
| total_weight | REAL | 100 |

---

## 🎯 Logique d'ajustement RL

### Quand un trade est audité comme "MAUVAIS" :

1. **Identification du critère dominant**
   ```php
   $reasoning = strtolower($trade['ai_reasoning']);
   
   if (strpos($reasoning, 'rsi') !== false) {
       $dominantCriterion = 'rsi';
   } elseif (strpos($reasoning, 'trend') !== false || strpos($reasoning, 'sparkline') !== false) {
       $dominantCriterion = 'sparkline';
   } elseif (strpos($reasoning, 'volume') !== false) {
       $dominantCriterion = 'volume';
   } elseif (strpos($reasoning, 'market cap') !== false) {
       $dominantCriterion = 'market_cap';
   }
   ```

2. **Réduction de 5% du poids**
   ```php
   $newWeight = max(5, $currentWeight - 5);
   ```

3. **Recalibration pour total = 100%**
   ```php
   $factor = 100 / array_sum($newWeights);
   foreach ($newWeights as &$weight) {
       $weight = round($weight * $factor, 2);
   }
   ```

4. **Validation par Mistral**
   - Prompt envoyé avec anciens/nouveaux poids
   - IA valide ou commente les ajustements
   - Enregistrement en BDD avec justification

---

## 🧪 Tests manuels

### Tester le moteur de trading
```bash
php /workspace/trading_engine.php
```

Résultat attendu (JSON) :
```json
{
    "status": "success",
    "timestamp": 1717344000,
    "trades_executed": 3,
    "audits_performed": 5
}
```

### Vérifier les logs
```bash
tail -f /workspace/logs/app.log
tail -f /workspace/logs/trading.log
```

### Requête SQL de débogage
```sql
-- Voir le portefeuille
SELECT * FROM virtual_portfolio;

-- Voir les positions actives
SELECT coin_symbol, quantity, avg_buy_price, current_price, unrealized_pnl 
FROM virtual_positions WHERE is_active = 1;

-- Voir les derniers trades
SELECT action, coin_symbol, price, score_trigger, ai_justification 
FROM virtual_trades ORDER BY timestamp DESC LIMIT 10;

-- Voir les audits
SELECT verdict, delta_percent, ai_analysis 
FROM trade_audits ORDER BY audited_at DESC LIMIT 5;

-- Voir l'historique des poids
SELECT rsi_weight, sparkline_weight, volume_weight, market_cap_weight, adjustment_reason 
FROM criteria_weights ORDER BY id DESC LIMIT 10;
```

---

## 🔧 Personnalisation

### Modifier les seuils de trading
Dans `trading_engine.php` :
```php
$buyThreshold = 65;   // Score minimum pour acheter
$sellThreshold = 35;  // Score maximum pour vendre
```

### Modifier l'investissement par trade
Dans `config.php` :
```php
define('TRADING_CONFIG', [
    'investment_per_trade' => 5000,  // Montant par trade
    'max_positions' => 20,           // Nombre max de positions
    // ...
]);
```

### Fréquence des audits
Dans `trading_engine.php` :
```php
// Actuellement : trades de plus de 24h (86400 secondes)
WHERE timestamp < " . (time() - 86400) . "

// Pour auditer plus souvent (ex: 6h)
WHERE timestamp < " . (time() - 21600) . "
```

---

## 📈 Métriques de performance suivies

- **Win Rate** : % de trades gagnants
- **Profit Factor** : Somme des gains / Somme des pertes
- **Sharpe Ratio** : Rendement ajusté du risque (calculé mensuellement)
- **Max Drawdown** : Perte maximale consécutive
- **Avg Win/Loss** : Gain/perte moyenne par trade
- **Holding Period** : Durée moyenne des positions

---

## ⚠️ Points d'attention

1. **Données CoinGecko requises** : Le système dépend de la table `coins` being up-to-date
2. **Analyses IA requises** : Doit avoir des scores dans `individual_analysis`
3. **Rate limiting Mistral** : Le moteur utilise plusieurs appels API par exécution
4. **Lock file** : Empêche les exécutions simultanées (timeout 1h)
5. **Base SQLite** : Peut devenir lente avec > 100 000 trades (migrer vers MySQL si nécessaire)

---

## 🎉 Prêt à l'emploi !

Le système est maintenant entièrement fonctionnel. Exécutez simplement :

```bash
# 1. Initialiser la BDD
php /workspace/init_db.php

# 2. Lancer le moteur manuellement pour tester
php /workspace/trading_engine.php

# 3. Configurer le cron pour automatisation
crontab -e
# Ajouter: 0 * * * * php /workspace/trading_engine.php >> /workspace/logs/trading.log 2>&1

# 4. Admirer le résultat
http://votre-site.com/virtual_portfolio.php
```

Bonne chance avec votre portefeuille virtuel de 1 million d'euros ! 🚀📈
