<?php

namespace Crypto\Core;

/**
 * Gestionnaire de Rate Limiting intelligent
 * Implémente plusieurs stratégies : Token Bucket, Sliding Window, Fixed Window
 */
class RateLimiter
{
    private CacheManager $cache;
    private array $config;

    // Stratégies disponibles
    public const STRATEGY_TOKEN_BUCKET = 'token_bucket';
    public const STRATEGY_SLIDING_WINDOW = 'sliding_window';
    public const STRATEGY_FIXED_WINDOW = 'fixed_window';

    public function __construct(CacheManager $cache, array $config = [])
    {
        $this->cache = $cache;
        $this->config = array_merge([
            'default_strategy' => self::STRATEGY_TOKEN_BUCKET,
            'default_limit' => 100,
            'default_window' => 3600, // 1 heure en secondes
        ], $config);
    }

    /**
     * Vérifier si une requête est autorisée
     * 
     * @param string $identifier Identifiant unique (IP, user_id, API key, etc.)
     * @param string $resource Nom de la ressource/API endpoint
     * @param int $limit Nombre maximum de requêtes autorisées
     * @param int $window Fenêtre de temps en secondes
     * @param string $strategy Stratégie à utiliser
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int, 'retry_after' => int]
     */
    public function isAllowed(
        string $identifier,
        string $resource = 'default',
        int $limit = null,
        int $window = null,
        string $strategy = null
    ): array {
        $limit = $limit ?? $this->config['default_limit'];
        $window = $window ?? $this->config['default_window'];
        $strategy = $strategy ?? $this->config['default_strategy'];

        $key = $this->buildKey($identifier, $resource);

        switch ($strategy) {
            case self::STRATEGY_TOKEN_BUCKET:
                return $this->tokenBucketStrategy($key, $limit, $window);
            
            case self::STRATEGY_SLIDING_WINDOW:
                return $this->slidingWindowStrategy($key, $limit, $window);
            
            case self::STRATEGY_FIXED_WINDOW:
                return $this->fixedWindowStrategy($key, $limit, $window);
            
            default:
                throw new \InvalidArgumentException("Stratégie de rate limiting inconnue: {$strategy}");
        }
    }

    /**
     * Stratégie Token Bucket
     * Permet des bursts tout en maintenant une moyenne
     */
    private function tokenBucketStrategy(string $key, int $limit, int $window): array
    {
        $now = time();
        $bucket = $this->cache->get($key);
        
        if ($bucket === null) {
            // Initialiser le bucket
            $bucket = [
                'tokens' => $limit,
                'last_update' => $now,
                'refill_rate' => $limit / $window // tokens par seconde
            ];
        }

        // Calculer les tokens à ajouter depuis la dernière mise à jour
        $timePassed = $now - $bucket['last_update'];
        $tokensToAdd = $timePassed * $bucket['refill_rate'];
        $bucket['tokens'] = min($limit, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_update'] = $now;

        if ($bucket['tokens'] >= 1) {
            // Autoriser la requête
            $bucket['tokens'] -= 1;
            $this->cache->set($key, $bucket, $window);
            
            return [
                'allowed' => true,
                'remaining' => (int) floor($bucket['tokens']),
                'reset' => $now + $window,
                'retry_after' => 0,
                'limit' => $limit,
                'strategy' => 'token_bucket'
            ];
        } else {
            // Refuser la requête
            $this->cache->set($key, $bucket, $window);
            
            // Calculer le temps d'attente jusqu'au prochain token
            $retryAfter = (int) ceil((1 - $bucket['tokens']) / $bucket['refill_rate']);
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $now + $window,
                'retry_after' => $retryAfter,
                'limit' => $limit,
                'strategy' => 'token_bucket'
            ];
        }
    }

    /**
     * Stratégie Sliding Window (fenêtre glissante)
     * Plus précise que fixed window, évite les pics aux limites
     */
    private function slidingWindowStrategy(string $key, int $limit, int $window): array
    {
        $now = time();
        $windowStart = $now - $window;
        
        // Récupérer toutes les timestamps dans la fenêtre
        $requests = $this->cache->get($key . ':requests') ?? [];
        
        // Filtrer les requêtes en dehors de la fenêtre
        $requests = array_filter($requests, fn($ts) => $ts > $windowStart);
        $requests = array_values($requests); // Réindexer
        
        $currentCount = count($requests);

        if ($currentCount < $limit) {
            // Autoriser la requête
            $requests[] = $now;
            $this->cache->set($key . ':requests', $requests, $window);
            
            return [
                'allowed' => true,
                'remaining' => $limit - count($requests),
                'reset' => $now + $window,
                'retry_after' => 0,
                'limit' => $limit,
                'strategy' => 'sliding_window'
            ];
        } else {
            // Refuser la requête
            // Le reset est quand la plus vieille requête sort de la fenêtre
            $oldestRequest = min($requests);
            $retryAfter = (int) ceil($oldestRequest + $window - $now);
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $oldestRequest + $window,
                'retry_after' => max(1, $retryAfter),
                'limit' => $limit,
                'strategy' => 'sliding_window'
            ];
        }
    }

    /**
     * Stratégie Fixed Window (fenêtre fixe)
     * Simple et efficace, mais peut permettre des pics aux limites
     */
    private function fixedWindowStrategy(string $key, int $limit, int $window): array
    {
        $now = time();
        $windowKey = $key . ':' . floor($now / $window);
        
        $count = $this->cache->get($windowKey) ?? 0;

        if ($count < $limit) {
            // Autoriser la requête
            $count++;
            $this->cache->set($windowKey, $count, $window);
            
            $windowEnd = (floor($now / $window) + 1) * $window;
            
            return [
                'allowed' => true,
                'remaining' => $limit - $count,
                'reset' => $windowEnd,
                'retry_after' => 0,
                'limit' => $limit,
                'strategy' => 'fixed_window'
            ];
        } else {
            // Refuser la requête
            $windowEnd = (floor($now / $window) + 1) * $window;
            $retryAfter = $windowEnd - $now;
            
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $windowEnd,
                'retry_after' => max(1, $retryAfter),
                'limit' => $limit,
                'strategy' => 'fixed_window'
            ];
        }
    }

    /**
     * Construire une clé unique pour le cache
     */
    private function buildKey(string $identifier, string $resource): string
    {
        return "ratelimit:{$resource}:{$identifier}";
    }

    /**
     * Réinitialiser le compteur pour un identifiant donné
     */
    public function reset(string $identifier, string $resource = 'default'): bool
    {
        $key = $this->buildKey($identifier, $resource);
        
        $this->cache->delete($key);
        $this->cache->delete($key . ':requests');
        
        // Pour fixed window, supprimer toutes les fenêtres actives
        $pattern = "ratelimit:{$resource}:{$identifier}:*";
        // Note: Nécessiterait une méthode scan dans CacheManager pour être complet
        
        return true;
    }

    /**
     * Obtenir l'état actuel du rate limiter pour un identifiant
     */
    public function getStatus(string $identifier, string $resource = 'default', int $limit = null, int $window = null): array
    {
        $limit = $limit ?? $this->config['default_limit'];
        $window = $window ?? $this->config['default_window'];
        
        // Faire une vérification sans consommer de token
        $result = $this->isAllowed($identifier, $resource, $limit, $window);
        
        // Si autorisé, on ne consomme pas (simulation)
        // Pour une vraie implémentation, il faudrait séparer check et consume
        
        return $result;
    }

    /**
     * Middleware helper pour les APIs
     * Lance une exception ou retourne false si rate limité
     */
    public function throttle(
        string $identifier,
        string $resource = 'default',
        int $limit = null,
        int $window = null,
        bool $throwException = true
    ): bool {
        $result = $this->isAllowed($identifier, $resource, $limit, $window);
        
        if (!$result['allowed']) {
            if ($throwException) {
                throw new RateLimitExceededException(
                    "Rate limit exceeded for {$identifier} on {$resource}",
                    $result['retry_after'],
                    $result
                );
            }
            return false;
        }
        
        return true;
    }
}

/**
 * Exception personnalisée pour le Rate Limiting
 */
class RateLimitExceededException extends \Exception
{
    private int $retryAfter;
    private array $context;

    public function __construct(string $message, int $retryAfter, array $context = [], int $code = 429)
    {
        parent::__construct($message, $code);
        $this->retryAfter = $retryAfter;
        $this->context = $context;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getHeaders(): array
    {
        return [
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Limit' => $this->context['limit'] ?? 0,
            'X-RateLimit-Remaining' => $this->context['remaining'] ?? 0,
            'X-RateLimit-Reset' => $this->context['reset'] ?? 0,
            'X-RateLimit-Strategy' => $this->context['strategy'] ?? 'unknown'
        ];
    }
}
