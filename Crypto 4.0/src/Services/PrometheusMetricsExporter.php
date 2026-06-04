<?php

namespace Crypto4\Services;

/**
 * Exporteur de métriques au format Prometheus
 * Permet l'intégration avec Grafana pour le monitoring
 */
class PrometheusMetricsExporter
{
    private array $metrics = [];
    private string $prefix = 'crypto4';

    public function __construct(string $prefix = 'crypto4')
    {
        $this->prefix = $prefix;
    }

    /**
     * Enregistrer un compteur (counter)
     * Valeur qui ne fait qu'augmenter
     */
    public function counter(string $name, float $value, array $labels = []): self
    {
        $metricName = $this->sanitizeName($name);
        $labelStr = $this->formatLabels($labels);
        
        $key = "{$metricName}{$labelStr}";
        
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [
                'type' => 'counter',
                'name' => "{$this->prefix}_{$metricName}",
                'help' => '',
                'value' => 0,
                'labels' => $labels
            ];
        }
        
        $this->metrics[$key]['value'] += $value;
        
        return $this;
    }

    /**
     * Enregistrer un gauge (valeur instantanée)
     * Valeur qui peut monter ou descendre
     */
    public function gauge(string $name, float $value, array $labels = []): self
    {
        $metricName = $this->sanitizeName($name);
        $labelStr = $this->formatLabels($labels);
        
        $key = "{$metricName}{$labelStr}";
        
        $this->metrics[$key] = [
            'type' => 'gauge',
            'name' => "{$this->prefix}_{$metricName}",
            'help' => '',
            'value' => $value,
            'labels' => $labels
        ];
        
        return $this;
    }

    /**
     * Enregistrer un histogramme
     * Pour les temps de réponse, tailles, etc.
     */
    public function histogram(string $name, float $value, array $buckets = [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10], array $labels = []): self
    {
        $metricName = $this->sanitizeName($name);
        
        // Compter dans quel bucket se trouve la valeur
        foreach ($buckets as $bucket) {
            $bucketLabel = array_merge($labels, ['le' => $bucket]);
            $bucketKey = "{$metricName}_bucket" . $this->formatLabels($bucketLabel);
            
            if (!isset($this->metrics[$bucketKey])) {
                $this->metrics[$bucketKey] = [
                    'type' => 'histogram_bucket',
                    'name' => "{$this->prefix}_{$metricName}_bucket",
                    'help' => '',
                    'value' => 0,
                    'labels' => $bucketLabel
                ];
            }
            
            if ($value <= $bucket) {
                $this->metrics[$bucketKey]['value'] += 1;
            }
        }
        
        // Bucket infini
        $infinityLabel = array_merge($labels, ['le' => '+Inf']);
        $infinityKey = "{$metricName}_bucket" . $this->formatLabels($infinityLabel);
        if (!isset($this->metrics[$infinityKey])) {
            $this->metrics[$infinityKey] = [
                'type' => 'histogram_bucket',
                'name' => "{$this->prefix}_{$metricName}_bucket",
                'help' => '',
                'value' => 0,
                'labels' => $infinityLabel
            ];
        }
        $this->metrics[$infinityKey]['value'] += 1;
        
        // Somme
        $sumKey = "{$metricName}_sum" . $this->formatLabels($labels);
        if (!isset($this->metrics[$sumKey])) {
            $this->metrics[$sumKey] = [
                'type' => 'histogram_sum',
                'name' => "{$this->prefix}_{$metricName}_sum",
                'help' => '',
                'value' => 0,
                'labels' => $labels
            ];
        }
        $this->metrics[$sumKey]['value'] += $value;
        
        // Compteur
        $countKey = "{$metricName}_count" . $this->formatLabels($labels);
        if (!isset($this->metrics[$countKey])) {
            $this->metrics[$countKey] = [
                'type' => 'histogram_count',
                'name' => "{$this->prefix}_{$metricName}_count",
                'help' => '',
                'value' => 0,
                'labels' => $labels
            ];
        }
        $this->metrics[$countKey]['value'] += 1;
        
        return $this;
    }

    /**
     * Ajouter une description (help) à une métrique
     */
    public function help(string $name, string $description): self
    {
        $metricName = $this->sanitizeName($name);
        
        foreach ($this->metrics as $key => &$metric) {
            if (strpos($metric['name'], "{$this->prefix}_{$metricName}") === 0) {
                $metric['help'] = $description;
            }
        }
        
        return $this;
    }

    /**
     * Générer le format Prometheus text
     */
    public function render(): string
    {
        $output = [];
        $groupedMetrics = [];
        
        // Grouper par nom de métrique
        foreach ($this->metrics as $metric) {
            $groupName = $metric['name'];
            if (!isset($groupedMetrics[$groupName])) {
                $groupedMetrics[$groupName] = [
                    'type' => $this->getPrometheusType($metric['type']),
                    'help' => $metric['help'],
                    'samples' => []
                ];
            }
            
            $labelStr = $this->formatLabels($metric['labels']);
            $groupedMetrics[$groupName]['samples'][] = [
                'labels' => $labelStr,
                'value' => $metric['value']
            ];
        }
        
        // Formater la sortie
        foreach ($groupedMetrics as $name => $data) {
            if (!empty($data['help'])) {
                $output[] = "# HELP {$name} {$data['help']}";
            }
            $output[] = "# TYPE {$name} {$data['type']}";
            
            foreach ($data['samples'] as $sample) {
                $output[] = "{$name}{$sample['labels']} {$sample['value']}";
            }
        }
        
        return implode("\n", $output) . "\n";
    }

    /**
     * Sauvegarder les métriques dans un fichier
     */
    public function saveToFile(string $filePath): bool
    {
        $content = $this->render();
        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Nettoyer le nom de la métrique
     */
    private function sanitizeName(string $name): string
    {
        // Remplacer les caractères non autorisés par des underscores
        $name = preg_replace('/[^a-zA-Z0-9_:]/', '_', $name);
        // Supprimer les underscores multiples
        $name = preg_replace('/_+/', '_', $name);
        // Commencer par une lettre
        if (!preg_match('/^[a-zA-Z]/', $name)) {
            $name = 'm_' . $name;
        }
        return strtolower($name);
    }

    /**
     * Formater les labels Prometheus
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $formatted = [];
        foreach ($labels as $key => $value) {
            $key = $this->sanitizeName($key);
            $value = addslashes((string)$value);
            $formatted[] = "{$key}=\"{$value}\"";
        }
        
        return '{' . implode(',', $formatted) . '}';
    }

    /**
     * Obtenir le type Prometheus
     */
    private function getPrometheusType(string $internalType): string
    {
        $mapping = [
            'counter' => 'counter',
            'gauge' => 'gauge',
            'histogram' => 'histogram',
            'histogram_bucket' => 'histogram',
            'histogram_sum' => 'histogram',
            'histogram_count' => 'histogram'
        ];
        
        return $mapping[$internalType] ?? 'untyped';
    }

    /**
     * Réinitialiser toutes les métriques
     */
    public function reset(): self
    {
        $this->metrics = [];
        return $this;
    }

    /**
     * Obtenir le nombre de métriques enregistrées
     */
    public function count(): int
    {
        return count($this->metrics);
    }
}
