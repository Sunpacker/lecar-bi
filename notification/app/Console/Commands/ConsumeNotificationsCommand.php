<?php

declare(strict_types=1);

namespace NotificationService\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamConsumer;
use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;

final class ConsumeNotificationsCommand extends Command
{
    protected $signature = 'notifications:consume {--once : Run a single consumption cycle and exit}';

    protected $description = 'Consume integration events from Redis Stream and project notifications';

    private bool $shouldStop = false;

    public function handle(RedisStreamConsumer $consumer, WorkerHeartbeatService $heartbeatService): int
    {
        $this->setupSignalHandlers();

        $this->info('Initializing notification stream consumer...');
        $consumer->init();
        $this->info('Consumer group verified. Listening for integration events...');

        $runOnce = (bool) $this->option('once');
        $processedTotal = 0;
        $lastProcessedAt = null;

        $heartbeatService->recordHeartbeat('notification-worker', null, 0, 0, 'running');

        while (! $this->shouldStop) {
            $processed = $consumer->consumeCycle();
            if ($processed > 0) {
                $processedTotal += $processed;
                $lastProcessedAt = (new DateTimeImmutable)->format(DATE_ATOM);
            }

            $heartbeatService->recordHeartbeat('notification-worker', $lastProcessedAt, $processedTotal, 0, 'running');

            if ($runOnce) {
                $this->info("Cycle finished. Processed {$processed} message(s).");
                break;
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Short pause if no messages were processed to prevent CPU spin
            if ($processed === 0) {
                usleep(100000); // 100ms
            }
        }

        $heartbeatService->recordHeartbeat('notification-worker', $lastProcessedAt, $processedTotal, 0, 'stopped');
        $this->info('Graceful shutdown completed.');

        return 0;
    }

    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function (): void {
                $this->info('SIGTERM received, finishing current message and shutting down...');
                $this->shouldStop = true;
            });

            pcntl_signal(SIGQUIT, function (): void {
                $this->info('SIGQUIT received, finishing current message and shutting down...');
                $this->shouldStop = true;
            });

            pcntl_signal(SIGINT, function (): void {
                $this->info('SIGINT received, finishing current message and shutting down...');
                $this->shouldStop = true;
            });
        }
    }
}
