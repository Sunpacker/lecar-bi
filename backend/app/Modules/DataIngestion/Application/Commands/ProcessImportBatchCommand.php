<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class ProcessImportBatchCommand
{
    public function __construct(
        public string $batchId,
        public string $workspaceId,
    ) {}
}
