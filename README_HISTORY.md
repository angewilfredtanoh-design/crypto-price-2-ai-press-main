# 📜 Système d'Historique et d'Agenda des Analyses - NEO CRYPTO DASH

## Vue d'ensemble

Ce système complet permet de :
- **Archiver** toutes les analyses générées par l'IA
- **Auditer** rétrospectivement la pertinence des conseils (ACHAT/VENTE/ATTENTE)
- **Calculer** la fiabilité du bot par crypto et globalement
- **Visualiser** l'évolution des scores et des prix dans le temps
- **Générer** des revues de marché périodiques
- **Filtrer** l'historique par crypto, conseil et statut d'audit

---

## 📁 Fichiers Créés

| Fichier | Rôle | Lignes |
|---------|------|--------|
| `lib/HistoryEngine.php` | Moteur métier (archivage, audit, stats) | 299 |
| `historique.php` | Interface utilisateur complète | 852 |
| `cron_history.php` | Script d'automatisation Cron | 63 |
| `init_db.php` | Tables SQL ajoutées (analyses_history, market_reviews) | +40 |

---

## 🗄️ Structure de la Base de Données

### Table `analyses_history`
Archive chaque analyse générée avec son résultat ultérieur.

```sql
CREATE TABLE analyses_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    crypto_id TEXT NOT NULL,          -- Ex: 'bitcoin'
    symbol TEXT NOT NULL,             -- Ex: 'BTC'
    score INTEGER NOT NULL,           -- Score IA 0-100
    conseil TEXT NOT NULL,            -- 'ACHAT', 'VENTE', 'ATTENTE'
    analyse_text TEXT NOT NULL,       -- Résumé de l'analyse IA
    sparkline_snapshot TEXT,          -- JSON des prix 7j au moment T
    rsi_snapshot REAL,                -- RSI au moment de l'analyse
    volume_snapshot REAL,             -- Volume 24h au moment T
    price_at_analysis REAL,           -- Prix d'entrée théorique
    created_at DATETIME,              -- Date de l'analyse
    was_correct INTEGER,              -- NULL (en attente), 1 (vrai), 0 (faux)
    price_at_audit REAL,              -- Prix lors de l'audit
    audit_date DATETIME               -- Date de l'audit
);
```

### Table `market_reviews`
Revues de marché périodiques générées automatiquement.

```sql
CREATE TABLE market_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    review_text TEXT NOT NULL,        -- Analyse globale du marché
    global_advice TEXT,               -- Conseil général
    top_picks TEXT,                   -- JSON ['BTC', 'ETH', ...]
    created_at DATETIME,              -- Date de génération
    period_start DATETIME,            -- Début de période analysée
    period_end DATETIME               -- Fin de période analysée
);
```

---

## ⚙️ Fonctionnement du Système

### 1. Archivage Automatique
Le script `cron_history.php` (à exécuter toutes les 6 heures) :
- Parcourt toutes les cryptos dans `individual_analysis`
- Copie les analyses récentes vers `analyses_history`
- Évite les doublons (fenêtre de 5 minutes)

```php
$engine->archiveAnalysis($cryptoId, $symbol);
```

### 2. Audit Rétrospectif
Après 24h, le système évalue si le conseil était pertinent :

| Conseil | Condition de SUCCÈS | Condition d'ÉCHEC |
|---------|---------------------|-------------------|
| **ACHAT** | Prix actuel > Prix d'analyse | Prix actuel ≤ Prix d'analyse |
| **VENTE** | Prix actuel < Prix d'analyse | Prix actuel ≥ Prix d'analyse |
| **ATTENTE** | Non évalué (neutre) | Non évalué (neutre) |

```php
$engine->runAudit(); // Met à jour was_correct = 1 ou 0
```

### 3. Calcul de Fiabilité
Pour chaque crypto, le système calcule :
```
Fiabilité (%) = (Nombre de conseils corrects / Total audits) × 100
```

Classification visuelle :
- 🟢 **≥ 60%** : Bonne fiabilité
- 🟡 **40-59%** : Fiabilité moyenne
- 🔴 **< 40%** : Faible fiabilité

### 4. Revues de Marché Hebdomadaires
Tous les lundis à minuit, une revue est générée automatiquement :
- Analyse des top picks actuels (score ≥ 70)
- Conseil global adapté au sentiment du marché
- Stockée dans `market_reviews`

---

## 🖥️ Interface Utilisateur (`historique.php`)

### Dashboard KPIs
- **Fiabilité Globale** : Pourcentage de bons conseils tous actifs confondus
- **Total Analyses** : Nombre d'analyses archivées depuis le lancement
- **Dernière Revue** : Date et top picks de la dernière revue de marché

### Graphique Chart.js
Visualisation double axe :
- **Axe Y gauche** : Score IA (0-100) - courbe bleue
- **Axe Y droit** : Prix en € - courbe jaune
- **Axe X** : Timeline des 30 dernières analyses

Sélectionnable par crypto via menu déroulant.

### Tableau de Fiabilité
Classement des cryptos par taux de réussite :
| Crypto | Total Audits | Succès | Échecs | Taux de Réussite |
|--------|--------------|--------|--------|------------------|
| BTC | 45 | 32 | 13 | 71.11% 🟢 |
| ETH | 38 | 20 | 18 | 52.63% 🟡 |

### Historique Détaillé (Timeline)
Tableau filtrable avec colonnes :
- Date et heure
- Crypto (symbole)
- Score IA (code couleur : vert ≥70, jaune ≥50, rouge <50)
- Conseil (badge coloré : ACHAT/VENTE/ATTENTE)
- Prix d'entrée et prix d'audit
- Résultat (badge : SUCCÈS/ÉCHEC/En attente)
- Texte de l'analyse IA (survol pour lire en entier)

**Filtres disponibles :**
- Par crypto (toutes ou sélection)
- Par conseil (ACHAT/VENTE/ATTENTE)
- Par statut (Validé/Erreur/En attente)

### Section Agenda & Revues
Affichage chronologique des revues de marché avec :
- Date de publication
- Période analysée
- Texte complet de l'analyse
- Conseil global mis en évidence
- Top Picks de la période

---

## 🕒 Configuration du Cron

### Commande à ajouter
Ouvrez votre crontab :
```bash
crontab -e
```

Ajoutez cette ligne (exécution toutes les 6 heures) :
```cron
0 */6 * * * php /workspace/cron_history.php >> /workspace/logs/history_cron.log 2>&1
```

### Planning recommandé
| Fréquence | Tâche | Impact |
|-----------|-------|--------|
| **Toutes les 6h** | Archivage + Audit | Données à jour |
| **Hebdo (lundi 0h)** | Revue de marché | Rapport périodique |

### Logs
Les exécutions sont journalisées dans :
```
/workspace/logs/history_cron.log
```

Exemple de sortie :
```
[2025-06-02 18:00:01] === Démarrage tâche historique ===
[2025-06-02 18:00:01] Archivage des analyses en cours...
[2025-06-02 18:00:02]   -> Archivé: BTC (bitcoin)
[2025-06-02 18:00:02]   -> Archivé: ETH (ethereum)
[2025-06-02 18:00:02] 2 nouvelles analyses archivées.
[2025-06-02 18:00:02] Lancement de l'audit de performance...
[2025-06-02 18:00:03] Audit ID 142 (BTC): Conseil ACHAT -> SUCCÈS
[2025-06-02 18:00:03] Audit terminé: 15 analyses évaluées.
[2025-06-02 18:00:03] === Tâche historique terminée avec succès ===
```

---

## 📊 Utilisation de l'API HistoryEngine

### Initialisation
```php
require_once 'config.php';
require_once 'lib/HistoryEngine.php';

$engine = new HistoryEngine();
```

### Méthodes Disponibles

#### `archiveAnalysis($cryptoId, $symbol)`
Archive une analyse récente.
```php
$success = $engine->archiveAnalysis('bitcoin', 'BTC');
```

#### `runAudit()`
Lance l'audit rétrospectif des analyses de plus de 24h.
```php
$count = $engine->runAudit();
echo "$count analyses auditées.";
```

#### `getReliabilityStats($limit = 20)`
Récupère les stats de fiabilité par crypto.
```php
$stats = $engine->getReliabilityStats(10);
foreach ($stats as $stat) {
    echo "{$stat['symbol']}: {$stat['accuracy_percent']}%\n";
}
```

#### `getFullHistory($filters)`
Récupère l'historique avec filtres optionnels.
```php
$history = $engine->getFullHistory([
    'crypto_id' => 'bitcoin',
    'conseil' => 'ACHAT',
    'was_correct' => 'validé' // ou 'erreur', 'en_attente', 'all'
]);
```

#### `getChartData($cryptoId, $limit = 30)`
Données pour le graphique d'évolution.
```php
$data = $engine->getChartData('ethereum', 50);
// Retourne: [{created_at, score, price}, ...]
```

#### `getUniqueCryptos()`
Liste toutes les cryptos dans l'historique.
```php
$cryptos = $engine->getUniqueCryptos();
// Retourne: [{crypto_id, symbol}, ...]
```

#### `getReviews($limit = 5)`
Dernières revues de marché.
```php
$reviews = $engine->getReviews(3);
```

#### `getGlobalStats()`
Statistiques globales pour le dashboard.
```php
$stats = $engine->getGlobalStats();
echo "Total: {$stats['total']}, Fiabilité: {$stats['accuracy']}%";
```

#### `generateMarketReview()`
Génère une nouvelle revue de marché.
```php
$engine->generateMarketReview();
```

---

## 🎯 Interprétation des Résultats

### Scénarios de Performance

#### 🟢 Excellente Performance (≥ 70%)
- Le modèle IA est bien calibré
- Les critères techniques (RSI, volumes, trend) sont pertinents
- **Action** : Continuer avec confiance, éventuellement augmenter les positions

#### 🟡 Performance Moyenne (40-69%)
- Le modèle a besoin d'ajustements
- Certains signaux sont bruités
- **Action** : Analyser les échecs par type de conseil, ajuster les seuils

#### 🔴 Faible Performance (< 40%)
- Le modèle est mal calibré ou le marché a changé
- Les critères actuels ne sont pas adaptés
- **Action** : Réviser les pondérations, intégrer de nouveaux indicateurs

### Analyse par Type de Conseil

Comparez la fiabilité selon le conseil :
- **ACHAT** : Fiabilité haussière
- **VENTE** : Fiabilité baissière
- **ATTENTE** : (Non évalué directement)

Si `Fiabilité ACHAT` >> `Fiabilité VENTE` :
→ Le modèle est meilleur pour détecter les opportunités haussières

---

## 🔧 Dépannage

### Problème : Aucune donnée dans l'historique
**Cause** : Le cron n'a pas été exécuté ou `individual_analysis` est vide.

**Solution** :
1. Vérifiez que des analyses existent : `SELECT COUNT(*) FROM individual_analysis;`
2. Exécutez manuellement le cron : `php /workspace/cron_history.php`
3. Attendez 24h pour voir les premiers audits

### Problème : Graphique vide pour une crypto
**Cause** : Pas assez de données historiques pour cette crypto.

**Solution** :
- Sélectionnez une autre crypto avec plus d'historique
- Attendez que le cron archive plus de données

### Problème : Fiabilité à 0% ou NULL
**Cause** : Aucun audit n'a encore été réalisé (données trop récentes).

**Solution** :
- Les audits ne concernent que les analyses de plus de 24h
- Patientez ou vérifiez la date des premières analyses

### Problème : Erreur de connexion DB
**Cause** : Chemin incorrect vers `DB_FILE` dans `config.php`.

**Solution** :
```php
// Dans config.php, vérifiez :
define('DB_FILE', '/workspace/data/crypto_dashboard.db');
// Assurez-vous que le dossier existe et est accessible en écriture
```

---

## 📈 Améliorations Futures Possibles

1. **Intégration IA avancée** : Envoyer les audits perdants à Mistral pour analyse des erreurs
2. **Backtesting stratégique** : Simuler des stratégies alternatives sur l'historique
3. **Alertes de dérive** : Notifier si la fiabilité chute brutalement
4. **Export CSV/PDF** : Télécharger les rapports de performance
5. **Comparaison de modèles** : Suivre la performance par modèle IA utilisé

---

## 📞 Support

Pour toute question ou problème :
1. Consultez les logs : `/workspace/logs/history_cron.log`
2. Vérifiez la structure DB : `sqlite3 /workspace/data/crypto_dashboard.db ".schema"`
3. Testez le moteur manuellement : `php /workspace/cron_history.php`

---

**Version** : 1.0.0  
**Dernière mise à jour** : Juin 2025  
**Compatibilité** : NEO CRYPTO DASH v3.0+
