<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Console\Commands;

use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;
use NotificationService\Tests\TestCase;

final class WorkerHealthCommandTest extends TestCase
{
    public function test_command_succeeds_when_worker_is_healthy(): void
    {
        $mock = $this->createMock(WorkerHeartbeatService::class);
        $mock->expects(self::once())
            ->method('getWorkerStatus')
            ->willReturn([
                'status' => 'ok',
                'service' => 'notification',
                'worker' => [
                    'worker_id' => 'notification-worker-1',
                    'heartbeat_age_seconds' => 5,
                    'last_processed_at' => '2026-09-25T09:00:00Z',
                    'last_processed_age_seconds' => 15,
                    'processed_count' => 50,
                    'failed_count' => 0,
                    'status' => 'running',
                ],
                'stream' => [
                    'pending_messages' => 0,
                    'dead_letter_count' => 0,
                ],
            ]);

        $this->app->instance(WorkerHeartbeatService::class, $mock);

        $this->artisan('notifications:worker-health')
            ->expectsOutputToContain('Worker Status: ok')
            ->expectsOutputToContain('Worker ID: notification-worker-1')
            ->assertSuccessful();
    }

    public function test_command_fails_when_worker_is_down(): void
    {
        $mock = $this->createMock(WorkerHeartbeatService::class);
        $mock->expects(self::once())
            ->method('getWorkerStatus')
            ->willReturn([
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
                    'pending_messages' => 0,
                    'dead_letter_count' => 0,
                ],
            ]);

        $this->app->instance(WorkerHeartbeatService::class, $mock);

        $this->artisan('notifications:worker-health')
            ->expectsOutputToContain('Worker Status: down')
            ->assertFailed();
    }

    public function test_command_outputs_json_when_requested(): void
    {
        $mock = $this->createMock(WorkerHeartbeatService::class);
        $mock->expects(self::once())
            ->method('getWorkerStatus')
            ->willReturn([
                'status' => 'ok',
                'service' => 'notification',
                'worker' => [
                    'worker_id' => 'notification-worker-1',
                    'heartbeat_age_seconds' => 2,
                    'last_processed_at' => null,
                    'last_processed_age_seconds' => null,
                    'processed_count' => 0,
                    'failed_count' => 0,
                    'status' => 'running',
                ],
                'stream' => [
                    'pending_messages' => 0,
                    'dead_letter_count' => 0,
                ],
            ]);

        $this->app->instance(WorkerHeartbeatService::class, $mock);

        $this->artisan('notifications:worker-health', ['--json' => true])
            ->assertSuccessful();
    }
}
