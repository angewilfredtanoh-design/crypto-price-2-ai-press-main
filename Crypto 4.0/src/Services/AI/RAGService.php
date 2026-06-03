<?php

namespace App\Services\AI;

use App\Core\MistralClient;
use App\Core\CacheManager;
use App\Core\Logger;

/**
 * Système RAG (Retrieval-Augmented Generation) pour analyses contextuelles
 * Utilise un cache sémantique et des embeddings pour enrichir les réponses IA
 */
class RAGService
{
    private $mistral;
    private $cache;
    private $knowledgeBase = [];
    
    public function __construct()
    {
        $this->mistral = new MistralClient();
        $this->cache = new CacheManager();
        $this->loadKnowledgeBase();
    }

    /**
     * Charge la base de connaissances locale (fichiers JSON, historiques)
     */
    private function loadKnowledgeBase(): void
    {
        // Charger les rapports précédents comme contexte
        $reportsDir = ROOT_DIR . '/reports';
        if (is_dir($reportsDir)) {
            $files = array_slice(scandir($reportsDir), 2); // Skip . and ..
            foreach ($files as $file) {
                if (str_ends_with($file, '.json')) {
                    $content = file_get_contents($reportsDir . '/' . $file);
                    $data = json_decode($content, true);
                    if ($data) {
                        $this->knowledgeBase[] = [
                            'source' => $file,
                            'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
                            'timestamp' => filemtime($reportsDir . '/' . $file)
                        ];
                    }
                }
            }
        }
        
        Logger::info("RAG: " . count($this->knowledgeBase) . " documents chargés en mémoire");
    }

    /**
     * Génère un hash unique pour un prompt (pour le cache)
     */
    private function hashPrompt(string $prompt, array $context = []): string
    {
        return hash('sha256', $prompt . json_encode($context, JSON_SORT_KEYS));
    }

    /**
     * Trouve les documents les plus pertinents pour une query (simple keyword matching + TF-IDF simulé)
     * Dans une version prod, utiliser des embeddings vectoriels (ex: sentence-transformers)
     */
    private function retrieveContext(string $query, int $topK = 3): array
    {
        $queryTerms = strtolower($query);
        $scores = [];
        
        foreach ($this->knowledgeBase as $index => $doc) {
            $content = strtolower($doc['content']);
            $score = 0;
            
            // Scoring simple par occurrence de mots-clés
            $words = explode(' ', $queryTerms);
            foreach ($words as $word) {
                if (strlen($word) > 3 && strpos($content, $word) !== false) {
                    $score += substr_count($content, $word);
                }
            }
            
            // Bonus pour les documents récents
            $ageInDays = (time() - $doc['timestamp']) / 86400;
            $recencyBonus = max(0, 10 - $ageInDays); // +10 points si < 1 jour, 0 si > 10 jours
            
            $scores[$index] = $score + $recencyBonus;
        }
        
        // Tri et sélection du top K
        arsort($scores);
        $topIndices = array_slice(array_keys($scores), 0, $topK, true);
        
        $relevantDocs = [];
        foreach ($topIndices as $idx) {
            if ($scores[$idx] > 0) {
                $relevantDocs[] = $this->knowledgeBase[$idx];
            }
        }
        
        return $relevantDocs;
    }

    /**
     * Analyse avec RAG : récupère le contexte + appelle l'IA
     * @param string $query La question/analyse demandée
     * @param array $marketData Données de marché actuelles
     * @param bool $useCache Si true, utilise le cache des réponses
     */
    public function analyzeWithContext(string $query, array $marketData = [], bool $useCache = true): array
    {
        // 1. Vérifier le cache
        if ($useCache) {
            $cacheKey = 'rag_' . $this->hashPrompt($query, $marketData);
            if ($cached = $this->cache->get($cacheKey)) {
                Logger::info("RAG: Réponse récupérée depuis le cache");
                return json_decode($cached, true);
            }
        }

        // 2. Retrieval: trouver les documents pertinents
        $relevantDocs = $this->retrieveContext($query);
        $contextText = "";
        
        if (!empty($relevantDocs)) {
            $contextText = "\n\n--- CONTEXTE HISTORIQUE ---\n";
            foreach ($relevantDocs as $doc) {
                $contextText .= "[Source: {$doc['source']}]\n";
                // Extraire uniquement les parties pertinentes (simplified)
                $contextText .= substr($doc['content'], 0, 500) . "...\n";
            }
            $contextText .= "--- FIN CONTEXTE ---\n";
        }

        // 3. Augmentation: construire le prompt enrichi
        $enhancedPrompt = "Tu es un expert en trading crypto. Analyser la situation suivante:\n\n";
        $enhancedPrompt .= "DONNÉES MARCHÉ ACTUELLES:\n" . json_encode($marketData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        $enhancedPrompt .= $contextText;
        $enhancedPrompt .= "\nQUESTION/ANALYSE DEMANDÉE:\n{$query}\n\n";
        $enhancedPrompt .= "Fournis une analyse détaillée, en tenant compte du contexte historique fourni.";

        // 4. Génération: appel à Mistral
        Logger::info("RAG: Envoi du prompt enrichi à Mistral (" . strlen($enhancedPrompt) . " chars)");
        
        try {
            $response = $this->mistral->chat([
                ['role' => 'system', 'content' => 'Expert trader crypto, analyse technique et fondamentale.'],
                ['role' => 'user', 'content' => $enhancedPrompt]
            ], [
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]);

            $result = [
                'analysis' => $response['choices'][0]['message']['content'] ?? 'Erreur d\'analyse',
                'context_used' => count($relevantDocs),
                'sources' => array_column($relevantDocs, 'source'),
                'cache_key' => $cacheKey ?? null,
                'timestamp' => time()
            ];

            // 5. Cache la réponse
            if ($useCache) {
                $this->cache->set($cacheKey, json_encode($result), 3600); // 1 heure
            }

            return $result;
            
        } catch (\Exception $e) {
            Logger::error("RAG: Erreur Mistral - " . $e->getMessage());
            return [
                'analysis' => "Erreur lors de l'analyse IA: " . $e->getMessage(),
                'context_used' => count($relevantDocs),
                'fallback' => true
            ];
        }
    }

    /**
     * Multi-modèles en parallèle pour robustesse
     * Interroge plusieurs modèles et agrège les réponses
     */
    public function analyzeWithMultiModels(string $query, array $marketData = []): array
    {
        $models = ['mistral-small-latest', 'mistral-large-latest'];
        $results = [];
        
        Logger::info("RAG: Lancement de l'analyse multi-modèles (" . count($models) . " modèles)");
        
        // En séquentiel pour simplifier (en prod: curl_multi ou async)
        foreach ($models as $model) {
            try {
                $start = microtime(true);
                $response = $this->mistral->chat([
                    ['role' => 'system', 'content' => 'Expert trader crypto.'],
                    ['role' => 'user', 'content' => $query . "\n\nDonnées: " . json_encode($marketData)]
                ], [
                    'model' => $model,
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ]);
                
                $results[$model] = [
                    'analysis' => $response['choices'][0]['message']['content'] ?? 'Erreur',
                    'response_time' => round((microtime(true) - $start) * 1000, 2),
                    'status' => 'success'
                ];
                
            } catch (\Exception $e) {
                $results[$model] = [
                    'analysis' => "Erreur: " . $e->getMessage(),
                    'status' => 'failed'
                ];
            }
        }
        
        // Agrégation simple: on prend le modèle le plus rapide avec succès
        $successfulResults = array_filter($results, fn($r) => $r['status'] === 'success');
        
        if (empty($successfulResults)) {
            return ['status' => 'all_failed', 'details' => $results];
        }
        
        // Sélection du meilleur résultat (le plus rapide ici)
        usort($successfulResults, fn($a, $b) => $a['response_time'] <=> $b['response_time']);
        $bestResult = reset($successfulResults);
        
        return [
            'status' => 'success',
            'best_model' => array_search($bestResult, $results),
            'analysis' => $bestResult['analysis'],
            'all_results' => $results,
            'consensus' => $this->calculateConsensus($results)
        ];
    }

    /**
     * Calcule un consensus entre les modèles (simple vote sur le sentiment)
     */
    private function calculateConsensus(array $results): array
    {
        $sentiments = ['bullish' => 0, 'bearish' => 0, 'neutral' => 0];
        
        foreach ($results as $model => $result) {
            if ($result['status'] !== 'success') continue;
            
            $text = strtolower($result['analysis']);
            if (strpos($text, 'acheter') !== false || strpos($text, 'bullish') !== false || strpos($text, 'hausse') !== false) {
                $sentiments['bullish']++;
            } elseif (strpos($text, 'vendre') !== false || strpos($text, 'bearish') !== false || strpos($text, 'baisse') !== false) {
                $sentiments['bearish']++;
            } else {
                $sentiments['neutral']++;
            }
        }
        
        $total = array_sum($sentiments);
        if ($total == 0) return ['consensus' => 'unknown'];
        
        $winner = array_keys($sentiments, max($sentiments))[0];
        
        return [
            'consensus' => $winner,
            'votes' => $sentiments,
            'confidence' => round(max($sentiments) / $total * 100, 2) . '%'
        ];
    }
}
