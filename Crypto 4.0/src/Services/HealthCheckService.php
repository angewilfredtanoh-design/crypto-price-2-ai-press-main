<?php

namespace Crypto4\Services;

/**
 * Service de Health Check pour le monitoring
 * Vérifie l'état de santé des composants critiques
 */
class HealthCheckService
{
    private array $checks = [];
    private bool $isHealthy = true;

    /**
     * Vérifier la base de données
     */
    public function checkDatabase(): self
    {
        try {
            $db = \Crypto4\Core\Database::getInstance();
            $pdo = $db->getConnection();
            
            // Test de lecture/écriture
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetchColumn();
            
            if ($result == 1) {
                $this->checks['database'] = [
                    'status' => 'healthy',
                    'message' => 'Database connection OK',
                    'response_time_ms' => $this->measureResponseTime(function() use ($pdo) {
                        $pdo->query("SELECT 1")->fetchColumn();
                    })
                ];
            } else {
                throw new \Exception('Unexpected database response');
            }
        } catch (\Exception $e) {
            $this->checks['database'] = [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'response_time_ms' => null
            ];
            $this->isHealthy = false;
        }

        return $this;
    }

    /**
     * Vérifier le service de cache (Redis/File)
     */
    public function checkCache(): self
    {
        try {
            $cache = \Crypto4\Services\CacheService::getInstance();
            $testKey = 'health_check_' . time();
            $testValue = 'ok';

            // Test écriture
            $cache->set($testKey, $testValue, 10);
            
            // Test lecture
            $retrieved = $cache->get($testKey);
            
            if ($retrieved === $testValue) {
                $this->checks['cache'] = [
                    'status' => 'healthy',
                    'message' => 'Cache service OK',
                    'backend' => method_exists($cache, 'getBackend') ? $cache->getBackend() : 'unknown',
                    'response_time_ms' => $this->measureResponseTime(function() use ($cache, $testKey, $testValue) {
                        $cache->set($testKey, $testValue, 10);
                        $cache->get($testKey);
                    })
                ];
                
                // Nettoyage
                $cache->delete($testKey);
            } else {
                throw new \Exception('Cache read/write mismatch');
            }
        } catch (\Exception $e) {
            $this->checks['cache'] = [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'response_time_ms' => null
            ];
            $this->isHealthy = false;
        }

        return $this;
    }

    /**
     * Vérifier les APIs externes (CoinGecko, Mistral)
     */
    public function checkExternalAPIs(): self
    {
        // CoinGecko
        try {
            $startTime = microtime(true);
            $coingecko = new \Crypto4\Services\CoinGeckoService();
            $price = $coingecko->getCurrentPrice('bitcoin', 'eur');
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if ($price > 0) {
                $this->checks['api_coingecko'] = [
                    'status' => 'healthy',
                    'message' => 'CoinGecko API OK',
                    'response_time_ms' => round($responseTime, 2)
                ];
            } else {
                throw new \Exception('Invalid price response');
            }
        } catch (\Exception $e) {
            $this->checks['api_coingecko'] = [
                'status' => 'degraded',
                'message' => $e->getMessage(),
                'response_time_ms' => null
            ];
        }

        // Mistral AI (seulement si clé configurée)
        try {
            $apiKey = getenv('MISTRAL_API_KEY');
            if ($apiKey && strlen($apiKey) > 10) {
                $startTime = microtime(true);
                $mistral = new \Crypto4\Services\MistralAIService();
                // Test léger sans consommer trop de tokens
                $response = $mistral->generateSimpleResponse('Test health check', 'test');
                $responseTime = (microtime(true) - $startTime) * 1000;
                
                $this->checks['api_mistral'] = [
                    'status' => 'healthy',
                    'message' => 'Mistral AI API OK',
                    'response_time_ms' => round($responseTime, 2)
                ];
            } else {
                $this->checks['api_mistral'] = [
                    'status' => 'skipped',
                    'message' => 'No API key configured',
                    'response_time_ms' => null
                ];
            }
        } catch (\Exception $e) {
            $this->checks['api_mistral'] = [
                'status' => 'degraded',
                'message' => $e->getMessage(),
                'response_time_ms' => null
            ];
        }

        return $this;
    }

    /**
     * Vérifier l'espace disque
     */
    public function checkDiskSpace(): self
    {
        $freeSpace = disk_free_space(LOG_DIR ?? '/tmp');
        $totalSpace = disk_total_space(LOG_DIR ?? '/tmp');
        $percentFree = ($freeSpace / $totalSpace) * 100;

        $status = $percentFree > 20 ? 'healthy' : ($percentFree > 10 ? 'warning' : 'critical');
        
        $this->checks['disk_space'] = [
            'status' => $status,
            'message' => sprintf('%.2f%% free (%.2f GB / %.2f GB)', 
                $percentFree, 
                $freeSpace / 1024 / 1024 / 1024, 
                $totalSpace / 1024 / 1024 / 1024
            ),
            'percent_free' => round($percentFree, 2)
        ];

        if ($status === 'critical') {
            $this->isHealthy = false;
        }

        return $this;
    }

    /**
     * Vérifier les processus en cours (trading, backtest)
     */
    public function checkRunningProcesses(): self
    {
        $processes = [];
        
        // Vérifier s'il y a des fichiers PID actifs
        $pidDir = RUNTIME_DIR ?? '/tmp/crypto4_pids';
        if (is_dir($pidDir)) {
            $files = scandir($pidDir);
            foreach ($files as $file) {
                if (preg_match('/\.pid$/', $file)) {
                    $pid = (int)file_get_contents($pidDir . '/' . $file);
                    $processName = str_replace('.pid', '', $file);
                    
                    // Vérifier si le process existe encore
                    $exists = @posix_getpgid($pid);
                    $processes[] = [
                        'name' => $processName,
                        'pid' => $pid,
                        'running' => $exists !== false
                    ];
                }
            }
        }

        $this->checks['processes'] = [
            'status' => 'healthy',
            'message' => count($processes) . ' process(es) tracked',
            'details' => $processes
        ];

        return $this;
    }

    /**
     * Mesurer le temps d'exécution d'une fonction
     */
    private function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Obtenir le statut global
     */
    public function getStatus(): string
    {
        return $this->isHealthy ? 'healthy' : 'unhealthy';
    }

    /**
     * Obtenir tous les checks
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * Générer un rapport JSON
     */
    public function toJson(): string
    {
        return json_encode([
            'status' => $this->getStatus(),
            'timestamp' => date('c'),
            'checks' => $this->checks,
            'overall_healthy' => $this->isHealthy
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Exécuter tous les checks
     */
    public function runAll(): self
    {
        return $this
            ->checkDatabase()
            ->checkCache()
            ->checkExternalAPIs()
            ->checkDiskSpace()
            ->checkRunningProcesses();
    }
}
