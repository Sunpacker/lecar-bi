<?php

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\StockHealthBreakdownDto;
use App\Modules\InventoryAnalytics\Application\Dtos\WarehouseStockDto;
use App\Modules\InventoryAnalytics\Domain\InventoryMetrics;
use App\Modules\InventoryAnalytics\Domain\StockHealthStatus;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresInventoryAnalyticsReadModel implements InventoryAnalyticsReadModelInterface
{
    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto
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

        // Warehouse breakdown query
        $warehouseRows = DB::table('dim_warehouses as w')
            ->leftJoinSub(
                $this->baseSnapshotQuery($workspaceId, $asOfDate),
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
                COUNT(inv.id) as items_count,
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

    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto
    {
        $asOfDate = $this->resolveLatestSnapshotDate($workspaceId);

        if (! $asOfDate) {
            return new InventoryItemsPaginatedDto(items: [], total: 0, page: $criteria->page, perPage: $criteria->perPage, totalPages: 0);
        }

        $query = $this->baseSnapshotQuery($workspaceId, $asOfDate, $criteria->warehouseId);

        if ($criteria->stockHealth !== null && $criteria->stockHealth !== '') {
            $query->where('inv.health_status', $criteria->stockHealth);
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $searchTerm = '%'.mb_strtolower(trim($criteria->search)).'%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->whereRaw('LOWER(p.name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(p.sku) LIKE ?', [$searchTerm]);
            });
        }

        $sortField = match ($criteria->sortBy) {
            'quantity_on_hand' => 'inv.quantity_on_hand',
            'quantity_available' => 'inv.quantity_available',
            'inventory_value' => 'inv.inventory_value',
            'sales_velocity' => 'inv.sales_velocity',
            'days_of_stock' => 'inv.days_of_stock',
            default => 'p.name',
        };

        $sortDirection = $criteria->sortDirection === 'desc' ? 'desc' : 'asc';

        $perPage = max(1, $criteria->perPage);
        $page = max(1, $criteria->page);
        $offset = ($page - 1) * $perPage;

        $rows = (clone $query)
            ->selectRaw('
                inv.id,
                inv.product_id,
                p.name as product_name,
                p.sku as product_sku,
                p.category_id,
                c.name as category_name,
                inv.warehouse_id,
                w.name as warehouse_name,
                w.code as warehouse_code,
                inv.quantity_on_hand,
                inv.quantity_reserved,
                inv.quantity_available,
                inv.unit_cost,
                inv.inventory_value,
                inv.sales_velocity,
                inv.days_of_stock,
                inv.health_status,
                inv.safety_stock,
                inv.reorder_point,
                COUNT(*) OVER() as full_count
            ')
            ->orderBy($sortField, $sortDirection)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = count($rows) > 0 ? (int) $rows[0]->full_count : 0;
        $totalPages = (int) ceil($total / $perPage);

        $items = [];
        foreach ($rows as $row) {
            $healthStatus = StockHealthStatus::tryFrom($row->health_status) ?? StockHealthStatus::OPTIMAL;

            $items[] = new InventoryItemDto(
                id: (string) $row->id,
                productId: (string) $row->product_id,
                productName: (string) $row->product_name,
                productSku: (string) $row->product_sku,
                categoryId: (string) $row->category_id,
                categoryName: (string) $row->category_name,
                warehouseId: (string) $row->warehouse_id,
                warehouseName: (string) $row->warehouse_name,
                warehouseCode: (string) $row->warehouse_code,
                quantityOnHand: (int) $row->quantity_on_hand,
                quantityReserved: (int) $row->quantity_reserved,
                quantityAvailable: (int) $row->quantity_available,
                unitCost: (float) $row->unit_cost,
                inventoryValue: (float) $row->inventory_value,
                salesVelocity: (float) $row->sales_velocity,
                daysOfStock: $row->days_of_stock !== null ? (float) $row->days_of_stock : null,
                stockHealth: $healthStatus->value,
                stockHealthLabel: $healthStatus->label(),
                safetyStock: (int) $row->safety_stock,
                reorderPoint: (int) $row->reorder_point,
            );
        }

        return new InventoryItemsPaginatedDto(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    public function getFilterOptions(string $workspaceId): InventoryFilterOptionsDto
    {
        $warehouses = DB::table('dim_warehouses')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn ($wh) => [
                'id' => (string) $wh->id,
                'name' => (string) $wh->name,
                'code' => (string) $wh->code,
            ])
            ->all();

        $statuses = array_map(fn (StockHealthStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], StockHealthStatus::cases());

        $latestDate = $this->resolveLatestSnapshotDate($workspaceId) ?? Carbon::today()->toDateString();

        return new InventoryFilterOptionsDto(
            warehouses: $warehouses,
            statuses: $statuses,
            latestSnapshotDate: $latestDate,
        );
    }

    private function resolveLatestSnapshotDate(string $workspaceId): ?string
    {
        return DB::table('fact_inventory_daily')
            ->where('workspace_id', $workspaceId)
            ->max('snapshot_date');
    }

    /**
     * Builds base subquery calculating velocity and health classifications for the snapshot.
     */
    private function baseSnapshotQuery(string $workspaceId, string $asOfDate, ?string $warehouseId = null): Builder
    {
        $thirtyDaysAgo = Carbon::parse($asOfDate)->subDays(30)->toDateString();

        // Subquery calculating sales velocity per product per warehouse over 30 days
        $velocitySub = DB::table('fact_order_items')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('order_date', [$thirtyDaysAgo, $asOfDate])
            ->select('product_id', 'warehouse_id')
            ->selectRaw('ROUND(COALESCE(SUM(quantity), 0) / 30.0, 2) as sales_velocity')
            ->groupBy('product_id', 'warehouse_id');

        $query = DB::table('fact_inventory_daily as f')
            ->leftJoinSub($velocitySub, 'v', function ($join) {
                $join->on('v.product_id', '=', 'f.product_id')
                    ->on('v.warehouse_id', '=', 'f.warehouse_id');
            })
            ->join('dim_products as p', function ($join) {
                $join->on('p.id', '=', 'f.product_id')
                    ->on('p.workspace_id', '=', 'f.workspace_id');
            })
            ->join('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'p.category_id')
                    ->on('c.workspace_id', '=', 'f.workspace_id');
            })
            ->join('dim_warehouses as w', function ($join) {
                $join->on('w.id', '=', 'f.warehouse_id')
                    ->on('w.workspace_id', '=', 'f.workspace_id');
            })
            ->where('f.workspace_id', $workspaceId)
            ->where('f.snapshot_date', $asOfDate);

        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('f.warehouse_id', $warehouseId);
        }

        // Calculate derived days of stock and health status dynamically
        $query->selectRaw('
            f.id,
            f.workspace_id,
            f.product_id,
            f.warehouse_id,
            f.quantity_on_hand,
            f.quantity_reserved,
            f.quantity_available,
            f.unit_cost,
            f.inventory_value,
            f.safety_stock,
            f.reorder_point,
            COALESCE(v.sales_velocity, 0.0) as sales_velocity,
            CASE
                WHEN f.quantity_available <= 0 THEN 0.0
                WHEN COALESCE(v.sales_velocity, 0.0) <= 0 THEN NULL
                ELSE ROUND(f.quantity_available / v.sales_velocity, 1)
            END as days_of_stock,
            CASE
                WHEN f.quantity_available <= 0 THEN \'out_of_stock\'
                WHEN (COALESCE(v.sales_velocity, 0.0) > 0 AND (f.quantity_available / v.sales_velocity) <= 7.0) OR f.quantity_available <= f.safety_stock THEN \'critical\'
                WHEN (COALESCE(v.sales_velocity, 0.0) > 0 AND (f.quantity_available / v.sales_velocity) > 60.0) OR (COALESCE(v.sales_velocity, 0.0) <= 0 AND f.quantity_available > f.safety_stock * 3) THEN \'overstock\'
                ELSE \'optimal\'
            END as health_status
        ');

        return DB::query()->fromSub($query, 'inv')
            ->join('dim_products as p', 'p.id', '=', 'inv.product_id')
            ->join('dim_categories as c', 'c.id', '=', 'p.category_id')
            ->join('dim_warehouses as w', 'w.id', '=', 'inv.warehouse_id');
    }
}
