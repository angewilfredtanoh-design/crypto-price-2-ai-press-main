<?php
/**
 * src/Core/MistralClient.php
 * Client API Mistral centralisé avec rotation intelligente des clés
 * Pour Crypto 4.0
 */

namespace Crypto4\Core;

if (!defined('ROOT_DIR')) {
    require_once dirname(__DIR__, 2) . '/config.php';
}

class MistralClient {
    private $apiKeys;
    private $endpoint;
    private $config;
    private $blacklistedKeys = [];
    
    public function __construct() {
        $this->apiKeys = defined('DEFAULT_MISTRAL_API_KEYS') ? DEFAULT_MISTRAL_API_KEYS : [];
        $this->endpoint = defined('MISTRAL_API_ENDPOINT') ? MISTRAL_API_ENDPOINT : 'https://api.mistral.ai/v1/chat/completions';
        $this->config = defined('API_ROTATION_CONFIG') ? API_ROTATION_CONFIG : [
            'max_retries' => 3,
            'retry_delay_ms' => 1000,
            'blacklist_duration_seconds' => 300,
            'timeout_seconds' => 30
        ];
        
        // Vérifier si des clés sont blacklistées depuis une précédente exécution
        $this->loadBlacklist();
    }
    
    /**
     * Charger les clés blacklistées depuis le cache
     */
    private function loadBlacklist() {
        $cacheFile = CACHE_DIR . '/mistral_blacklist.json';
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['keys']) && isset($data['expires'])) {
                $now = time();
                foreach ($data['keys'] as $keyHash => $expireAt) {
                    if ($expireAt > $now) {
                        $this->blacklistedKeys[$keyHash] = $expireAt;
                    }
                }
            }
        }
    }
    
    /**
     * Sauvegarder les clés blacklistées
     */
    private function saveBlacklist() {
        $cacheFile = CACHE_DIR . '/mistral_blacklist.json';
        $data = ['keys' => $this->blacklistedKeys, 'expires' => time() + 3600];
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    }
    
    /**
     * Hacher une clé pour le logging sécurisé
     */
    private function hashKey($key) {
        return substr(md5($key), 0, 8);
    }
    
    /**
     * Appeler l'API Mistral avec rotation automatique des clés
     * @param array $messages Messages au format OpenAI
     * @param string $model Modèle à utiliser
     * @param int $maxTokens Nombre maximum de tokens en réponse
     * @param float $temperature Température (0.0 - 1.0)
     * @return string|null Contenu de la réponse ou null en cas d'échec
     */
    public function call($messages, $model = 'mistral-small-2603', $maxTokens = 200, $temperature = 0.3) {
        if (empty($this->apiKeys)) {
            appLog("Aucune clé API Mistral configurée", 'CRITICAL');
            return null;
        }
        
        $availableKeys = array_filter($this->apiKeys, function($key) {
            $hash = $this->hashKey($key);
            return !isset($this->blacklistedKeys[$hash]);
        });
        
        if (empty($availableKeys)) {
            appLog("Toutes les clés API sont blacklistées", 'ERROR');
            $availableKeys = $this->apiKeys; // Fallback: toutes les clés
        }
        
        foreach ($availableKeys as $index => $apiKey) {
            for ($retry = 0; $retry < $this->config['max_retries']; $retry++) {
                try {
                    $ch = curl_init($this->endpoint);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey,
                        'User-Agent: ' . API_ROTATION_CONFIG['user_agent']
                    ]);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens
                    ]));
                    curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout_seconds']);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        $data = json_decode($response, true);
                        if (isset($data['choices'][0]['message']['content'])) {
                            $this->logApiUsage($model, strlen(json_encode($messages)), 
                                strlen($response), true, $index, $httpCode);
                            return $data['choices'][0]['message']['content'];
                        }
                    }
                    
                    // Gérer les erreurs
                    $keyHash = $this->hashKey($apiKey);
                    appLog("Échec API clé #$index (HTTP $httpCode): " . substr($response, 0, 100), 'WARNING');
                    
                    if ($httpCode >= 400 && $httpCode < 500) {
                        // Erreur client (4xx): blacklist la clé
                        $this->blacklistedKeys[$keyHash] = time() + API_ROTATION_CONFIG['blacklist_duration_seconds'];
                        $this->saveBlacklist();
                        appLog("Clé #$index blacklistée pour " . API_ROTATION_CONFIG['blacklist_duration_seconds'] . "s", 'WARNING');
                        break; // Passer à la clé suivante
                    }
                    
                    // Retry avec délai
                    if ($retry < $this->config['max_retries'] - 1) {
                        usleep(API_ROTATION_CONFIG['retry_delay_ms'] * 1000 * ($retry + 1));
                    }
                    
                } catch (Exception $e) {
                    appLog("Exception API: " . $e->getMessage(), 'ERROR');
                    continue;
                }
            }
        }
        
        appLog("Toutes les clés API ont échoué pour le modèle $model", 'ERROR');
        $this->logApiUsage($model, 0, 0, false, -1, 0);
        return null;
    }
    
    /**
     * Logger l'utilisation de l'API dans la base de données
     */
    private function logApiUsage($model, $promptLength, $responseLength, $success, $keyIndex, $httpCode) {
        try {
            if (!file_exists(DB_FILE)) return;
            
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("INSERT INTO api_usage_logs 
                (timestamp, model_used, tokens_prompt, tokens_completion, tokens_total, 
                 success, error_message, response_time_ms, key_index) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                time(),
                $model,
                intval($promptLength / 4),
                intval($responseLength / 4),
                intval(($promptLength + $responseLength) / 4),
                $success ? 1 : 0,
                $success ? null : "HTTP $httpCode",
                0, // Sera mis à jour avec le vrai temps de réponse
                $keyIndex
            ]);
        } catch (Exception $e) {
            // Ignorer les erreurs de logging
        }
    }
    
    /**
     * Obtenir le modèle optimal pour une tâche donnée
     */
    public function getModelForTask($taskName) {
        if (defined('TASK_MODEL_MAPPING') && isset(TASK_MODEL_MAPPING[$taskName])) {
            return TASK_MODEL_MAPPING[$taskName];
        }
        return API_ROTATION_CONFIG['fallback_model'] ?? 'mistral-small-2603';
    }
    
    /**
     * Appel simplifié pour compatibilité avec l'ancien code
     */
    public static function quickCall($messages, $model = 'mistral-small-2603', $maxTokens = 200) {
        $client = new self();
        return $client->call($messages, $model, $maxTokens);
    }
}

// Fonction legacy pour compatibilité avec l'ancien code
function callMistral($messages, $model = 'mistral-small-2603', $maxTokens = 200) {
    return MistralClient::quickCall($messages, $model, $maxTokens);
}

?>
