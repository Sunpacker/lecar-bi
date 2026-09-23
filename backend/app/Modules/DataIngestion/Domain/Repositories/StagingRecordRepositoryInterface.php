<?php

namespace App\Modules\DataIngestion\Domain\Repositories;

interface StagingRecordRepositoryInterface
{
    /** @param array<string, string> $row */
    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void;

    /** @param array<string, string> $row */
    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void;

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void;

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void;

    public function deleteByBatchId(string $batchId, string $workspaceId): void;
}
