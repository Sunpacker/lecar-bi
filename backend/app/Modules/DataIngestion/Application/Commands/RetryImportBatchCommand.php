<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class RetryImportBatchCommand
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
    ) {}
}
