<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Commands;

use App\Shared\Infrastructure\Jobs\PublishOutboxMessagesJob;
use Illuminate\Console\Command;

/**
 * Manually trigger the outbox publisher for a single batch.
 * Useful for operational tasks, debugging, and CI smoke tests.
 */
final class OutboxPublishCommand extends Command
{
    protected $signature = 'outbox:publish';

    protected $description = 'Publish a batch of pending outbox messages to the integration event transport.';

    public function handle(): int
    {
        $this->info('Dispatching outbox publish job...');
        PublishOutboxMessagesJob::dispatchSync();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
