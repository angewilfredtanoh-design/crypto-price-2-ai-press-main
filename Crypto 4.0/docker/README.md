# Docker - NEO CRYPTO DASH / Crypto 4.0

Ce dossier contient la configuration Docker pour les environnements de développement et de production.

## Structure

```
docker/
├── dev/
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── php.ini
├── prod/
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── php.ini
└── scripts/
    ├── init-db.sh
    └── entrypoint.sh
```

## Environnement de Développement

### Prérequis
- Docker >= 20.10
- Docker Compose >= 2.0

### Démarrage

```bash
cd docker/dev
docker-compose up -d --build
```

### Services inclus

- **PHP 8.2** avec extensions nécessaires (pdo_sqlite, curl, json)
- **phpMyAdmin** pour visualiser la base SQLite (optionnel)
- **Volumes** pour le code source et les données persistantes

### Accès

- Application : http://localhost:8080
- phpMyAdmin : http://localhost:8081 (si activé)

### Commandes utiles

```bash
# Voir les logs
docker-compose logs -f

# Exécuter des commandes dans le container
docker-compose exec app php -v
docker-compose exec app composer install

# Lancer les tests
docker-compose exec app composer test

# Redémarrer
docker-compose restart

# Arrêter
docker-compose down
```

## Environnement de Production

### Build

```bash
cd docker/prod
docker-compose build
```

### Déploiement

```bash
# Démarrer en mode détaché
docker-compose up -d

# Voir les logs en temps réel
docker-compose logs -f app
```

### Sécurité

- Les variables sensibles sont passées via `.env`
- Le container tourne avec un utilisateur non-root
- Les ports sont exposés uniquement si nécessaire

### Variables d'environnement requises

Créez un fichier `.env` dans `docker/prod/` :

```env
# Application
APP_ENV=production
APP_DEBUG=false

# Base de données
DB_FILE=/app/data/crypto_cache.db

# API Keys (à remplacer par vos vraies clés)
MISTRAL_API_KEYS=nrcTwO2J9Y09I04vgFWEVVtjg4iT7aya

# Logs
LOG_LEVEL=ERROR
```

### Persistance des données

Les volumes Docker persistent :
- `/app/data` : Base de données SQLite
- `/app/logs` : Fichiers de logs
- `/app/cache` : Cache applicatif

### Backup

```bash
# Sauvegarder la base de données
docker-compose exec app tar -czf /tmp/db-backup.tar.gz /app/data

# Copier vers l'hôte
docker-compose cp app:/tmp/db-backup.tar.gz ./backups/
```

### Mise à jour

```bash
# Tirer la nouvelle version
git pull origin main

# Rebuild et redémarrer
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Vérifier les logs
docker-compose logs -f
```

## Scripts personnalisés

### entrypoint.sh

Script d'initialisation du container :
- Crée les dossiers nécessaires
- Définit les permissions
- Lance les migrations DB si besoin

### init-db.sh

Initialise la base de données :
- Crée les tables si inexistantes
- Importe les données de démo (dev seulement)

## Monitoring

### Healthcheck

Le Dockerfile inclut un healthcheck :

```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php -r "require 'config.php'; echo 'OK';" || exit 1
```

### Logs

Les logs sont disponibles dans :
- Container : `/app/logs/`
- Host (via volumes) : `./logs/`

### Metrics

Endpoint JSON pour monitoring externe :
```bash
curl http://localhost:8080/public/metrics.php
```

## Troubleshooting

### Problèmes de permissions

```bash
docker-compose exec app chown -R www-data:www-data /app
docker-compose exec app chmod -R 755 /app/cache /app/logs /app/data
```

### Base de données corrompue

```bash
# Supprimer et recréer
docker-compose down
rm -rf data/*.db
docker-compose up -d
```

### Logs vides

Vérifiez le niveau de log dans `config.php` :
```php
define('LOG_LEVEL', 'DEBUG'); // Pour plus de détails
```

## Optimisations Production

### PHP OPcache

Activé par défaut dans `prod/php.ini` :
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### Cache applicatif

Le cache est automatiquement géré par `CacheManager.php`.

### Compression Gzip

Configurée dans le serveur web (Nginx/Apache).

## Intégration Continue

### GitHub Actions

Exemple de workflow `.github/workflows/docker.yml` :

```yaml
name: Docker Build

on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Build Docker image
        run: |
          cd docker/dev
          docker-compose build
      
      - name: Run tests
        run: |
          cd docker/dev
          docker-compose run app composer test
```

## Support

Pour toute question ou problème :
1. Consultez les logs : `docker-compose logs -f`
2. Vérifiez la configuration `.env`
3. Assurez-vous que les ports ne sont pas utilisés
4. Redémarrez les containers : `docker-compose restart`
