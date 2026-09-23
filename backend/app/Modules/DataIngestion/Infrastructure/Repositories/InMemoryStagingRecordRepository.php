<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;

final class InMemoryStagingRecordRepository implements StagingRecordRepositoryInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $salesRows = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $inventoryRows = [];

    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        $this->salesRows[$batchId][$rowNumber] = array_merge($row, [
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'status' => $status,
        ]);
    }

    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        $this->inventoryRows[$batchId][$rowNumber] = array_merge($row, [
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'status' => $status,
        ]);
    }

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        if (isset($this->salesRows[$batchId][$rowNumber])) {
            $this->salesRows[$batchId][$rowNumber]['status'] = $status;
        }
    }

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        if (isset($this->inventoryRows[$batchId][$rowNumber])) {
            $this->inventoryRows[$batchId][$rowNumber]['status'] = $status;
        }
    }

    public function countSalesRows(string $batchId): int
    {
        return count($this->salesRows[$batchId] ?? []);
    }

    public function countInventoryRows(string $batchId): int
    {
        return count($this->inventoryRows[$batchId] ?? []);
    }

    public function getSalesRowStatus(string $batchId, int $rowNumber): ?string
    {
        return $this->salesRows[$batchId][$rowNumber]['status'] ?? null;
    }

    public function deleteByBatchId(string $batchId, string $workspaceId): void
    {
        unset($this->salesRows[$batchId], $this->inventoryRows[$batchId]);
    }
}
