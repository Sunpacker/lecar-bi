<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Shared\Infrastructure\Metrics;

use NotificationService\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use NotificationService\Tests\TestCase;

class PrometheusMetricsRegistryTest extends TestCase
{
    private PrometheusMetricsRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PrometheusMetricsRegistry(
            service: 'notification',
            environment: 'testing',
            forceMemory: true
        );
    }

    public function test_increments_counter_and_renders_prometheus_format(): void
    {
        $this->registry->registerMetric('events_consumed_total', 'counter', 'Total consumed events');

        $this->registry->incrementCounter('events_consumed_total', [
            'event_type' => 'alert.triggered.v1',
            'status' => 'success',
        ]);
        $this->registry->incrementCounter('events_consumed_total', [
            'event_type' => 'alert.triggered.v1',
            'status' => 'success',
        ], 2);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP events_consumed_total Total consumed events', $output);
        $this->assertStringContainsString('# TYPE events_consumed_total counter', $output);
        $this->assertStringContainsString('events_consumed_total{environment="testing",event_type="alert.triggered.v1",service="notification",status="success"} 3', $output);
    }

    public function test_sets_gauge_value(): void
    {
        $this->registry->registerMetric('worker_up', 'gauge', 'Worker operational status');
        $this->registry->setGauge('worker_up', 1);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP worker_up Worker operational status', $output);
        $this->assertStringContainsString('# TYPE worker_up gauge', $output);
        $this->assertStringContainsString('worker_up{environment="testing",service="notification"} 1', $output);
    }

    public function test_observes_histogram(): void
    {
        $this->registry->registerMetric('http_request_duration_seconds', 'histogram', 'HTTP request duration');

        $this->registry->observeHistogram('http_request_duration_seconds', 0.02, [
            'method' => 'GET',
            'route' => 'api/v1/health',
        ], [0.01, 0.05, 0.1]);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP http_request_duration_seconds HTTP request duration', $output);
        $this->assertStringContainsString('# TYPE http_request_duration_seconds histogram', $output);
        $this->assertStringContainsString('http_request_duration_seconds_bucket{environment="testing",le="0.05",method="GET",route="api/v1/health",service="notification"} 1', $output);
        $this->assertStringContainsString('http_request_duration_seconds_count{environment="testing",method="GET",route="api/v1/health",service="notification"} 1', $output);
    }

    public function test_merges_dynamic_gauges(): void
    {
        $dynamic = [
            'dependency_up{dependency="database",environment="testing",service="notification"}' => 1,
            'dependency_up{dependency="redis",environment="testing",service="notification"}' => 1,
        ];

        $output = $this->registry->render($dynamic);

        $this->assertStringContainsString('dependency_up{dependency="database",environment="testing",service="notification"} 1', $output);
        $this->assertStringContainsString('dependency_up{dependency="redis",environment="testing",service="notification"} 1', $output);
    }
}
