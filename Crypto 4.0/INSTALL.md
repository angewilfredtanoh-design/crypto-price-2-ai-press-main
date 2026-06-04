# Installation - Crypto 4.0

## Prérequis

- PHP 7.4 ou supérieur
- SQLite3 (ou MySQL/MariaDB)
- Un serveur web (Apache, Nginx) ou PHP CLI

## Étapes d'installation

### 1. Initialisation de la base de données

Exécutez le script d'initialisation pour créer la base de données :

```bash
php init_db.php
```

Ou via un navigateur :
```
http://votre-domaine.com/init_db.php
```

### 2. Configuration

Éditez le fichier `config.php` pour configurer :

- Vos clés API (Mistral, etc.)
- Les paramètres de la base de données
- Les réglages du moteur de trading
- Les paramètres de l'IA

### 3. Lancement du projet

#### Option A : Serveur web
Placez les fichiers dans votre dossier web et accédez à :
```
http://votre-domaine.com/index.php
```

#### Option B : Serveur PHP intégré
```bash
php -S localhost:8000
```
Puis accédez à : `http://localhost:8000`

### 4. Tâches cron (optionnel)

Pour automatiser les analyses et le trading :

```bash
# Analyse IA périodique
php update_analyses.php

# Mise à jour des données
php update.php

# Génération de contenu
php generate_global_press.php
```

## Structure du projet

```
Crypto 4.0/
├── index.php              # Page principale
├── config.php             # Configuration
├── init_db.php            # Initialisation DB
├── trading_engine.php     # Moteur de trading
├── portfolio.php          # Gestion du portfolio
├── virtual_portfolio.php  # Portfolio virtuel
├── historique.php         # Historique des trades
├── stats.php              # Statistiques
├── ai_analysis.php        # Analyse IA
├── ai_blog.php            # Blog IA
├── blog.php               # Blog
├── update.php             # Script de mise à jour
├── update_analyses.php    # Mise à jour des analyses
├── lib/                   # Bibliothèques
│   ├── TradingLogic.php
│   ├── HistoryEngine.php
│   └── MistralClient.php
└── README*.md             # Documentation
```

## Support

Consultez les fichiers `README_TRADING.md` et `README_HISTORY.md` pour plus de détails.
