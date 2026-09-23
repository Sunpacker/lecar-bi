<?php

namespace App\Modules\DataIngestion\Domain\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatchId;

interface ImportFailureRepositoryInterface
{
    /**
     * @param \App\Modules\DataIngestion\Domain\RowError[] $rowErrors
     */
    public function recordFailures(ImportBatchId $batchId, string $workspaceId, array $rowErrors): void;

    /**
     * @return \App\Modules\DataIngestion\Domain\RowError[]
     */
    public function listByBatchId(ImportBatchId $batchId, string $workspaceId, int $page, int $perPage): array;
}
