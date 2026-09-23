<?php

namespace App\Modules\DataIngestion\Infrastructure\Repositories;

use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;
use App\Modules\DataIngestion\Infrastructure\Models\StagingInventoryRecordModel;
use App\Modules\DataIngestion\Infrastructure\Models\StagingSalesRecordModel;

final class EloquentStagingRecordRepository implements StagingRecordRepositoryInterface
{
    public function recordSalesRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        StagingSalesRecordModel::create([
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'order_number' => $row['order_number'],
            'order_date' => $row['order_date'],
            'channel_code' => $row['channel_code'],
            'region_code' => $row['region_code'],
            'warehouse_code' => $row['warehouse_code'],
            'sku' => $row['sku'],
            'quantity' => (int) $row['quantity'],
            'unit_price' => (float) $row['unit_price'],
            'unit_cost' => (float) $row['unit_cost'],
            'order_status' => $row['order_status'],
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function recordInventoryRow(string $batchId, string $workspaceId, int $rowNumber, array $row, string $status): void
    {
        StagingInventoryRecordModel::create([
            'batch_id' => $batchId,
            'workspace_id' => $workspaceId,
            'row_number' => $rowNumber,
            'snapshot_date' => $row['snapshot_date'],
            'warehouse_code' => $row['warehouse_code'],
            'sku' => $row['sku'],
            'quantity_on_hand' => (int) $row['quantity_on_hand'],
            'quantity_reserved' => (int) $row['quantity_reserved'],
            'safety_stock' => (int) ($row['safety_stock'] ?? 0),
            'reorder_point' => (int) ($row['reorder_point'] ?? 0),
            'unit_cost' => isset($row['unit_cost']) && $row['unit_cost'] !== '' ? (float) $row['unit_cost'] : null,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function markSalesRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        StagingSalesRecordModel::where('batch_id', $batchId)
            ->where('row_number', $rowNumber)
            ->update(['status' => $status]);
    }

    public function markInventoryRowStatus(string $batchId, int $rowNumber, string $status): void
    {
        StagingInventoryRecordModel::where('batch_id', $batchId)
            ->where('row_number', $rowNumber)
            ->update(['status' => $status]);
    }

    public function deleteByBatchId(string $batchId, string $workspaceId): void
    {
        StagingSalesRecordModel::where('batch_id', $batchId)
            ->where('workspace_id', $workspaceId)
            ->delete();

        StagingInventoryRecordModel::where('batch_id', $batchId)
            ->where('workspace_id', $workspaceId)
            ->delete();
    }
}
