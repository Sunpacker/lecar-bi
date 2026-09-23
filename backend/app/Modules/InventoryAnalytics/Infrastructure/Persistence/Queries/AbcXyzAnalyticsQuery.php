<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcDistributionDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzMatrixCellDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\XyzDistributionDto;
use App\Modules\InventoryAnalytics\Domain\AbcXyzCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbcXyzAnalyticsQuery
{
    use InteractsWithInventorySnapshot;

    public function getSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto
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

    public function getItems(string $workspaceId, AbcXyzItemsCriteriaDto $criteria): AbcXyzProductItemsPaginatedDto
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
}
