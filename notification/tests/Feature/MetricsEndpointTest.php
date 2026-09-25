<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use NotificationService\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use NotificationService\Tests\TestCase;

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
        $this->getJson('/api/v1/health');

        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

        $content = (string) $response->getContent();

        $this->assertStringContainsString('# HELP http_requests_total', $content);
        $this->assertStringContainsString('service="notification"', $content);
        $this->assertStringContainsString('# HELP worker_up', $content);
        $this->assertStringContainsString('worker_up{', $content);
        $this->assertStringContainsString('# HELP dependency_up', $content);
        $this->assertStringContainsString('dependency_up{', $content);
    }

    public function test_api_v1_metrics_endpoint_available(): void
    {
        $response = $this->get('/api/v1/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
