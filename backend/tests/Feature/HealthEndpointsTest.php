<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;
use App\Shared\Infrastructure\Health\OutboxHealthService;
use Tests\TestCase;

final class HealthEndpointsTest extends TestCase
{
    public function test_liveness_endpoint_returns_200_and_does_not_require_database_or_redis(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'analytics',
            ]);
    }

    public function test_readiness_endpoint_returns_200_when_dependencies_are_healthy(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'analytics',
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                ],
            ]);
    }

    public function test_readiness_endpoint_returns_503_when_database_fails(): void
    {
        $mockChecker = $this->createMock(DependencyHealthCheckerInterface::class);
        $mockChecker->expects(self::once())
            ->method('check')
            ->willReturn([
                'database' => 'error',
                'redis' => 'ok',
            ]);
        $this->app->instance(DependencyHealthCheckerInterface::class, $mockChecker);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'service' => 'analytics',
                'checks' => [
                    'database' => 'error',
                    'redis' => 'ok',
                ],
            ]);
    }

    public function test_readiness_endpoint_returns_503_when_redis_fails(): void
    {
        $mockChecker = $this->createMock(DependencyHealthCheckerInterface::class);
        $mockChecker->expects(self::once())
            ->method('check')
            ->willReturn([
                'database' => 'ok',
                'redis' => 'error',
            ]);
        $this->app->instance(DependencyHealthCheckerInterface::class, $mockChecker);

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'service' => 'analytics',
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'error',
                ],
            ]);
    }

    public function test_legacy_health_endpoint_conforms_to_contract_and_includes_version(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'analytics',
                'version' => 'v1',
                'checks' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                ],
            ]);
    }

    public function test_legacy_health_endpoint_returns_503_when_dependency_fails(): void
    {
        $mockChecker = $this->createMock(DependencyHealthCheckerInterface::class);
        $mockChecker->expects(self::once())
            ->method('check')
            ->willReturn([
                'database' => 'error',
                'redis' => 'ok',
            ]);
        $this->app->instance(DependencyHealthCheckerInterface::class, $mockChecker);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'service' => 'analytics',
                'version' => 'v1',
                'checks' => [
                    'database' => 'error',
                    'redis' => 'ok',
                ],
            ]);
    }

    public function test_outbox_health_endpoint_returns_metrics_and_status(): void
    {
        $mockService = $this->createMock(OutboxHealthService::class);
        $mockService->expects(self::once())
            ->method('checkHealth')
            ->willReturn([
                'status' => 'ok',
                'service' => 'analytics',
                'outbox' => [
                    'pending_count' => 0,
                    'oldest_pending_age_seconds' => null,
                    'failed_count' => 0,
                ],
                'publisher' => [
                    'last_run_at' => '2026-09-25T09:00:00Z',
                    'last_run_age_seconds' => 10,
                ],
            ]);

        $this->app->instance(OutboxHealthService::class, $mockService);

        $response = $this->getJson('/api/v1/health/outbox');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'analytics',
                'outbox' => [
                    'pending_count' => 0,
                    'oldest_pending_age_seconds' => null,
                    'failed_count' => 0,
                ],
                'publisher' => [
                    'last_run_at' => '2026-09-25T09:00:00Z',
                    'last_run_age_seconds' => 10,
                ],
            ]);
    }
}
