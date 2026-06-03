# Dockerfile - Environnement de Développement
# NEO CRYPTO DASH / Crypto 4.0

FROM php:8.2-cli

# Définir le répertoire de travail
WORKDIR /app

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_sqlite curl json

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Créer un utilisateur non-root pour la sécurité
RUN useradd -m -u 1000 appuser

# Copier les fichiers du projet
COPY . /app/

# Donner les permissions appropriées
RUN chown -R appuser:appuser /app \
    && chmod -R 755 /app/cache /app/logs /app/data /app/exports 2>/dev/null || true

# Passer à l'utilisateur non-root
USER appuser

# Exposer le port pour le serveur PHP intégré
EXPOSE 8080

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php -r "require 'config.php'; echo 'OK';" || exit 1

# Commande par défaut : lancer le serveur PHP intégré
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
