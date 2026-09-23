<?php

namespace App\Modules\DataIngestion\Domain\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\RowError;

interface ImportFailureRepositoryInterface
{
    /**
     * @param  RowError[]  $rowErrors
     */
    public function recordFailures(ImportBatchId $batchId, string $workspaceId, array $rowErrors): void;

    /**
     * @return RowError[]
     */
    public function listByBatchId(ImportBatchId $batchId, string $workspaceId, int $page, int $perPage): array;

    public function countByBatchId(ImportBatchId $batchId, string $workspaceId): int;

    public function deleteByBatchId(ImportBatchId $batchId, string $workspaceId): void;
}
