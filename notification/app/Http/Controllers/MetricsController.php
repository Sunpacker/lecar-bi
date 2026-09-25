<?php

declare(strict_types=1);

namespace NotificationService\Http\Controllers;

use Illuminate\Http\Response;
use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;
use NotificationService\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;
use NotificationService\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;

class MetricsController
{
    public function __construct(
        private readonly PrometheusMetricsRegistry $metrics,
        private readonly WorkerHeartbeatService $workerService,
        private readonly DependencyHealthCheckerInterface $dependencyChecker
    ) {}

    public function __invoke(): Response
    {
        $this->registerMetadata();

        $dynamicGauges = [];
        $env = (string) config('app.env', 'production');

        // 1. Dependency health gauges
        try {
            $deps = $this->dependencyChecker->check();
            foreach ($deps as $depName => $depStatus) {
                $line = $this->metrics->formatMetricLine('dependency_up', [
                    'service' => 'notification',
                    'environment' => $env,
                    'dependency' => $depName,
                ]);
                $dynamicGauges[$line] = ($depStatus === 'ok') ? 1 : 0;
            }
        } catch (\Throwable) {
            // Fail safe on dependency error
        }

        // 2. Worker & stream gauges
        try {
            $worker = $this->workerService->getWorkerStatus();

            $lineWorkerUp = $this->metrics->formatMetricLine('worker_up', [
                'service' => 'notification',
                'environment' => $env,
            ]);
            $dynamicGauges[$lineWorkerUp] = ($worker['status'] === 'ok') ? 1 : 0;

            $lineHeartbeat = $this->metrics->formatMetricLine('worker_heartbeat_timestamp_seconds', [
                'service' => 'notification',
                'environment' => $env,
            ]);
            $heartbeatAge = $worker['worker']['heartbeat_age_seconds'];
            $heartbeatTs = $heartbeatAge !== null ? max(0, time() - $heartbeatAge) : 0;
            $dynamicGauges[$lineHeartbeat] = $heartbeatTs;

            $linePending = $this->metrics->formatMetricLine('integration_stream_pending_total', [
                'service' => 'notification',
                'environment' => $env,
            ]);
            $dynamicGauges[$linePending] = $worker['stream']['pending_messages'];

            $lineDeadLetter = $this->metrics->formatMetricLine('integration_dead_letter_total', [
                'service' => 'notification',
                'environment' => $env,
            ]);
            $dynamicGauges[$lineDeadLetter] = $worker['stream']['dead_letter_count'];
        } catch (\Throwable) {
            // Fail safe on worker status error
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
        $this->metrics->registerMetric('worker_up', 'gauge', 'Notification worker operational status (1 = healthy, 0 = missing heartbeat)');
        $this->metrics->registerMetric('worker_heartbeat_timestamp_seconds', 'gauge', 'Unix timestamp of the latest notification worker heartbeat');
        $this->metrics->registerMetric('integration_stream_pending_total', 'gauge', 'Number of pending messages in the integration events stream consumer group');
        $this->metrics->registerMetric('integration_dead_letter_total', 'gauge', 'Number of unhandled messages in the dead letter stream');
        $this->metrics->registerMetric('events_consumed_total', 'counter', 'Total number of integration events processed by worker');
        $this->metrics->registerMetric('dependency_up', 'gauge', 'Local dependency operational status (1 = healthy, 0 = degraded)');
    }
}
