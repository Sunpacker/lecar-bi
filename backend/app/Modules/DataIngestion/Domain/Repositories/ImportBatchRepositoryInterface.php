<?php

namespace App\Modules\DataIngestion\Domain\Repositories;

use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;

interface ImportBatchRepositoryInterface
{
    public function findById(ImportBatchId $id, string $workspaceId): ?ImportBatch;

    public function save(ImportBatch $batch): void;

    /**
     * @return ImportBatch[]
     */
    public function listByWorkspace(string $workspaceId, ?ImportStatus $status, int $page, int $perPage): array;
}
