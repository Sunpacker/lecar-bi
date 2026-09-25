<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Metrics;

use App\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use Tests\TestCase;

class PrometheusMetricsRegistryTest extends TestCase
{
    private PrometheusMetricsRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PrometheusMetricsRegistry(
            service: 'analytics',
            environment: 'testing',
            forceMemory: true
        );
    }

    public function test_increments_counter_and_renders_prometheus_format(): void
    {
        $this->registry->registerMetric('http_requests_total', 'counter', 'Total HTTP requests');

        $this->registry->incrementCounter('http_requests_total', [
            'method' => 'GET',
            'route' => 'api/v1/health',
            'status_code' => '200',
        ]);
        $this->registry->incrementCounter('http_requests_total', [
            'method' => 'GET',
            'route' => 'api/v1/health',
            'status_code' => '200',
        ], 2);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP http_requests_total Total HTTP requests', $output);
        $this->assertStringContainsString('# TYPE http_requests_total counter', $output);
        $this->assertStringContainsString('http_requests_total{environment="testing",method="GET",route="api/v1/health",service="analytics",status_code="200"} 3', $output);
    }

    public function test_sets_gauge_value(): void
    {
        $this->registry->registerMetric('outbox_backlog_total', 'gauge', 'Pending outbox messages');
        $this->registry->setGauge('outbox_backlog_total', 42);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP outbox_backlog_total Pending outbox messages', $output);
        $this->assertStringContainsString('# TYPE outbox_backlog_total gauge', $output);
        $this->assertStringContainsString('outbox_backlog_total{environment="testing",service="analytics"} 42', $output);
    }

    public function test_observes_histogram_and_renders_buckets_count_sum(): void
    {
        $this->registry->registerMetric('http_request_duration_seconds', 'histogram', 'HTTP request duration');

        $this->registry->observeHistogram('http_request_duration_seconds', 0.05, [
            'method' => 'GET',
            'route' => 'api/v1/sales/overview',
        ], [0.01, 0.05, 0.1]);

        $output = $this->registry->render();

        $this->assertStringContainsString('# HELP http_request_duration_seconds HTTP request duration', $output);
        $this->assertStringContainsString('# TYPE http_request_duration_seconds histogram', $output);
        $this->assertStringContainsString('http_request_duration_seconds_bucket{environment="testing",le="0.05",method="GET",route="api/v1/sales/overview",service="analytics"} 1', $output);
        $this->assertStringContainsString('http_request_duration_seconds_bucket{environment="testing",le="+Inf",method="GET",route="api/v1/sales/overview",service="analytics"} 1', $output);
        $this->assertStringContainsString('http_request_duration_seconds_count{environment="testing",method="GET",route="api/v1/sales/overview",service="analytics"} 1', $output);
        $this->assertStringContainsString('http_request_duration_seconds_sum{environment="testing",method="GET",route="api/v1/sales/overview",service="analytics"} 0.05', $output);
    }

    public function test_resets_storage(): void
    {
        $this->registry->incrementCounter('test_counter');
        $this->registry->reset();

        $output = $this->registry->render();
        $this->assertSame("# AutoBI Metrics (empty)\n", $output);
    }

    public function test_merges_dynamic_gauges_on_render(): void
    {
        $dynamic = [
            'dependency_up{dependency="database",environment="testing",service="analytics"}' => 1,
            'dependency_up{dependency="redis",environment="testing",service="analytics"}' => 0,
        ];

        $output = $this->registry->render($dynamic);

        $this->assertStringContainsString('dependency_up{dependency="database",environment="testing",service="analytics"} 1', $output);
        $this->assertStringContainsString('dependency_up{dependency="redis",environment="testing",service="analytics"} 0', $output);
    }
}
