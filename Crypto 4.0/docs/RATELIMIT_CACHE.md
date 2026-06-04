# Rate Limiting & Caching Intelligent

## 📋 Vue d'ensemble

Crypto 4.0 intègre désormais un système avancé de **Rate Limiting** et de **Caching Intelligent** pour optimiser les performances et protéger vos APIs.

## 🚀 Fonctionnalités

### CacheManager
- **Support Redis** (prioritaire) avec fallback automatique sur cache fichier
- **TTL configurable** par clé
- **Nettoyage automatique** des entrées expirées
- **Statistiques en temps réel**
- **Sécurité** : hachage des clés pour les noms de fichiers

### RateLimiter
Trois stratégies implémentées :

1. **Token Bucket** (par défaut)
   - Permet des bursts contrôlés
   - Maintient une moyenne de requêtes
   - Idéal pour les APIs avec trafic variable

2. **Sliding Window**
   - Fenêtre glissante précise
   - Évite les pics aux limites de temps
   - Plus équitable pour tous les utilisateurs

3. **Fixed Window**
   - Simple et efficace
   - Basé sur des fenêtres de temps fixes
   - Moins de surcharge mémoire

## 📦 Installation

### Prérequis
- PHP 8.2+
- Composer
- Redis (optionnel, recommandé pour la production)

### Installation de Redis (optionnel)
```bash
# Installer Redis Server
sudo apt-get install redis-server

# Installer l'extension PHP Redis
pecl install redis

# Activer l'extension
echo "extension=redis.so" | sudo tee /etc/php/8.2/cli/conf.d/20-redis.ini
echo "extension=redis.so" | sudo tee /etc/php/8.2/apache2/conf.d/20-redis.ini

# Redémarrer le serveur web
sudo systemctl restart apache2
```

## ⚙️ Configuration

### Configuration du Cache

```php
$config = [
    'cache' => [
        'redis_enabled' => true,        // Activer Redis
        'redis_host' => '127.0.0.1',    // Hôte Redis
        'redis_port' => 6379,           // Port Redis
        'redis_password' => '',         // Mot de passe (si nécessaire)
        'redis_database' => 0,          // Base de données Redis
        'file_cache_dir' => './cache/', // Dossier de fallback
        'prefix' => 'crypto4_'          // Préfixe des clés
    ]
];

$cache = new CacheManager($config['cache']);
```

### Configuration du Rate Limiter

```php
$config = [
    'rate_limiter' => [
        'default_strategy' => RateLimiter::STRATEGY_TOKEN_BUCKET,
        'default_limit' => 100,     // Nombre de requêtes max
        'default_window' => 3600    // Fenêtre de temps (secondes)
    ]
];

$rateLimiter = new RateLimiter($cache, $config['rate_limiter']);
```

## 💡 Exemples d'utilisation

### Utiliser le Cache

```php
use Crypto\Core\CacheManager;

$cache = new CacheManager($config);

// Stocker une valeur (TTL: 5 minutes)
$cache->set('user_123_profile', $userData, 300);

// Récupérer une valeur
$userData = $cache->get('user_123_profile');

// Vérifier l'existence
if ($cache->has('user_123_profile')) {
    echo "Données en cache!";
}

// Supprimer une clé
$cache->delete('user_123_profile');

// Vider tout le cache
$cache->flush();

// Obtenir les statistiques
$stats = $cache->getStats();
echo "Type: {$stats['type']}, Clés: {$stats['keys_count']}";
```

### Utiliser le Rate Limiter

```php
use Crypto\Core\RateLimiter;
use Crypto\Core\RateLimitExceededException;

$rateLimiter = new RateLimiter($cache);

// Méthode simple
$result = $rateLimiter->isAllowed('user_123', 'api_trading');

if ($result['allowed']) {
    // Traiter la requête
    echo "Requête autorisée. Remaining: {$result['remaining']}";
} else {
    // Refuser la requête
    header("Retry-After: {$result['retry_after']}");
    http_response_code(429);
    echo "Trop de requêtes. Réessayez dans {$result['retry_after']}s";
}

// Avec exception automatique
try {
    $rateLimiter->throttle('user_123', 'api_trading', 10, 60);
    // Traiter la requête
} catch (RateLimitExceededException $e) {
    http_response_code(429);
    foreach ($e->getHeaders() as $header => $value) {
        header("{$header}: {$value}");
    }
    echo $e->getMessage();
}
```

### Stratégies différentes par endpoint

```php
// API de trading : plus stricte
$tradingResult = $rateLimiter->isAllowed(
    'user_123',
    'trading',
    10,     // 10 requêtes
    60,     // par minute
    RateLimiter::STRATEGY_SLIDING_WINDOW
);

// API de consultation : plus permissive
$infoResult = $rateLimiter->isAllowed(
    'user_123',
    'info',
    100,    // 100 requêtes
    3600,   // par heure
    RateLimiter::STRATEGY_TOKEN_BUCKET
);
```

## 🎯 Cas d'usage recommandés

### Protection d'API
```php
// Dans votre controller API
public function handleRequest($userId) {
    try {
        $this->rateLimiter->throttle($userId, 'api', 50, 3600);
        
        // Traitement de la requête
        return $this->process();
        
    } catch (RateLimitExceededException $e) {
        return response()->tooManyRequests($e->getHeaders());
    }
}
```

### Cache de données externes
```php
// Éviter les appels API répétés
function getCoinPrice($symbol) {
    global $cache;
    
    $cacheKey = "coin_price_{$symbol}";
    
    // Vérifier le cache
    if ($cached = $cache->get($cacheKey)) {
        return $cached;
    }
    
    // Appel API externe
    $price = externalApi->getPrice($symbol);
    
    // Mettre en cache (10 minutes)
    $cache->set($cacheKey, $price, 600);
    
    return $price;
}
```

### Session utilisateur
```php
// Limiter les connexions
function login($username, $password) {
    global $rateLimiter;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    try {
        // 5 tentatives par 15 minutes
        $rateLimiter->throttle($ip, 'login', 5, 900);
        
        // Processus de connexion
        if (authenticate($username, $password)) {
            $rateLimiter->reset($ip, 'login');
            return true;
        }
        
        return false;
        
    } catch (RateLimitExceededException $e) {
        logAttempt($ip, 'blocked');
        throw $e;
    }
}
```

## 📊 Headers HTTP

Le RateLimiter ajoute automatiquement ces headers :

- `X-RateLimit-Limit`: Nombre maximum de requêtes
- `X-RateLimit-Remaining`: Requêtes restantes
- `X-RateLimit-Reset`: Timestamp de réinitialisation
- `X-RateLimit-Strategy`: Stratégie utilisée
- `Retry-After`: Secondes avant nouvelle tentative (si bloqué)

## 🔧 Commandes utiles

### Tester le système
```bash
cd "/workspace/Crypto 4.0"
php examples/cache_ratelimit_demo.php
```

### Nettoyer le cache fichier manuellement
```bash
rm -rf cache/*.cache
```

### Vérifier les statistiques Redis
```bash
redis-cli INFO memory
redis-cli KEYS "crypto4_*"
```

## 📝 Bonnes pratiques

1. **Utilisez des identifiants uniques** : IP, user_id, API key
2. **Adaptez les limites** selon le type d'endpoint
3. **Monitorer les stats** régulièrement
4. **Prévoyez un fallback** si Redis est indisponible
5. **Documentez vos limites** pour les consommateurs d'API

## 🐛 Dépannage

### Le cache fichier ne fonctionne pas
```bash
# Vérifier les permissions
chmod 755 /workspace/Crypto\ 4.0/cache
chown www-data:www-data /workspace/Crypto\ 4.0/cache
```

### Redis ne se connecte pas
```bash
# Vérifier que Redis tourne
sudo systemctl status redis-server

# Tester la connexion
redis-cli ping
# Doit retourner: PONG
```

### Extension Redis non trouvée
```bash
# Vérifier l'installation
php -m | grep redis

# Si vide, installer
pecl install redis
```

## 📚 Références

- [Pattern Token Bucket](https://en.wikipedia.org/wiki/Token_bucket)
- [Pattern Sliding Window](https://en.wikipedia.org/wiki/Sliding_window_protocol)
- [Redis Documentation](https://redis.io/documentation)
- [PSR-6 Cache Interface](https://www.php-fig.org/psr/psr-6/)

---

**Crypto 4.0** - Trading intelligent avec protection avancée
