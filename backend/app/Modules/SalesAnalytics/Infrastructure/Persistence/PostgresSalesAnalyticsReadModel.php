<?php

namespace App\Modules\SalesAnalytics\Infrastructure\Persistence;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesCategoryBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRegionBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesTrendPointDto;
use App\Modules\SalesAnalytics\Domain\SalesMetrics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresSalesAnalyticsReadModel implements SalesAnalyticsReadModelInterface
{
    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto
    {
        // 1. Summary Query
        $summaryQuery = $this->baseItemsQuery($workspaceId, $criteria)
            ->selectRaw('COALESCE(SUM(total_price), 0) as total_revenue, COUNT(DISTINCT order_id) as order_count, COALESCE(SUM(gross_profit), 0) as gross_profit');

        $summaryRow = $summaryQuery->first();

        $totalRevenue = (float) ($summaryRow->total_revenue ?? 0.0);
        $orderCount = (int) ($summaryRow->order_count ?? 0);
        $grossProfit = (float) ($summaryRow->gross_profit ?? 0.0);

        $aov = SalesMetrics::calculateAov($totalRevenue, $orderCount);
        $marginRate = SalesMetrics::calculateMarginRate($totalRevenue, $grossProfit);

        $summaryDto = new SalesSummaryDto(
            totalRevenue: $totalRevenue,
            orderCount: $orderCount,
            averageOrderValue: $aov,
            grossProfit: $grossProfit,
            marginRate: $marginRate,
        );

        // 2. Trend Query
        $trendRows = $this->baseItemsQuery($workspaceId, $criteria)
            ->selectRaw('order_date as date, COALESCE(SUM(total_price), 0) as revenue, COUNT(DISTINCT order_id) as order_count')
            ->groupBy('order_date')
            ->orderBy('order_date', 'asc')
            ->get();

        $trendDtos = [];
        foreach ($trendRows as $row) {
            $trendDtos[] = new SalesTrendPointDto(
                date: (string) $row->date,
                revenue: (float) $row->revenue,
                orderCount: (int) $row->order_count,
            );
        }

        // 3. Category Breakdown Query
        $categoryRows = $this->baseItemsQuery($workspaceId, $criteria, 'i')
            ->join('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'i.category_id')
                    ->on('c.workspace_id', '=', 'i.workspace_id');
            })
            ->selectRaw('c.id as category_id, c.name as category_name, COALESCE(SUM(i.total_price), 0) as revenue, COUNT(DISTINCT i.order_id) as order_count')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('revenue')
            ->get();

        $categoryDtos = [];
        foreach ($categoryRows as $row) {
            $catRevenue = (float) $row->revenue;
            $categoryDtos[] = new SalesCategoryBreakdownDto(
                categoryId: (string) $row->category_id,
                categoryName: (string) $row->category_name,
                revenue: $catRevenue,
                orderCount: (int) $row->order_count,
                revenueShare: SalesMetrics::calculateShare($catRevenue, $totalRevenue),
            );
        }

        // 4. Regional Breakdown Query
        $regionRows = $this->baseItemsQuery($workspaceId, $criteria, 'i')
            ->join('dim_regions as r', function ($join) {
                $join->on('r.id', '=', 'i.region_id')
                    ->on('r.workspace_id', '=', 'i.workspace_id');
            })
            ->selectRaw('r.id as region_id, r.name as region_name, r.code as region_code, COALESCE(SUM(i.total_price), 0) as revenue, COUNT(DISTINCT i.order_id) as order_count')
            ->groupBy('r.id', 'r.name', 'r.code')
            ->orderByDesc('revenue')
            ->get();

        $regionDtos = [];
        foreach ($regionRows as $row) {
            $regRevenue = (float) $row->revenue;
            $regionDtos[] = new SalesRegionBreakdownDto(
                regionId: (string) $row->region_id,
                regionName: (string) $row->region_name,
                regionCode: (string) $row->region_code,
                revenue: $regRevenue,
                orderCount: (int) $row->order_count,
                revenueShare: SalesMetrics::calculateShare($regRevenue, $totalRevenue),
            );
        }

        return new SalesOverviewDto(
            summary: $summaryDto,
            trend: $trendDtos,
            categories: $categoryDtos,
            regions: $regionDtos,
        );
    }

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto
    {
        $categories = DB::table('dim_categories')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => ['id' => (string) $c->id, 'name' => (string) $c->name])
            ->all();

        $regions = DB::table('dim_regions')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name, 'code' => (string) $r->code])
            ->all();

        $dateBounds = DB::table('fact_orders')
            ->where('workspace_id', $workspaceId)
            ->selectRaw('MIN(order_date) as min_date, MAX(order_date) as max_date')
            ->first();

        $minDate = (string) ($dateBounds->min_date ?? date('Y-01-01'));
        $maxDate = (string) ($dateBounds->max_date ?? date('Y-m-d'));

        return new SalesFilterOptionsDto(
            categories: $categories,
            regions: $regions,
            minDate: $minDate,
            maxDate: $maxDate,
        );
    }

    public function getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto
    {
        $filterCriteria = new SalesFilterCriteriaDto(
            dateFrom: $criteria->dateFrom,
            dateTo: $criteria->dateTo,
            categoryId: $criteria->categoryId,
            regionId: $criteria->regionId,
        );

        $total = $this->baseItemsQuery($workspaceId, $filterCriteria)->count();

        $sortColumnMap = [
            'order_date' => 'i.order_date',
            'order_number' => 'o.order_number',
            'product_name' => 'p.name',
            'total_price' => 'i.total_price',
            'quantity' => 'i.quantity',
            'gross_profit' => 'i.gross_profit',
        ];
        $sortColumn = $sortColumnMap[$criteria->sortBy] ?? 'i.order_date';
        $direction = $criteria->sortDirection === 'asc' ? 'asc' : 'desc';

        $rows = $this->baseItemsQuery($workspaceId, $filterCriteria, 'i')
            ->join('fact_orders as o', function ($join) {
                $join->on('o.id', '=', 'i.order_id')
                    ->on('o.workspace_id', '=', 'i.workspace_id');
            })
            ->join('dim_products as p', function ($join) {
                $join->on('p.id', '=', 'i.product_id')
                    ->on('p.workspace_id', '=', 'i.workspace_id');
            })
            ->join('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'i.category_id')
                    ->on('c.workspace_id', '=', 'i.workspace_id');
            })
            ->join('dim_regions as r', function ($join) {
                $join->on('r.id', '=', 'i.region_id')
                    ->on('r.workspace_id', '=', 'i.workspace_id');
            })
            ->join('dim_brands as b', function ($join) {
                $join->on('b.id', '=', 'i.brand_id')
                    ->on('b.workspace_id', '=', 'i.workspace_id');
            })
            ->select([
                'i.id',
                'i.order_id',
                'o.order_number',
                'i.order_date',
                'i.product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                'i.category_id',
                'c.name as category_name',
                'i.region_id',
                'r.name as region_name',
                'b.name as brand_name',
                'i.quantity',
                'i.unit_price',
                'i.total_price',
                'i.gross_profit',
                'o.status',
            ])
            ->orderBy($sortColumn, $direction)
            ->forPage($criteria->page, $criteria->perPage)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = new SalesRecordDto(
                id: (string) $row->id,
                orderId: (string) $row->order_id,
                orderNumber: (string) $row->order_number,
                orderDate: (string) $row->order_date,
                productId: (string) $row->product_id,
                productName: (string) $row->product_name,
                productSku: (string) $row->product_sku,
                categoryId: (string) $row->category_id,
                categoryName: (string) $row->category_name,
                regionId: (string) $row->region_id,
                regionName: (string) $row->region_name,
                brandName: (string) $row->brand_name,
                quantity: (int) $row->quantity,
                unitPrice: (float) $row->unit_price,
                totalPrice: (float) $row->total_price,
                grossProfit: (float) $row->gross_profit,
                status: (string) $row->status,
            );
        }

        $totalPages = (int) max(1, ceil($total / $criteria->perPage));

        return new SalesRecordsPaginatedDto(
            items: $items,
            total: $total,
            page: $criteria->page,
            perPage: $criteria->perPage,
            totalPages: $totalPages,
        );
    }

    private function baseItemsQuery(string $workspaceId, SalesFilterCriteriaDto $criteria, ?string $alias = null): Builder
    {
        $table = $alias !== null ? "fact_order_items as {$alias}" : 'fact_order_items';
        $prefix = $alias !== null ? "{$alias}." : '';

        $query = DB::table($table)->where("{$prefix}workspace_id", $workspaceId);

        if ($criteria->dateFrom !== null) {
            $query->where("{$prefix}order_date", '>=', $criteria->dateFrom);
        }

        if ($criteria->dateTo !== null) {
            $query->where("{$prefix}order_date", '<=', $criteria->dateTo);
        }

        if ($criteria->categoryId !== null) {
            $query->where("{$prefix}category_id", $criteria->categoryId);
        }

        if ($criteria->regionId !== null) {
            $query->where("{$prefix}region_id", $criteria->regionId);
        }

        return $query;
    }
}
