<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
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
}
