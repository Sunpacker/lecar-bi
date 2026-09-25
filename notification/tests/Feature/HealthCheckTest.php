<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;
use NotificationService\Tests\TestCase;
use PDO;
use RuntimeException;

final class HealthCheckTest extends TestCase
{
    public function test_live_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'notification',
        ]);
    }

    public function test_ready_endpoint_returns_ok_when_dependencies_healthy(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturn(Mockery::mock(PDO::class));

        Redis::shouldReceive('connection')
            ->with('integration_events')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->once()
            ->andReturn('PONG');

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'notification',
            'checks' => [
                'database' => 'ok',
                'redis' => 'ok',
            ],
        ]);
    }

    public function test_ready_endpoint_returns_503_when_database_fails(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andThrow(new RuntimeException('DB connection failed'));

        Redis::shouldReceive('connection')
            ->with('integration_events')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->once()
            ->andReturn('PONG');

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'degraded',
            'service' => 'notification',
            'checks' => [
                'database' => 'error',
                'redis' => 'ok',
            ],
        ]);
    }

    public function test_ready_endpoint_returns_503_when_redis_fails(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturn(Mockery::mock(PDO::class));

        Redis::shouldReceive('connection')
            ->with('integration_events')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->once()
            ->andThrow(new RuntimeException('Redis unreachable'));

        $response = $this->getJson('/api/v1/health/ready');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'degraded',
            'service' => 'notification',
            'checks' => [
                'database' => 'ok',
                'redis' => 'error',
            ],
        ]);
    }

    public function test_health_endpoint_returns_ok_when_dependencies_healthy(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturn(Mockery::mock(PDO::class));

        Redis::shouldReceive('connection')
            ->with('integration_events')
            ->once()
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->once()
            ->andReturn('PONG');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'notification',
            'version' => 'v1',
            'checks' => [
                'database' => 'ok',
                'redis' => 'ok',
            ],
        ]);
    }

    public function test_worker_endpoint_returns_ok_when_worker_is_healthy(): void
    {
        $mock = $this->createMock(WorkerHeartbeatService::class);
        $mock->method('getWorkerStatus')->willReturn([
            'status' => 'ok',
            'service' => 'notification',
            'worker' => [
                'worker_id' => 'notification-worker-1',
                'heartbeat_age_seconds' => 4,
                'last_processed_at' => '2026-09-25T09:00:00Z',
                'last_processed_age_seconds' => 10,
                'processed_count' => 10,
                'failed_count' => 0,
                'status' => 'running',
            ],
            'stream' => [
                'pending_messages' => 0,
                'dead_letter_count' => 0,
            ],
        ]);
        $this->app->instance(WorkerHeartbeatService::class, $mock);

        $response = $this->getJson('/api/v1/health/worker');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'notification',
        ]);
    }

    public function test_worker_endpoint_returns_503_when_worker_is_down(): void
    {
        $mock = $this->createMock(WorkerHeartbeatService::class);
        $mock->method('getWorkerStatus')->willReturn([
            'status' => 'down',
            'service' => 'notification',
            'worker' => [
                'worker_id' => null,
                'heartbeat_age_seconds' => null,
                'last_processed_at' => null,
                'last_processed_age_seconds' => null,
                'processed_count' => 0,
                'failed_count' => 0,
                'status' => null,
            ],
            'stream' => [
                'pending_messages' => 5,
                'dead_letter_count' => 0,
            ],
        ]);
        $this->app->instance(WorkerHeartbeatService::class, $mock);

        $response = $this->getJson('/api/v1/health/worker');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'down',
            'service' => 'notification',
        ]);
    }
}
