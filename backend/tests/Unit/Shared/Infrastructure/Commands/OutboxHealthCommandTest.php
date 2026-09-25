<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Commands;

use App\Shared\Infrastructure\Health\OutboxHealthService;
use Tests\TestCase;

final class OutboxHealthCommandTest extends TestCase
{
    public function test_command_outputs_health_signals_and_succeeds_when_healthy(): void
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

        $this->artisan('outbox:health')
            ->expectsOutputToContain('Outbox Status: ok')
            ->expectsOutputToContain('Pending Messages: 0')
            ->assertSuccessful();
    }

    public function test_command_fails_when_status_is_degraded(): void
    {
        $mockService = $this->createMock(OutboxHealthService::class);
        $mockService->expects(self::once())
            ->method('checkHealth')
            ->willReturn([
                'status' => 'degraded',
                'service' => 'analytics',
                'outbox' => [
                    'pending_count' => 15,
                    'oldest_pending_age_seconds' => 450,
                    'failed_count' => 2,
                ],
                'publisher' => [
                    'last_run_at' => null,
                    'last_run_age_seconds' => null,
                ],
            ]);

        $this->app->instance(OutboxHealthService::class, $mockService);

        $this->artisan('outbox:health')
            ->expectsOutputToContain('Outbox Status: degraded')
            ->expectsOutputToContain('Failed Messages: 2')
            ->assertFailed();
    }

    public function test_command_outputs_json_format_when_requested(): void
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
                    'last_run_at' => null,
                    'last_run_age_seconds' => null,
                ],
            ]);

        $this->app->instance(OutboxHealthService::class, $mockService);

        $this->artisan('outbox:health', ['--json' => true])
            ->assertSuccessful();
    }
}
