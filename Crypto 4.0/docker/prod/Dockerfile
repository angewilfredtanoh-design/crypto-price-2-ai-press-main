# Dockerfile - Environnement de Production
# NEO CRYPTO DASH / Crypto 4.0

FROM php:8.2-fpm-alpine

# Définir le répertoire de travail
WORKDIR /app

# Installer les extensions système nécessaires
RUN apk add --no-cache \
    nginx \
    sqlite \
    sqlite-libs \
    curl \
    git \
    zip \
    unzip \
    bash \
    supervisor

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_sqlite curl json opcache

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Créer l'utilisateur et le groupe pour Nginx/PHP
RUN addgroup -g 1000 appgroup && \
    adduser -u 1000 -G appgroup -s /bin/bash -D appuser

# Copier la configuration Nginx
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf

# Copier la configuration PHP-FPM
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copier les fichiers du projet
COPY . /app/

# Copier le script entrypoint
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Créer les dossiers nécessaires avec permissions appropriées
RUN mkdir -p /app/data /app/logs /app/cache /app/exports && \
    chown -R appuser:appgroup /app && \
    chmod -R 755 /app/data /app/logs /app/cache /app/exports && \
    chmod 777 /app/logs

# Configuration de Supervisor pour gérer Nginx et PHP-FPM
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:80/ || exit 1

# Exposer le port HTTP
EXPOSE 80

# Utiliser le script entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Commande par défaut : lancer supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
