<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Jobs;

use App\Modules\Support\Application\Contracts\GenerationDispatcherInterface;

final readonly class QueueGenerationDispatcher implements GenerationDispatcherInterface
{
    public function __construct(private string $queue) {}

    public function dispatch(string $generationId): void
    {
        ProcessSupportGenerationJob::dispatch($generationId)->onQueue($this->queue)->afterCommit();
    }
}
