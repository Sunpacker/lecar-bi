<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Lightweight Prometheus Metrics Registry.
 *
 * Supports Counters, Gauges, and Histograms with Redis persistence
 * across long-lived and short-lived PHP processes.
 * Falls back to in-memory storage in testing or when Redis is unavailable.
 */
class PrometheusMetricsRegistry
{
    private const string REDIS_PREFIX = 'metrics:analytics:';

    /** @var array<string, float|int> */
    private array $memoryStorage = [];

    /** @var array<string, array{type: string, help: string}> */
    private array $metricMetadata = [];

    /**
     * Standard duration buckets in seconds for HTTP requests.
     *
     * @var array<int, float>
     */
    public const array HTTP_DURATION_BUCKETS = [
        0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0,
    ];

    /**
     * Standard duration buckets in seconds for background jobs.
     *
     * @var array<int, float>
     */
    public const array JOB_DURATION_BUCKETS = [
        0.05, 0.1, 0.5, 1.0, 5.0, 10.0, 30.0, 60.0,
    ];

    public function __construct(
        private readonly string $service = 'analytics',
        private readonly ?string $environment = null,
        private readonly bool $forceMemory = false
    ) {}

    public function registerMetric(string $name, string $type, string $help): void
    {
        $this->metricMetadata[$name] = [
            'type' => $type,
            'help' => $help,
        ];
    }

    /**
     * Increment a counter.
     *
     * @param  array<string, string>  $labels
     */
    public function incrementCounter(string $name, array $labels = [], int $amount = 1): void
    {
        $labels = $this->enrichLabels($labels);
        $metricKey = $this->formatMetricLine($name, $labels);

        if ($this->shouldUseMemory()) {
            $this->memoryStorage[$metricKey] = ($this->memoryStorage[$metricKey] ?? 0) + $amount;

            return;
        }

        try {
            Redis::hincrby(self::REDIS_PREFIX.'counters', $metricKey, $amount);
        } catch (Throwable) {
            $this->memoryStorage[$metricKey] = ($this->memoryStorage[$metricKey] ?? 0) + $amount;
        }
    }

    /**
     * Set a gauge value.
     *
     * @param  array<string, string>  $labels
     */
    public function setGauge(string $name, float|int $value, array $labels = []): void
    {
        $labels = $this->enrichLabels($labels);
        $metricKey = $this->formatMetricLine($name, $labels);

        if ($this->shouldUseMemory()) {
            $this->memoryStorage[$metricKey] = $value;

            return;
        }

        try {
            Redis::hset(self::REDIS_PREFIX.'gauges', $metricKey, (string) $value);
        } catch (Throwable) {
            $this->memoryStorage[$metricKey] = $value;
        }
    }

    /**
     * Observe a value in a histogram.
     *
     * @param  array<string, string>  $labels
     * @param  array<int, float>  $buckets
     */
    public function observeHistogram(
        string $name,
        float $value,
        array $labels = [],
        array $buckets = self::HTTP_DURATION_BUCKETS
    ): void {
        $labels = $this->enrichLabels($labels);

        // 1. Bucket counters
        foreach ($buckets as $bucket) {
            if ($value <= $bucket) {
                $bucketLabels = array_merge($labels, ['le' => (string) $bucket]);
                $this->incrementInternal(self::REDIS_PREFIX.'histograms', $name.'_bucket', $bucketLabels, 1);
            }
        }

        // +Inf bucket
        $infLabels = array_merge($labels, ['le' => '+Inf']);
        $this->incrementInternal(self::REDIS_PREFIX.'histograms', $name.'_bucket', $infLabels, 1);

        // 2. Count
        $this->incrementInternal(self::REDIS_PREFIX.'histograms', $name.'_count', $labels, 1);

        // 3. Sum
        $sumKey = $this->formatMetricLine($name.'_sum', $labels);
        if ($this->shouldUseMemory()) {
            $this->memoryStorage[$sumKey] = ($this->memoryStorage[$sumKey] ?? 0.0) + $value;
        } else {
            try {
                Redis::hincrbyfloat(self::REDIS_PREFIX.'histograms', $sumKey, $value);
            } catch (Throwable) {
                $this->memoryStorage[$sumKey] = ($this->memoryStorage[$sumKey] ?? 0.0) + $value;
            }
        }
    }

    /**
     * Export all metrics in Prometheus text exposition format (version 0.0.4).
     *
     * @param  array<string, float|int>  $dynamicGauges  Key: metric line e.g. "outbox_backlog_total{service="analytics"} 5"
     */
    public function render(array $dynamicGauges = []): string
    {
        $allMetrics = $this->collectAllMetrics();

        // Merge dynamically evaluated gauges
        foreach ($dynamicGauges as $key => $val) {
            $allMetrics[$key] = $val;
        }

        if (empty($allMetrics)) {
            return "# AutoBI Metrics (empty)\n";
        }

        // Group by base metric name
        /** @var array<string, array<string, float|int>> $grouped */
        $grouped = [];
        foreach ($allMetrics as $line => $val) {
            $baseName = $this->extractMetricName($line);
            $grouped[$baseName][$line] = $val;
        }

        $output = '';
        foreach ($grouped as $baseName => $samples) {
            $meta = $this->metricMetadata[$baseName] ?? null;
            if ($meta !== null) {
                $output .= sprintf("# HELP %s %s\n", $baseName, $meta['help']);
                $output .= sprintf("# TYPE %s %s\n", $baseName, $meta['type']);
            }

            foreach ($samples as $metricLine => $value) {
                $formattedValue = is_float($value) ? sprintf('%.6f', $value) : (string) $value;
                if (is_float($value)) {
                    $formattedValue = rtrim(rtrim($formattedValue, '0'), '.');
                    if (! str_contains($formattedValue, '.')) {
                        $formattedValue .= '.0';
                    }
                }
                $output .= sprintf("%s %s\n", $metricLine, $formattedValue);
            }
        }

        return $output;
    }

    /**
     * Clear all recorded metrics (useful for testing).
     */
    public function reset(): void
    {
        $this->memoryStorage = [];

        if (! $this->shouldUseMemory()) {
            try {
                Redis::del(self::REDIS_PREFIX.'counters');
                Redis::del(self::REDIS_PREFIX.'gauges');
                Redis::del(self::REDIS_PREFIX.'histograms');
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }
    }

    /**
     * @return array<string, float|int>
     */
    private function collectAllMetrics(): array
    {
        $result = $this->memoryStorage;

        if (! $this->shouldUseMemory()) {
            try {
                /** @var array<string, string> $counters */
                $counters = Redis::hgetall(self::REDIS_PREFIX.'counters');
                foreach ($counters as $k => $v) {
                    $result[$k] = (int) $v;
                }

                /** @var array<string, string> $gauges */
                $gauges = Redis::hgetall(self::REDIS_PREFIX.'gauges');
                foreach ($gauges as $k => $v) {
                    $result[$k] = str_contains($v, '.') ? (float) $v : (int) $v;
                }

                /** @var array<string, string> $histograms */
                $histograms = Redis::hgetall(self::REDIS_PREFIX.'histograms');
                foreach ($histograms as $k => $v) {
                    $result[$k] = str_contains($v, '.') ? (float) $v : (int) $v;
                }
            } catch (Throwable) {
                // Return memory storage on Redis error
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function incrementInternal(string $hashKey, string $name, array $labels, int $amount): void
    {
        $metricKey = $this->formatMetricLine($name, $labels);

        if ($this->shouldUseMemory()) {
            $this->memoryStorage[$metricKey] = ($this->memoryStorage[$metricKey] ?? 0) + $amount;

            return;
        }

        try {
            Redis::hincrby($hashKey, $metricKey, $amount);
        } catch (Throwable) {
            $this->memoryStorage[$metricKey] = ($this->memoryStorage[$metricKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, string>
     */
    private function enrichLabels(array $labels): array
    {
        $env = $this->environment ?? (string) config('app.env', 'production');

        return array_merge([
            'service' => $this->service,
            'environment' => $env,
        ], $labels);
    }

    /**
     * Format metric line with sorted labels: metric_name{label1="a",label2="b"}.
     *
     * @param  array<string, string>  $labels
     */
    public function formatMetricLine(string $name, array $labels): string
    {
        if (empty($labels)) {
            return $name;
        }

        ksort($labels);
        $pairs = [];
        foreach ($labels as $k => $v) {
            $escaped = str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $v);
            $pairs[] = sprintf('%s="%s"', $k, $escaped);
        }

        return sprintf('%s{%s}', $name, implode(',', $pairs));
    }

    private function extractMetricName(string $line): string
    {
        $bracePos = strpos($line, '{');
        $raw = $bracePos !== false ? substr($line, 0, $bracePos) : $line;

        // For histogram submetrics (_bucket, _count, _sum), group under the root name
        foreach (['_bucket', '_count', '_sum'] as $suffix) {
            if (str_ends_with($raw, $suffix)) {
                return substr($raw, 0, -strlen($suffix));
            }
        }

        return $raw;
    }

    private function shouldUseMemory(): bool
    {
        if ($this->forceMemory) {
            return true;
        }

        return app()->environment('testing');
    }
}
