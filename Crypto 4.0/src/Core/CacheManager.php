<?php

namespace Crypto4\Core;

use Redis;
use Exception;

/**
 * Gestionnaire de cache intelligent avec support Redis et fallback fichier
 */
class CacheManager
{
    private ?Redis $redis = null;
    private string $fileCacheDir;
    private bool $useRedis = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->fileCacheDir = $config['file_cache_dir'] ?? __DIR__ . '/../../cache/';
        
        // S'assurer que le dossier de cache fichier existe et est accessible
        if (!is_dir($this->fileCacheDir)) {
            mkdir($this->fileCacheDir, 0777, true);
        }
        
        // Créer les sous-dossiers pour le hachage (00-ff)
        for ($i = 0; $i < 256; $i++) {
            $subdir = $this->fileCacheDir . sprintf('%02x', $i);
            if (!is_dir($subdir)) {
                mkdir($subdir, 0777, true);
            }
        }

        // Tentative de connexion à Redis
        if ($config['redis_enabled'] ?? false) {
            try {
                $this->redis = new Redis();
                $host = $config['redis_host'] ?? '127.0.0.1';
                $port = $config['redis_port'] ?? 6379;
                
                if ($this->redis->connect($host, $port, 2.0)) {
                    if (!empty($config['redis_password'])) {
                        $this->redis->auth($config['redis_password']);
                    }
                    $this->redis->select($config['redis_database'] ?? 0);
                    $this->useRedis = true;
                    Logger::info('CacheManager: Connected to Redis');
                }
            } catch (Exception $e) {
                Logger::warning('CacheManager: Redis connection failed, falling back to file cache: ' . $e->getMessage());
                $this->useRedis = false;
            }
        }
    }

    /**
     * Récupérer une valeur du cache
     */
    public function get(string $key): mixed
    {
        $fullKey = $this->prefixKey($key);
        
        try {
            if ($this->useRedis && $this->redis) {
                $data = $this->redis->get($fullKey);
                if ($data !== false) {
                    Logger::debug("Cache HIT (Redis): {$key}");
                    return unserialize($data);
                }
            } else {
                $filePath = $this->getFilePath($fullKey);
                if (file_exists($filePath)) {
                    $data = file_get_contents($filePath);
                    $metadata = json_decode($data, true);
                    
                    if ($metadata && isset($metadata['expires']) && $metadata['expires'] > time()) {
                        Logger::debug("Cache HIT (File): {$key}");
                        return $metadata['data'];
                    } elseif ($metadata) {
                        // Expired, delete it
                        unlink($filePath);
                    }
                }
            }
        } catch (Exception $e) {
            Logger::error("Cache get error for {$key}: " . $e->getMessage());
        }

        Logger::debug("Cache MISS: {$key}");
        return null;
    }

    /**
     * Stocker une valeur dans le cache
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $fullKey = $this->prefixKey($key);
        
        try {
            if ($this->useRedis && $this->redis) {
                $result = $this->redis->setex($fullKey, $ttl, serialize($value));
                Logger::debug("Cache SET (Redis): {$key} (TTL: {$ttl}s)");
                return $result;
            } else {
                $filePath = $this->getFilePath($fullKey);
                $data = [
                    'data' => $value,
                    'expires' => time() + $ttl,
                    'created' => time()
                ];
                
                $result = file_put_contents($filePath, json_encode($data), LOCK_EX);
                Logger::debug("Cache SET (File): {$key} (TTL: {$ttl}s)");
                return $result !== false;
            }
        } catch (Exception $e) {
            Logger::error("Cache set error for {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer une clé du cache
     */
    public function delete(string $key): bool
    {
        $fullKey = $this->prefixKey($key);
        
        try {
            if ($this->useRedis && $this->redis) {
                $result = $this->redis->del($fullKey) > 0;
                Logger::debug("Cache DELETE (Redis): {$key}");
                return $result;
            } else {
                $filePath = $this->getFilePath($fullKey);
                if (file_exists($filePath)) {
                    $result = unlink($filePath);
                    Logger::debug("Cache DELETE (File): {$key}");
                    return $result;
                }
                return false;
            }
        } catch (Exception $e) {
            Logger::error("Cache delete error for {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vider tout le cache
     */
    public function flush(): bool
    {
        try {
            if ($this->useRedis && $this->redis) {
                $pattern = $this->prefixKey('*');
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
                Logger::info('Cache flushed (Redis)');
                return true;
            } else {
                $files = glob($this->fileCacheDir . '*.cache');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                Logger::info('Cache flushed (File)');
                return true;
            }
        } catch (Exception $e) {
            Logger::error("Cache flush error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si une clé existe dans le cache
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Obtenir les statistiques du cache
     */
    public function getStats(): array
    {
        $stats = [
            'type' => $this->useRedis ? 'redis' : 'file',
            'keys_count' => 0,
            'memory_usage' => 0
        ];

        try {
            if ($this->useRedis && $this->redis) {
                $pattern = $this->prefixKey('*');
                $keys = $this->redis->keys($pattern);
                $stats['keys_count'] = count($keys);
                
                $info = $this->redis->info('memory');
                $stats['memory_usage'] = $info['used_memory_human'] ?? 'N/A';
            } else {
                $files = glob($this->fileCacheDir . '*.cache');
                $stats['keys_count'] = count($files);
                $totalSize = array_sum(array_map('filesize', $files));
                $stats['memory_usage'] = $this->formatBytes($totalSize);
            }
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Nettoyer les clés expirées (pour le cache fichier)
     */
    public function cleanup(): int
    {
        if ($this->useRedis) {
            return 0; // Redis gère automatiquement l'expiration
        }

        $count = 0;
        $files = glob($this->fileCacheDir . '*.cache');
        
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            
            $data = file_get_contents($file);
            $metadata = json_decode($data, true);
            
            if ($metadata && isset($metadata['expires']) && $metadata['expires'] < time()) {
                unlink($file);
                $count++;
            }
        }

        if ($count > 0) {
            Logger::info("Cache cleanup: removed {$count} expired entries");
        }

        return $count;
    }

    private function prefixKey(string $key): string
    {
        $prefix = $this->config['prefix'] ?? 'crypto4_';
        return $prefix . $key;
    }

    private function getFilePath(string $key): string
    {
        // Hash the key to create a safe filename
        $hashedKey = hash('sha256', $key);
        return $this->fileCacheDir . substr($hashedKey, 0, 2) . '/' . $hashedKey . '.cache';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function isUsingRedis(): bool
    {
        return $this->useRedis;
    }
}
