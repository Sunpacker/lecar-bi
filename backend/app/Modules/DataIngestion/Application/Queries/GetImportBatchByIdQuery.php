<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportBatchByIdQuery
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
    ) {}
}
