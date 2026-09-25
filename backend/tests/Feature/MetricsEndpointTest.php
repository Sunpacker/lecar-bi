<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        /** @var PrometheusMetricsRegistry $metrics */
        $metrics = $this->app->make(PrometheusMetricsRegistry::class);
        $metrics->reset();
    }

    public function test_metrics_endpoint_returns_prometheus_exposition_format(): void
    {
        // First make a normal API request to generate http metrics
        $this->getJson('/api/v1/health');

        // Scrape /metrics
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $content = $response->getContent();
        $this->assertIsString($content);

        // Verify standard metrics and headers
        $this->assertStringContainsString('# HELP http_requests_total', $content);
        $this->assertStringContainsString('# TYPE http_requests_total counter', $content);
        $this->assertStringContainsString('http_requests_total{', $content);
        $this->assertStringContainsString('route="api/v1/health"', $content);

        // Verify gauges
        $this->assertStringContainsString('# HELP dependency_up', $content);
        $this->assertStringContainsString('dependency_up{', $content);
        $this->assertStringContainsString('dependency="database"', $content);
        $this->assertStringContainsString('dependency="redis"', $content);

        $this->assertStringContainsString('# HELP outbox_backlog_total', $content);
        $this->assertStringContainsString('outbox_backlog_total{', $content);
    }

    public function test_api_v1_metrics_endpoint_also_available(): void
    {
        $response = $this->get('/api/v1/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    public function test_metrics_endpoint_itself_is_not_measured_in_http_requests_total(): void
    {
        $this->get('/metrics');
        $response = $this->get('/metrics');

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('route="metrics"', $content);
    }
}
