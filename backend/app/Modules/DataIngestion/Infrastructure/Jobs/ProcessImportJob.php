<?php

namespace App\Modules\DataIngestion\Infrastructure\Jobs;

use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\ProcessImportBatchHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $batchId,
        public readonly string $workspaceId,
    ) {}

    public function handle(ProcessImportBatchHandler $handler): void
    {
        $handler->handle(new ProcessImportBatchCommand($this->batchId, $this->workspaceId));
    }
}
