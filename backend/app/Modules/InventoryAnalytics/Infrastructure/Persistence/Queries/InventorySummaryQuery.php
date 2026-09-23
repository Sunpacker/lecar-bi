<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\StockHealthBreakdownDto;
use App\Modules\InventoryAnalytics\Application\Dtos\WarehouseStockDto;
use App\Modules\InventoryAnalytics\Domain\InventoryMetrics;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventorySummaryQuery
{
    use InteractsWithInventorySnapshot;

    public function execute(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto
    {
        $asOfDate = $criteria->asOfDate ?? $this->resolveLatestSnapshotDate($workspaceId);

        if (! $asOfDate) {
            return new InventorySummaryDto(
                totalItems: 0,
                totalQuantityOnHand: 0,
                totalQuantityReserved: 0,
                totalQuantityAvailable: 0,
                totalInventoryValue: 0.0,
                criticalCount: 0,
                overstockCount: 0,
                outOfStockCount: 0,
                optimalCount: 0,
                averageDaysOfStock: null,
                healthBreakdown: [],
                warehouses: [],
                asOfDate: Carbon::today()->toDateString(),
            );
        }

        // Base items query for snapshot
        $itemsQuery = $this->baseSnapshotQuery($workspaceId, $asOfDate, $criteria->warehouseId);

        $summaryRow = (clone $itemsQuery)
            ->selectRaw('
                COUNT(*) as total_items,
                COALESCE(SUM(inv.quantity_on_hand), 0) as total_on_hand,
                COALESCE(SUM(inv.quantity_reserved), 0) as total_reserved,
                COALESCE(SUM(inv.quantity_available), 0) as total_available,
                COALESCE(SUM(inv.inventory_value), 0) as total_value,
                COUNT(CASE WHEN inv.health_status = \'critical\' THEN 1 END) as critical_count,
                COUNT(CASE WHEN inv.health_status = \'overstock\' THEN 1 END) as overstock_count,
                COUNT(CASE WHEN inv.health_status = \'out_of_stock\' THEN 1 END) as out_of_stock_count,
                COUNT(CASE WHEN inv.health_status = \'optimal\' THEN 1 END) as optimal_count,
                AVG(CASE WHEN inv.days_of_stock IS NOT NULL AND inv.days_of_stock > 0 THEN inv.days_of_stock END) as avg_dos
            ')
            ->first();

        $totalItems = (int) ($summaryRow->total_items ?? 0);
        $totalOnHand = (int) ($summaryRow->total_on_hand ?? 0);
        $totalReserved = (int) ($summaryRow->total_reserved ?? 0);
        $totalAvailable = (int) ($summaryRow->total_available ?? 0);
        $totalValue = (float) ($summaryRow->total_value ?? 0.0);
        $criticalCount = (int) ($summaryRow->critical_count ?? 0);
        $overstockCount = (int) ($summaryRow->overstock_count ?? 0);
        $outOfStockCount = (int) ($summaryRow->out_of_stock_count ?? 0);
        $optimalCount = (int) ($summaryRow->optimal_count ?? 0);
        $avgDos = $summaryRow->avg_dos !== null ? round((float) $summaryRow->avg_dos, 1) : null;

        // Health breakdown query
        $healthBreakdownRows = (clone $itemsQuery)
            ->selectRaw('
                inv.health_status,
                COUNT(*) as items_count,
                COALESCE(SUM(inv.inventory_value), 0) as total_value
            ')
            ->groupBy('inv.health_status')
            ->get();

        $healthMap = [];
        foreach ($healthBreakdownRows as $row) {
            $healthMap[$row->health_status] = [
                'count' => (int) $row->items_count,
                'value' => (float) $row->total_value,
            ];
        }

        $healthBreakdown = [];
        foreach (StockHealthStatus::cases() as $status) {
            $count = $healthMap[$status->value]['count'] ?? 0;
            $val = $healthMap[$status->value]['value'] ?? 0.0;
            $share = $totalItems > 0 ? InventoryMetrics::calculateShare($count, $totalItems) : 0.0;

            $healthBreakdown[] = new StockHealthBreakdownDto(
                status: $status->value,
                label: $status->label(),
                itemsCount: $count,
                totalValue: $val,
                share: $share,
            );
        }

        $warehouseInventoryQuery = $this->baseSnapshotQuery($workspaceId, $asOfDate)
            ->selectRaw('
                inv.id as inventory_id,
                inv.warehouse_id,
                inv.quantity_available,
                inv.inventory_value,
                inv.health_status
            ');

        // Warehouse breakdown query
        $warehouseRows = DB::table('dim_warehouses as w')
            ->leftJoinSub(
                $warehouseInventoryQuery,
                'inv',
                fn ($join) => $join->on('inv.warehouse_id', '=', 'w.id')
            )
            ->where('w.workspace_id', $workspaceId)
            ->selectRaw('
                w.id as warehouse_id,
                w.name as warehouse_name,
                w.code as warehouse_code,
                COALESCE(SUM(inv.quantity_available), 0) as total_quantity,
                COALESCE(SUM(inv.inventory_value), 0) as total_value,
                COUNT(inv.inventory_id) as items_count,
                COUNT(CASE WHEN inv.health_status = \'critical\' THEN 1 END) as critical_count,
                COUNT(CASE WHEN inv.health_status = \'overstock\' THEN 1 END) as overstock_count
            ')
            ->groupBy('w.id', 'w.name', 'w.code')
            ->orderBy('w.name')
            ->get();

        $warehouses = [];
        foreach ($warehouseRows as $wh) {
            $warehouses[] = new WarehouseStockDto(
                warehouseId: (string) $wh->warehouse_id,
                warehouseName: (string) $wh->warehouse_name,
                warehouseCode: (string) $wh->warehouse_code,
                totalQuantity: (int) $wh->total_quantity,
                totalValue: (float) $wh->total_value,
                itemsCount: (int) $wh->items_count,
                criticalCount: (int) $wh->critical_count,
                overstockCount: (int) $wh->overstock_count,
            );
        }

        return new InventorySummaryDto(
            totalItems: $totalItems,
            totalQuantityOnHand: $totalOnHand,
            totalQuantityReserved: $totalReserved,
            totalQuantityAvailable: $totalAvailable,
            totalInventoryValue: $totalValue,
            criticalCount: $criticalCount,
            overstockCount: $overstockCount,
            outOfStockCount: $outOfStockCount,
            optimalCount: $optimalCount,
            averageDaysOfStock: $avgDos,
            healthBreakdown: $healthBreakdown,
            warehouses: $warehouses,
            asOfDate: $asOfDate,
        );
    }
}
