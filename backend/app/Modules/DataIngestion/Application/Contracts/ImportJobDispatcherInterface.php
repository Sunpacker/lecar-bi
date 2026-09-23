<?php

namespace App\Modules\DataIngestion\Application\Contracts;

interface ImportJobDispatcherInterface
{
    public function dispatch(string $batchId, string $workspaceId): void;
}
