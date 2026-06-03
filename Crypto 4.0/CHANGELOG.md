# CHANGELOG

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versionnage Sémantique](https://semver.org/lang/fr/).

## [4.0.0] - 2025-06-03

### Added
- Architecture OOP complète avec namespace `Crypto4\` suivant PSR-4
- Classes Core : `Database`, `Logger`, `CacheManager`, `RateLimiter`, `MistralClient`
- Classes Models : `Coin`, `Analysis` avec typage fort et getters/setters
- Classes Services : `CoinGeckoService`, `AdvancedTradingEngine`, `RAGService`, `HistoryService`, `BacktestService`
- Service CoinGecko pour données marché réelles (top 1000 cryptos)
- Historique OHLCV via API CoinGecko avec cache intelligent
- Trading Engine avancé avec Kelly Criterion, Volatility Targeting, Trailing Stop Dynamique
- DCA Intelligent (achète plus quand le prix baisse)
- Calcul P&L réel avec frais (0.1%) et slippage (0.05%)
- RAG (Retrieval-Augmented Generation) avec contexte historique
- Cache des réponses Mistral (hash du prompt)
- Multi-modèles en parallèle (mistral-small + mistral-large)
- Tests unitaires PHPUnit pour TradingEngine et CoinGeckoService
- Dashboard Metrics (`public/metrics.php`) pour monitoring
- Configuration Composer complète avec autoload PSR-4
- Scripts Composer pour tests et couverture de code
- Fichiers `.gitignore` améliorés (vendor/, *.db, logs/, .env, etc.)

### Changed
- Refactorisation complète en classes OOP strictes
- Migration vers PSR-4 avec autoloading Composer
- Amélioration de la gestion des erreurs et logging
- Optimisation du cache (5 min pour market data, 1h pour OHLCV)
- Structure de projet réorganisée (src/, tests/, public/, cli/)

### Fixed
- Correction de la rotation des clés API Mistral
- Gestion améliorée des timeouts API
- Fallback automatique sur données simulées si API indisponible

### Deprecated
- Anciennes fonctions procédurales dans `lib/` (migration vers OOP en cours)

### Removed
- Dépendances inutiles
- Code dupliqué

### Security
- Masquage des clés API dans les logs (hash MD5 partiel)
- Blacklist des clés API défaillantes
- Protection contre l'exécution directe des fichiers de configuration

---

## [3.0.0] - 2025-05-28

### Added
- Système d'historique et d'agenda des analyses
- Audit rétrospectif des conseils IA
- Calcul de fiabilité par crypto et globalement
- Visualisation de l'évolution des scores et des prix
- Génération de revues de marché périodiques
- Rotation intelligente des clés API Mistral (20 modèles optimisés)
- Prompts enrichis (800-1200 mots)
- Logging complet (app.log, error.log, api_usage.log)
- Design pro futuriste compatible Hostinger

### Changed
- Compatible Hostinger Mutualisé
- Gestion d'erreurs robuste
- Cache SQLite optimisé

---

## [2.0.0] - 2025-05-15

### Added
- Moteur de trading automatique virtuel
- Gestion des positions (achat/vente)
- Portfolio virtuel avec suivi P&L
- Analyses IA multi-critères (RSI, MACD, Momentum, etc.)
- Scores de confiance 0-100
- Conseils ACHAT/VENTE/ATTENTE

### Changed
- Exécution via cron (toutes les heures)
- Seuils dynamiques configurables

---

## [1.0.0] - 2025-05-01

### Added
- Dashboard crypto temps réel
- Affichage top 100 cryptos
- Prix, market cap, volume 24h
- Sparklines 7 jours
- Recherche de cryptos
- Favoris
- Interface responsive

---

## Notes de Version

### Convention de Versionnage Sémantique

Ce projet suit le versionnage sémantique 2.0.0 :

- **MAJOR** (X.0.0) : Changements incompatibles avec les versions précédentes
- **MINOR** (x.Y.0) : Nouvelles fonctionnalités rétro-compatibles
- **PATCH** (x.y.Z) : Corrections de bugs rétro-compatibles

### Branches Git

- `main` : Version stable actuelle
- `develop` : Branche de développement
- `feature/*` : Nouvelles fonctionnalités
- `bugfix/*` : Corrections de bugs
- `release/*` : Préparation de release

### Tags Git

Les tags sont créés pour chaque version :
```bash
git tag -a v4.0.0 -m "Release version 4.0.0"
git push origin --tags
```
