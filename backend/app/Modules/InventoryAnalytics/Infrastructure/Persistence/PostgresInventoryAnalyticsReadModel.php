<?php

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcDistributionDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzMatrixCellDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\StockHealthBreakdownDto;
use App\Modules\InventoryAnalytics\Application\Dtos\WarehouseStockDto;
use App\Modules\InventoryAnalytics\Application\Dtos\XyzDistributionDto;
use App\Modules\InventoryAnalytics\Domain\AbcXyzCalculator;
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

        $categories = DB::table('dim_categories')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => (string) $cat->id,
                'name' => (string) $cat->name,
                'code' => (string) $cat->code,
            ])
            ->all();

        $suppliers = DB::table('dim_suppliers')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($sup) => [
                'id' => (string) $sup->id,
                'name' => (string) $sup->name,
            ])
            ->all();

        return new InventoryFilterOptionsDto(
            warehouses: $warehouses,
            statuses: $statuses,
            latestSnapshotDate: $latestDate,
            categories: $categories,
            suppliers: $suppliers,
        );
    }

    public function getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto
    {
        $rawProducts = $this->fetchRawAbcXyzProducts(
            $workspaceId,
            $criteria->periodDays,
            $criteria->warehouseId,
            $criteria->categoryId,
            $criteria->supplierId
        );

        $analysis = AbcXyzCalculator::analyze($rawProducts);

        $matrixDtos = array_map(fn (array $cell) => new AbcXyzMatrixCellDto(
            code: $cell['code'],
            label: $cell['label'],
            description: $cell['description'],
            recommendation: $cell['recommendation'],
            count: $cell['count'],
            countShare: $cell['count_share'],
            revenue: $cell['revenue'],
            revenueShare: $cell['revenue_share'],
            inventoryValue: $cell['inventory_value'],
            inventoryValueShare: $cell['inventory_value_share'],
        ), $analysis['matrix']);

        $abcDtos = array_map(fn (array $item) => new AbcDistributionDto(
            class: $item['class'],
            label: $item['label'],
            count: $item['count'],
            countShare: $item['count_share'],
            revenue: $item['revenue'],
            revenueShare: $item['revenue_share'],
        ), $analysis['abc_distribution']);

        $xyzDtos = array_map(fn (array $item) => new XyzDistributionDto(
            class: $item['class'],
            label: $item['label'],
            count: $item['count'],
            countShare: $item['count_share'],
            revenue: $item['revenue'],
            revenueShare: $item['revenue_share'],
        ), $analysis['xyz_distribution']);

        $endDate = $this->resolveLatestSnapshotDate($workspaceId) ?? Carbon::today()->toDateString();
        $startDate = Carbon::parse($endDate)->subDays($criteria->periodDays)->toDateString();

        return new AbcXyzSummaryDto(
            totalProducts: $analysis['total_products'],
            totalRevenue: $analysis['total_revenue'],
            totalInventoryValue: $analysis['total_inventory_value'],
            matrix: $matrixDtos,
            abcDistribution: $abcDtos,
            xyzDistribution: $xyzDtos,
            periodDays: $criteria->periodDays,
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    public function getAbcXyzItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto
    {
        $rawProducts = $this->fetchRawAbcXyzProducts(
            $workspaceId,
            $criteria->periodDays,
            $criteria->warehouseId,
            $criteria->categoryId,
            $criteria->supplierId
        );

        $analysis = AbcXyzCalculator::analyze($rawProducts);
        $items = $analysis['items'];

        if ($criteria->abcClass !== null && $criteria->abcClass !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['abc_class'] === $criteria->abcClass));
        }

        if ($criteria->xyzClass !== null && $criteria->xyzClass !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['xyz_class'] === $criteria->xyzClass));
        }

        if ($criteria->group !== null && $criteria->group !== '') {
            $items = array_values(array_filter($items, fn ($i) => $i['abc_xyz_group'] === $criteria->group));
        }

        if ($criteria->search !== null && $criteria->search !== '') {
            $searchLower = mb_strtolower(trim($criteria->search));
            $items = array_values(array_filter($items, function ($i) use ($searchLower) {
                return str_contains(mb_strtolower($i['product_name']), $searchLower)
                    || str_contains(mb_strtolower($i['product_sku']), $searchLower);
            }));
        }

        // Sorting
        usort($items, function (array $a, array $b) use ($criteria) {
            $direction = $criteria->sortDirection === 'desc' ? -1 : 1;

            return match ($criteria->sortBy) {
                'product_name' => strcmp($a['product_name'], $b['product_name']) * $direction,
                'total_units_sold' => ($a['total_units_sold'] <=> $b['total_units_sold']) * $direction,
                'revenue_share' => ($a['revenue_share'] <=> $b['revenue_share']) * $direction,
                'cumulative_revenue_share' => ($a['cumulative_revenue_share'] <=> $b['cumulative_revenue_share']) * $direction,
                'coefficient_of_variation' => (($a['coefficient_of_variation'] ?? 999999) <=> ($b['coefficient_of_variation'] ?? 999999)) * $direction,
                'current_stock' => ($a['current_stock'] <=> $b['current_stock']) * $direction,
                'inventory_value' => ($a['inventory_value'] <=> $b['inventory_value']) * $direction,
                default => ($a['total_revenue'] <=> $b['total_revenue']) * $direction,
            };
        });

        $total = count($items);
        $perPage = max(1, $criteria->perPage);
        $page = max(1, $criteria->page);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($items, $offset, $perPage);

        $itemDtos = array_map(fn (array $i) => new AbcXyzProductItemDto(
            id: $i['id'],
            productId: $i['product_id'],
            productName: $i['product_name'],
            productSku: $i['product_sku'],
            categoryId: $i['category_id'],
            categoryName: $i['category_name'],
            brandName: $i['brand_name'],
            supplierId: $i['supplier_id'],
            supplierName: $i['supplier_name'],
            totalRevenue: $i['total_revenue'],
            totalUnitsSold: $i['total_units_sold'],
            revenueShare: $i['revenue_share'],
            cumulativeRevenueShare: $i['cumulative_revenue_share'],
            abcClass: $i['abc_class'],
            periodSales: $i['period_sales'],
            averageSales: $i['average_sales'],
            standardDeviation: $i['standard_deviation'],
            coefficientOfVariation: $i['coefficient_of_variation'],
            xyzClass: $i['xyz_class'],
            abcXyzGroup: $i['abc_xyz_group'],
            currentStock: $i['current_stock'],
            inventoryValue: $i['inventory_value'],
        ), $sliced);

        return new AbcXyzProductItemsPaginatedDto(
            items: $itemDtos,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    /**
     * Fetches raw product aggregations across sales, snapshot inventory, and dimensions.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRawAbcXyzProducts(
        string $workspaceId,
        int $periodDays,
        ?string $warehouseId,
        ?string $categoryId,
        ?string $supplierId,
    ): array {
        $endDate = $this->resolveLatestSnapshotDate($workspaceId) ?? Carbon::today()->toDateString();
        $startDate = Carbon::parse($endDate)->subDays($periodDays)->toDateString();

        // 1. Sales totals per product in period for completed orders
        $salesQuery = DB::table('fact_order_items as foi')
            ->join('fact_orders as fo', function ($join) {
                $join->on('fo.id', '=', 'foi.order_id')
                    ->on('fo.workspace_id', '=', 'foi.workspace_id');
            })
            ->where('foi.workspace_id', $workspaceId)
            ->where('fo.status', 'completed')
            ->whereBetween('foi.order_date', [$startDate, $endDate]);

        if ($warehouseId !== null && $warehouseId !== '') {
            $salesQuery->where('foi.warehouse_id', $warehouseId);
        }

        $salesRows = (clone $salesQuery)
            ->select('foi.product_id')
            ->selectRaw('COALESCE(SUM(foi.total_price), 0) as revenue')
            ->selectRaw('COALESCE(SUM(foi.quantity), 0) as units_sold')
            ->groupBy('foi.product_id')
            ->get()
            ->keyBy('product_id');

        // 2. Periodic volumes for variation CV calculation
        $bucketSql = $periodDays <= 30 ? "TO_CHAR(foi.order_date, 'YYYY-IW')" : "TO_CHAR(foi.order_date, 'YYYY-MM')";
        $bucketRows = (clone $salesQuery)
            ->select('foi.product_id')
            ->selectRaw("{$bucketSql} as bucket")
            ->selectRaw('COALESCE(SUM(foi.quantity), 0) as bucket_units')
            ->groupBy('foi.product_id', DB::raw($bucketSql))
            ->orderBy('bucket')
            ->get();

        $allBuckets = $bucketRows->pluck('bucket')->unique()->sort()->values()->all();
        $productBuckets = [];
        foreach ($bucketRows as $row) {
            $productBuckets[$row->product_id][$row->bucket] = (float) $row->bucket_units;
        }

        // 3. Current stock and value from latest snapshot
        $stockQuery = DB::table('fact_inventory_daily')
            ->where('workspace_id', $workspaceId)
            ->where('snapshot_date', $endDate);

        if ($warehouseId !== null && $warehouseId !== '') {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stockRows = $stockQuery
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity_available), 0) as current_stock')
            ->selectRaw('COALESCE(SUM(inventory_value), 0) as inventory_value')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // 4. Products dimension query with category, brand, supplier
        $prodQuery = DB::table('dim_products as p')
            ->join('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'p.category_id')
                    ->on('c.workspace_id', '=', 'p.workspace_id');
            })
            ->join('dim_brands as b', function ($join) {
                $join->on('b.id', '=', 'p.brand_id')
                    ->on('b.workspace_id', '=', 'p.workspace_id');
            })
            ->where('p.workspace_id', $workspaceId);

        if ($categoryId !== null && $categoryId !== '') {
            $prodQuery->where('p.category_id', $categoryId);
        }

        // Supplier association via fact_supplier_deliveries
        $supplierSub = DB::table('fact_supplier_deliveries')
            ->where('workspace_id', $workspaceId)
            ->select('product_id', DB::raw('MAX(supplier_id) as supplier_id'))
            ->groupBy('product_id');

        $prodQuery->leftJoinSub($supplierSub, 'sup_link', fn ($join) => $join->on('sup_link.product_id', '=', 'p.id'))
            ->leftJoin('dim_suppliers as sup', function ($join) {
                $join->on('sup.id', '=', 'sup_link.supplier_id')
                    ->on('sup.workspace_id', '=', 'p.workspace_id');
            });

        if ($supplierId !== null && $supplierId !== '') {
            $prodQuery->where('sup.id', $supplierId);
        }

        $products = $prodQuery
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.category_id',
                'c.name as category_name',
                'b.name as brand_name',
                'sup.id as supplier_id',
                'sup.name as supplier_name',
            ])
            ->orderBy('p.name')
            ->get();

        $rawProducts = [];
        foreach ($products as $p) {
            $pid = (string) $p->product_id;
            $revenue = isset($salesRows[$pid]) ? (float) $salesRows[$pid]->revenue : 0.0;
            $units = isset($salesRows[$pid]) ? (int) $salesRows[$pid]->units_sold : 0;
            $stock = isset($stockRows[$pid]) ? (int) $stockRows[$pid]->current_stock : 0;
            $invVal = isset($stockRows[$pid]) ? (float) $stockRows[$pid]->inventory_value : 0.0;

            // Build full bucket array (fill missing periods with 0.0)
            $periodSales = [];
            foreach ($allBuckets as $bucket) {
                $periodSales[] = $productBuckets[$pid][$bucket] ?? 0.0;
            }

            $rawProducts[] = [
                'id' => $pid,
                'product_id' => $pid,
                'product_name' => (string) $p->product_name,
                'product_sku' => (string) $p->product_sku,
                'category_id' => (string) $p->category_id,
                'category_name' => (string) $p->category_name,
                'brand_name' => (string) $p->brand_name,
                'supplier_id' => $p->supplier_id !== null ? (string) $p->supplier_id : null,
                'supplier_name' => $p->supplier_name !== null ? (string) $p->supplier_name : null,
                'revenue' => $revenue,
                'units_sold' => $units,
                'current_stock' => $stock,
                'inventory_value' => $invVal,
                'period_sales' => $periodSales,
            ];
        }

        return $rawProducts;
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
