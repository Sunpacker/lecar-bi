<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Commands;

use App\Shared\Application\Ports\OutboxRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Resets a 'failed' outbox message back to 'pending' for retry.
 * Use this when a message has exhausted its automatic retry attempts
 * and you want to manually re-queue it after fixing the underlying issue.
 */
final class OutboxRetryCommand extends Command
{
    protected $signature = 'outbox:retry {eventId : The event_id of the failed outbox message}';

    protected $description = 'Reset a failed outbox message back to pending for retry.';

    public function handle(OutboxRepositoryInterface $outboxRepository): int
    {
        $eventId = (string) $this->argument('eventId');

        if ($eventId === '') {
            $this->error('event_id must not be empty.');

            return self::FAILURE;
        }

        $outboxRepository->resetForRetry($eventId);
        $this->info("Outbox message '{$eventId}' has been reset to pending.");

        return self::SUCCESS;
    }
}
