<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use NotificationService\Integration\Infrastructure\Redis\RedisStreamClientInterface;
use NotificationService\Tests\TestCase;

final class ConsumeNotificationsCommandTest extends TestCase
{
    public function test_artisan_consume_runs_single_cycle_with_once_flag(): void
    {
        $fakeClient = new class implements RedisStreamClientInterface
        {
            public bool $groupEnsured = false;

            public function ensureGroup(string $stream, string $group): void
            {
                $this->groupEnsured = true;
            }

            public function claimStale(string $stream, string $group, string $consumer, int $minIdleMs, int $count): array
            {
                return [];
            }

            public function readGroup(string $stream, string $group, string $consumer, int $count, int $blockTimeoutMs): array
            {
                return [];
            }

            public function ack(string $stream, string $group, string $messageId): void {}

            public function publish(string $stream, array $data): string
            {
                return '1-0';
            }
        };

        $this->app->instance(RedisStreamClientInterface::class, $fakeClient);

        $this->artisan('notifications:consume', ['--once' => true])
            ->expectsOutputToContain('Initializing notification stream consumer...')
            ->expectsOutputToContain('Consumer group verified. Listening for integration events...')
            ->expectsOutputToContain('Cycle finished. Processed 0 message(s).')
            ->assertExitCode(0);

        $this->assertTrue($fakeClient->groupEnsured);
    }
}
