<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Controllers;

use App\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;
use App\Shared\Infrastructure\Health\OutboxHealthService;
use App\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use DateTimeImmutable;
use Illuminate\Http\Response;

class MetricsController
{
    public function __construct(
        private readonly PrometheusMetricsRegistry $metrics,
        private readonly OutboxHealthService $outboxHealth,
        private readonly DependencyHealthCheckerInterface $dependencyChecker
    ) {}

    public function __invoke(): Response
    {
        $this->registerMetadata();

        $dynamicGauges = [];

        // 1. Dependency health gauges
        try {
            $deps = $this->dependencyChecker->check();
            foreach ($deps as $depName => $depStatus) {
                $line = $this->metrics->formatMetricLine('dependency_up', [
                    'service' => 'analytics',
                    'environment' => (string) config('app.env', 'production'),
                    'dependency' => $depName,
                ]);
                $dynamicGauges[$line] = ($depStatus === 'ok') ? 1 : 0;
            }
        } catch (\Throwable) {
            // Fail safe on dependency check failure
        }

        // 2. Outbox metrics
        try {
            $health = $this->outboxHealth->checkHealth();
            $env = (string) config('app.env', 'production');
            $outbox = $health['outbox'];
            $publisher = $health['publisher'];

            $lineBacklog = $this->metrics->formatMetricLine('outbox_backlog_total', [
                'service' => 'analytics',
                'environment' => $env,
            ]);
            $dynamicGauges[$lineBacklog] = $outbox['pending_count'];

            $lineOldest = $this->metrics->formatMetricLine('outbox_oldest_message_age_seconds', [
                'service' => 'analytics',
                'environment' => $env,
            ]);
            $dynamicGauges[$lineOldest] = $outbox['oldest_pending_age_seconds'] ?? 0;

            $lineFailed = $this->metrics->formatMetricLine('outbox_failed_total', [
                'service' => 'analytics',
                'environment' => $env,
            ]);
            $dynamicGauges[$lineFailed] = $outbox['failed_count'];

            $lineHeartbeat = $this->metrics->formatMetricLine('outbox_publisher_heartbeat_timestamp_seconds', [
                'service' => 'analytics',
                'environment' => $env,
            ]);
            $heartbeatTs = 0;
            if (isset($publisher['last_run_at'])) {
                $dt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $publisher['last_run_at']);
                if ($dt !== false) {
                    $heartbeatTs = $dt->getTimestamp();
                }
            }
            $dynamicGauges[$lineHeartbeat] = $heartbeatTs;
        } catch (\Throwable) {
            // Fail safe on outbox check failure
        }

        $rendered = $this->metrics->render($dynamicGauges);

        return response($rendered, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function registerMetadata(): void
    {
        $this->metrics->registerMetric('http_requests_total', 'counter', 'Total number of HTTP requests processed');
        $this->metrics->registerMetric('http_request_duration_seconds', 'histogram', 'HTTP request duration in seconds');
        $this->metrics->registerMetric('jobs_total', 'counter', 'Total number of background jobs processed');
        $this->metrics->registerMetric('job_duration_seconds', 'histogram', 'Background job execution duration in seconds');
        $this->metrics->registerMetric('outbox_backlog_total', 'gauge', 'Total number of pending outbox messages');
        $this->metrics->registerMetric('outbox_oldest_message_age_seconds', 'gauge', 'Age in seconds of the oldest pending outbox message');
        $this->metrics->registerMetric('outbox_failed_total', 'gauge', 'Total number of failed outbox messages');
        $this->metrics->registerMetric('outbox_publisher_heartbeat_timestamp_seconds', 'gauge', 'Unix timestamp of the latest outbox publisher heartbeat');
        $this->metrics->registerMetric('dependency_up', 'gauge', 'Local dependency operational status (1 = healthy, 0 = degraded)');
    }
}
