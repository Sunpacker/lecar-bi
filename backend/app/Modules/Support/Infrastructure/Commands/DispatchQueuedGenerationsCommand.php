<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Commands;

use App\Modules\Support\Application\Contracts\GenerationDispatcherInterface;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use Illuminate\Console\Command;

final class DispatchQueuedGenerationsCommand extends Command
{
    protected $signature = 'support:dispatch-queued {--limit=100}';

    protected $description = 'Dispatch persisted support generations that were not enqueued after commit';

    public function handle(SupportRepositoryInterface $repository, GenerationDispatcherInterface $dispatcher): int
    {
        $ids = $repository->queuedGenerationIds(max(1, (int) $this->option('limit')));
        foreach ($ids as $id) {
            $dispatcher->dispatch($id);
        }
        $this->info('Dispatched '.count($ids).' queued support generations.');

        return self::SUCCESS;
    }
}
