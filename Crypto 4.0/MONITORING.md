# 📊 PROMETHEUS + GRAFANA MONITORING

## Vue d'ensemble

Crypto 4.0 intègre maintenant un système complet de monitoring avec :
- **Health Checks** automatisés
- **Export de métriques** au format Prometheus
- **Dashboard Grafana** pré-configuré

---

## 🚀 INSTALLATION RAPIDE

### 1. Prérequis

```bash
# Docker & Docker Compose requis
docker --version
docker-compose --version
```

### 2. Lancer la stack de monitoring

```bash
cd "Crypto 4.0"
docker-compose up -d prometheus grafana
```

### 3. Accéder aux interfaces

| Service | URL | Identifiants |
|---------|-----|--------------|
| Grafana | http://localhost:3000 | admin / admin |
| Prometheus | http://localhost:9090 | - |

---

## 📈 MÉTRIQUES DISPONIBLES

### Health Checks
```promql
crypto4_component_health_status{component="database"}     # État de la BDD
crypto4_component_health_status{component="cache"}        # État du cache
crypto4_component_health_status{component="api_coingecko"} # API CoinGecko
crypto4_component_health_status{component="api_mistral"}   # API Mistral AI
crypto4_component_response_time_seconds                   # Temps de réponse
```

### Trading
```promql
crypto4_database_trades_total         # Nombre total de trades
crypto4_database_open_positions       # Positions ouvertes
crypto4_trading_pnl_total_eur         # P&L total en EUR
```

### Système
```promql
crypto4_system_memory_usage_bytes     # Mémoire utilisée
crypto4_system_disk_free_bytes        # Espace disque libre
crypto4_cache_hits_total              # Hits du cache
crypto4_cache_misses_total            # Misses du cache
crypto4_cache_keys_count              # Nombre de clés en cache
```

---

## 🔧 UTILISATION DES SCRIPTS CLI

### Health Check manuel
```bash
php cli/health_check.php
```

Sortie exemple :
```
╔════════════════════════════════════════════════════════╗
║         CRYPTO 4.0 - HEALTH CHECK                      ║
╚════════════════════════════════════════════════════════╝

Exécution des checks...

✅ DATABASE             [HEALTHY] Database connection OK
   └─ Response time: 1.23 ms
✅ CACHE                [HEALTHY] Cache service OK
   └─ Response time: 0.45 ms
✅ API_COINGECKO        [HEALTHY] CoinGecko API OK
   └─ Response time: 234.56 ms
⏭️ API_MISTRAL          [SKIPPED] No API key configured
✅ DISK_SPACE           [HEALTHY] 75.32% free (45.20 GB / 60.00 GB)

╔════════════════════════════════════════════════════════╗
║  STATUT GLOBAL: ✅ HEALTHY                             ║
╚════════════════════════════════════════════════════════╝
```

### Export des métriques Prometheus
```bash
# Afficher les métriques
php cli/metrics_exporter.php stdout

# Sauvegarder dans un fichier
php cli/metrics_exporter.php file /tmp/metrics.prom
```

---

## 📊 CONFIGURATION PROMETHEUS

Le fichier `monitoring/prometheus.yml` est pré-configuré pour scraper :

```yaml
scrape_configs:
  - job_name: 'crypto4_metrics'
    static_configs:
      - targets: ['host.docker.internal:8080']
    
  - job_name: 'crypto4_file'
    file_sd_configs:
      - files:
          - '/metrics/metrics.prom'
    scrape_interval: 15s
```

---

## 📉 CONFIGURATION GRAFANA

### Import du dashboard

1. Connectez-vous à Grafana (http://localhost:3000)
2. Allez dans **Dashboards** → **Import**
3. Uploadez le fichier `monitoring/grafana-dashboard.json`
4. Sélectionnez la datasource **Prometheus**
5. Cliquez sur **Import**

### Panels inclus

- **System Health Overview** : Vue d'ensemble des composants
- **Trading Performance** : P&L, trades, positions
- **Response Times** : Latences des APIs et services
- **Cache Efficiency** : Taux de hit/miss
- **Resource Usage** : Mémoire et disque

---

## 🔄 AUTOMATISATION

### Cron pour exporter les métriques

Ajoutez à votre crontab :

```bash
# Exporter les métriques toutes les 15 secondes
* * * * * php /path/to/Crypto\ 4.0/cli/metrics_exporter.php file /tmp/metrics.prom
```

### Systemd service (Linux)

Créez `/etc/systemd/system/crypto4-metrics.service` :

```ini
[Unit]
Description=Crypto 4.0 Metrics Exporter
After=network.target

[Service]
Type=simple
User=www-data
ExecStart=/usr/bin/php /path/to/Crypto\ 4.0/cli/metrics_exporter.php file /tmp/metrics.prom
Restart=always
RestartSec=15

[Install]
WantedBy=multi-user.target
```

Puis :
```bash
sudo systemctl enable crypto4-metrics
sudo systemctl start crypto4-metrics
```

---

## 🎯 ALERTES RECOMMANDÉES

Configurez ces alertes dans Grafana :

### Alerte Critique - Composant Down
```promql
crypto4_component_health_status == 0
```
→ Notification immédiate (Slack, Email, PagerDuty)

### Alerte Warning - P&L Négatif
```promql
crypto4_trading_pnl_total_eur < -10000
```
→ Perte > 10k€

### Alerte Warning - Espace Disque Faible
```promql
(crypto4_system_disk_free_bytes / crypto4_system_disk_total_bytes) * 100 < 20
```
→ Moins de 20% d'espace libre

### Alerte Info - Cache Inefficace
```promql
rate(crypto4_cache_misses_total[5m]) / rate(crypto4_cache_hits_total[5m]) > 0.5
```
→ Taux de miss > 50%

---

## 🐛 DÉPANNAGE

### Les métriques ne s'affichent pas ?

```bash
# Vérifier que le script fonctionne
php cli/metrics_exporter.php stdout | head -20

# Vérifier les permissions
ls -la /tmp/metrics.prom

# Vérifier les logs Prometheus
docker logs prometheus
```

### Grafana ne se connecte pas à Prometheus ?

1. Vérifiez que Prometheus est accessible : http://localhost:9090/api/v1/targets
2. Dans Grafana : Configuration → Data Sources → Prometheus → Save & Test
3. Assurez-vous que l'URL est `http://prometheus:9090` (dans Docker)

---

## 📚 RESSOURCES

- [Documentation Prometheus](https://prometheus.io/docs/)
- [Documentation Grafana](https://grafana.com/docs/)
- [PromQL Cheat Sheet](https://promlabs.com/promql-cheat-sheet/)

---

*Monitoring Crypto 4.0 v1.0 - Ready for Production*
