<?php

namespace App\Modules\DataIngestion\Infrastructure\Jobs;

use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;

final class QueueImportJobDispatcher implements ImportJobDispatcherInterface
{
    public function dispatch(string $batchId, string $workspaceId): void
    {
        ProcessImportJob::dispatch($batchId, $workspaceId);
    }
}
