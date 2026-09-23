<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Infrastructure\Persistence;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\DeliveryStatusBreakdownDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveryItemDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceItemDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformancePaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierSummaryDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierTrendPointDto;
use App\Modules\SupplierAnalytics\Domain\SupplierMetrics;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PostgresSupplierAnalyticsReadModel implements SupplierAnalyticsReadModelInterface
{
    public function getSupplierOverview(string $workspaceId, SupplierOverviewCriteriaDto $criteria): SupplierOverviewDto
    {
        $baseQuery = $this->baseDeliveriesQuery($workspaceId, $criteria->dateFrom, $criteria->dateTo, $criteria->supplierId, $criteria->warehouseId);

        $summaryRow = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'on_time\' THEN 1 ELSE 0 END), 0) as on_time_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'delayed\' THEN 1 ELSE 0 END), 0) as delayed_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'partial\' THEN 1 ELSE 0 END), 0) as partial_deliveries,
                COALESCE(SUM(d.total_purchase_cost), 0) as total_spend,
                COALESCE(SUM(d.ordered_quantity), 0) as total_ordered_quantity,
                COALESCE(SUM(d.received_quantity), 0) as total_received_quantity,
                COALESCE(SUM(d.defect_quantity), 0) as total_defect_quantity,
                COALESCE(AVG(d.lead_time_days), 0) as avg_lead_time_days,
                COALESCE(AVG(CASE WHEN d.delay_days > 0 THEN d.delay_days ELSE NULL END), 0) as avg_delay_days
            ')
            ->first();

        $totalDeliveries = (int) ($summaryRow->total_deliveries ?? 0);
        $onTime = (int) ($summaryRow->on_time_deliveries ?? 0);
        $delayed = (int) ($summaryRow->delayed_deliveries ?? 0);
        $partial = (int) ($summaryRow->partial_deliveries ?? 0);
        $totalSpend = (float) ($summaryRow->total_spend ?? 0.0);
        $totalOrdered = (int) ($summaryRow->total_ordered_quantity ?? 0);
        $totalReceived = (int) ($summaryRow->total_received_quantity ?? 0);
        $totalDefect = (int) ($summaryRow->total_defect_quantity ?? 0);

        $onTimeRate = SupplierMetrics::calculateOnTimeRate($onTime, $totalDeliveries);
        $delayRate = SupplierMetrics::calculateDelayRate($delayed, $totalDeliveries);
        $fulfillmentRate = SupplierMetrics::calculateFulfillmentRate($totalReceived, $totalOrdered);
        $defectRate = SupplierMetrics::calculateDefectRate($totalDefect, $totalReceived);
        $avgLeadTime = round((float) ($summaryRow->avg_lead_time_days ?? 0.0), 1);
        $avgDelayDays = round((float) ($summaryRow->avg_delay_days ?? 0.0), 1);

        $summary = new SupplierSummaryDto(
            totalDeliveries: $totalDeliveries,
            onTimeDeliveries: $onTime,
            delayedDeliveries: $delayed,
            partialDeliveries: $partial,
            totalSpend: round($totalSpend, 2),
            totalOrderedQuantity: $totalOrdered,
            totalReceivedQuantity: $totalReceived,
            totalDefectQuantity: $totalDefect,
            onTimeRate: $onTimeRate,
            delayRate: $delayRate,
            fulfillmentRate: $fulfillmentRate,
            defectRate: $defectRate,
            averageLeadTimeDays: $avgLeadTime,
            averageDelayDays: $avgDelayDays,
        );

        // Status breakdown
        $breakdownRows = (clone $baseQuery)
            ->selectRaw('
                d.delivery_status,
                COUNT(*) as count,
                COALESCE(SUM(d.received_quantity), 0) as quantity
            ')
            ->groupBy('d.delivery_status')
            ->get();

        $breakdownMap = [];
        foreach ($breakdownRows as $row) {
            $breakdownMap[$row->delivery_status] = [
                'count' => (int) $row->count,
                'quantity' => (int) $row->quantity,
            ];
        }

        $statusBreakdown = [];
        foreach (['on_time', 'delayed', 'partial'] as $st) {
            $cnt = $breakdownMap[$st]['count'] ?? 0;
            $qty = $breakdownMap[$st]['quantity'] ?? 0;
            $share = $totalDeliveries > 0 ? round(($cnt / $totalDeliveries) * 100, 1) : 0.0;
            $statusBreakdown[] = new DeliveryStatusBreakdownDto($st, $cnt, $share, $qty);
        }

        // Monthly trends
        $trendRows = (clone $baseQuery)
            ->selectRaw('
                TO_CHAR(d.order_date, \'YYYY-MM\') as period,
                COUNT(*) as deliveries_count,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'on_time\' THEN 1 ELSE 0 END), 0) as on_time_deliveries,
                COALESCE(SUM(d.total_purchase_cost), 0) as total_spend,
                COALESCE(SUM(d.ordered_quantity), 0) as ordered_quantity,
                COALESCE(SUM(d.received_quantity), 0) as received_quantity,
                COALESCE(AVG(d.lead_time_days), 0) as avg_lead_time_days
            ')
            ->groupBy(DB::raw('TO_CHAR(d.order_date, \'YYYY-MM\')'))
            ->orderBy('period')
            ->get();

        $trends = [];
        foreach ($trendRows as $tr) {
            $tDel = (int) $tr->deliveries_count;
            $tOnTime = (int) $tr->on_time_deliveries;
            $tOrd = (int) $tr->ordered_quantity;
            $tRec = (int) $tr->received_quantity;

            $trends[] = new SupplierTrendPointDto(
                period: (string) $tr->period,
                deliveriesCount: $tDel,
                onTimeDeliveries: $tOnTime,
                totalSpend: round((float) $tr->total_spend, 2),
                onTimeRate: SupplierMetrics::calculateOnTimeRate($tOnTime, $tDel),
                fulfillmentRate: SupplierMetrics::calculateFulfillmentRate($tRec, $tOrd),
                avgLeadTimeDays: round((float) $tr->avg_lead_time_days, 1),
            );
        }

        // Top suppliers (top 5 by total spend)
        $topSupplierRows = (clone $baseQuery)
            ->join('dim_suppliers as s', 's.id', '=', 'd.supplier_id')
            ->selectRaw('
                s.id as supplier_id,
                s.name as supplier_name,
                COUNT(*) as total_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'on_time\' THEN 1 ELSE 0 END), 0) as on_time_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'delayed\' THEN 1 ELSE 0 END), 0) as delayed_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'partial\' THEN 1 ELSE 0 END), 0) as partial_deliveries,
                COALESCE(SUM(d.total_purchase_cost), 0) as total_spend,
                COALESCE(SUM(d.ordered_quantity), 0) as ordered_quantity,
                COALESCE(SUM(d.received_quantity), 0) as received_quantity,
                COALESCE(SUM(d.defect_quantity), 0) as defect_quantity,
                COALESCE(AVG(d.lead_time_days), 0) as avg_lead_time_days,
                COALESCE(AVG(CASE WHEN d.delay_days > 0 THEN d.delay_days ELSE NULL END), 0) as avg_delay_days
            ')
            ->groupBy('s.id', 's.name')
            ->orderByDesc('total_spend')
            ->limit(5)
            ->get();

        $topSuppliers = [];
        foreach ($topSupplierRows as $row) {
            $topSuppliers[] = $this->mapSupplierPerformanceRow($row);
        }

        return new SupplierOverviewDto(
            summary: $summary,
            statusBreakdown: $statusBreakdown,
            trends: $trends,
            topSuppliers: $topSuppliers,
        );
    }

    public function getSupplierPerformance(string $workspaceId, SupplierPerformanceCriteriaDto $criteria): SupplierPerformancePaginatedDto
    {
        $baseQuery = $this->baseDeliveriesQuery($workspaceId, $criteria->dateFrom, $criteria->dateTo, null, $criteria->warehouseId)
            ->join('dim_suppliers as s', 's.id', '=', 'd.supplier_id');

        if ($criteria->search !== null && $criteria->search !== '') {
            $baseQuery->where('s.name', 'ilike', '%'.$criteria->search.'%');
        }

        $aggregatedQuery = (clone $baseQuery)
            ->selectRaw('
                s.id as supplier_id,
                s.name as supplier_name,
                COUNT(*) as total_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'on_time\' THEN 1 ELSE 0 END), 0) as on_time_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'delayed\' THEN 1 ELSE 0 END), 0) as delayed_deliveries,
                COALESCE(SUM(CASE WHEN d.delivery_status = \'partial\' THEN 1 ELSE 0 END), 0) as partial_deliveries,
                COALESCE(SUM(d.total_purchase_cost), 0) as total_spend,
                COALESCE(SUM(d.ordered_quantity), 0) as ordered_quantity,
                COALESCE(SUM(d.received_quantity), 0) as received_quantity,
                COALESCE(SUM(d.defect_quantity), 0) as defect_quantity,
                COALESCE(AVG(d.lead_time_days), 0) as avg_lead_time_days,
                COALESCE(AVG(CASE WHEN d.delay_days > 0 THEN d.delay_days ELSE NULL END), 0) as avg_delay_days
            ')
            ->groupBy('s.id', 's.name');

        $allRows = $aggregatedQuery->get();
        $items = [];
        foreach ($allRows as $row) {
            $items[] = $this->mapSupplierPerformanceRow($row);
        }

        // Sorting
        $sortBy = $criteria->sortBy;
        $desc = strtolower($criteria->sortDirection) === 'desc';

        usort($items, function (SupplierPerformanceItemDto $a, SupplierPerformanceItemDto $b) use ($sortBy, $desc) {
            $valA = match ($sortBy) {
                'supplier_name' => $a->supplierName,
                'total_deliveries' => $a->totalDeliveries,
                'on_time_rate' => $a->onTimeRate,
                'fulfillment_rate' => $a->fulfillmentRate,
                'defect_rate' => $a->defectRate,
                'avg_lead_time_days' => $a->avgLeadTimeDays,
                'reliability_score' => $a->reliabilityScore,
                default => $a->totalSpend,
            };

            $valB = match ($sortBy) {
                'supplier_name' => $b->supplierName,
                'total_deliveries' => $b->totalDeliveries,
                'on_time_rate' => $b->onTimeRate,
                'fulfillment_rate' => $b->fulfillmentRate,
                'defect_rate' => $b->defectRate,
                'avg_lead_time_days' => $b->avgLeadTimeDays,
                'reliability_score' => $b->reliabilityScore,
                default => $b->totalSpend,
            };

            $cmp = is_string($valA) ? strcmp($valA, (string) $valB) : ($valA <=> $valB);

            return $desc ? -$cmp : $cmp;
        });

        $total = count($items);
        $page = max(1, $criteria->page);
        $perPage = max(1, $criteria->perPage);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $offset = ($page - 1) * $perPage;
        $pagedItems = array_slice($items, $offset, $perPage);

        return new SupplierPerformancePaginatedDto(
            items: $pagedItems,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    public function getSupplierDeliveries(string $workspaceId, SupplierDeliveriesCriteriaDto $criteria): SupplierDeliveriesPaginatedDto
    {
        $query = DB::table('fact_supplier_deliveries as d')
            ->join('dim_suppliers as s', 's.id', '=', 'd.supplier_id')
            ->join('dim_warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->join('dim_products as p', 'p.id', '=', 'd.product_id')
            ->where('d.workspace_id', $workspaceId);

        if ($criteria->supplierId !== null) {
            $query->where('d.supplier_id', $criteria->supplierId);
        }
        if ($criteria->warehouseId !== null) {
            $query->where('d.warehouse_id', $criteria->warehouseId);
        }
        if ($criteria->status !== null) {
            $query->where('d.delivery_status', $criteria->status);
        }
        if ($criteria->dateFrom !== null) {
            $query->where('d.order_date', '>=', $criteria->dateFrom);
        }
        if ($criteria->dateTo !== null) {
            $query->where('d.order_date', '<=', $criteria->dateTo);
        }
        if ($criteria->search !== null && $criteria->search !== '') {
            $query->where(function ($q) use ($criteria) {
                $term = '%'.$criteria->search.'%';
                $q->where('p.name', 'ilike', $term)
                    ->orWhere('p.sku', 'ilike', $term)
                    ->orWhere('s.name', 'ilike', $term);
            });
        }

        $total = $query->count();

        // Sort mapping
        $sortCol = match ($criteria->sortBy) {
            'expected_delivery_date' => 'd.expected_delivery_date',
            'actual_delivery_date' => 'd.actual_delivery_date',
            'lead_time_days' => 'd.lead_time_days',
            'delay_days' => 'd.delay_days',
            'total_purchase_cost' => 'd.total_purchase_cost',
            default => 'd.order_date',
        };

        $sortDir = strtolower($criteria->sortDirection) === 'asc' ? 'asc' : 'desc';

        $page = max(1, $criteria->page);
        $perPage = max(1, $criteria->perPage);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $offset = ($page - 1) * $perPage;

        $rows = $query
            ->select([
                'd.id',
                'd.order_date',
                'd.expected_delivery_date',
                'd.actual_delivery_date',
                'd.supplier_id',
                's.name as supplier_name',
                'd.product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                'd.warehouse_id',
                'w.name as warehouse_name',
                'd.ordered_quantity',
                'd.received_quantity',
                'd.defect_quantity',
                'd.unit_purchase_cost',
                'd.total_purchase_cost',
                'd.delivery_status',
                'd.lead_time_days',
                'd.delay_days',
            ])
            ->orderBy($sortCol, $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $items[] = new SupplierDeliveryItemDto(
                id: (string) $row->id,
                orderDate: (string) $row->order_date,
                expectedDeliveryDate: (string) $row->expected_delivery_date,
                actualDeliveryDate: $row->actual_delivery_date ? (string) $row->actual_delivery_date : null,
                supplierId: (string) $row->supplier_id,
                supplierName: (string) $row->supplier_name,
                productId: (string) $row->product_id,
                productName: (string) $row->product_name,
                productSku: (string) $row->product_sku,
                warehouseId: (string) $row->warehouse_id,
                warehouseName: (string) $row->warehouse_name,
                orderedQuantity: (int) $row->ordered_quantity,
                receivedQuantity: (int) $row->received_quantity,
                defectQuantity: (int) ($row->defect_quantity ?? 0),
                unitPurchaseCost: (float) $row->unit_purchase_cost,
                totalPurchaseCost: (float) $row->total_purchase_cost,
                deliveryStatus: (string) $row->delivery_status,
                leadTimeDays: (int) $row->lead_time_days,
                delayDays: (int) $row->delay_days,
            );
        }

        return new SupplierDeliveriesPaginatedDto(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
        );
    }

    public function getFilterOptions(string $workspaceId): SupplierFilterOptionsDto
    {
        $suppliers = DB::table('fact_supplier_deliveries as d')
            ->join('dim_suppliers as s', 's.id', '=', 'd.supplier_id')
            ->where('d.workspace_id', $workspaceId)
            ->select('s.id', 's.name')
            ->distinct()
            ->orderBy('s.name')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name])
            ->all();

        $warehouses = DB::table('fact_supplier_deliveries as d')
            ->join('dim_warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->where('d.workspace_id', $workspaceId)
            ->select('w.id', 'w.name')
            ->distinct()
            ->orderBy('w.name')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'name' => (string) $r->name])
            ->all();

        $dateBounds = DB::table('fact_supplier_deliveries')
            ->where('workspace_id', $workspaceId)
            ->selectRaw('MIN(order_date) as min_date, MAX(order_date) as max_date')
            ->first();

        $minDate = $dateBounds?->min_date ? (string) $dateBounds->min_date : '2025-01-01';
        $maxDate = $dateBounds?->max_date ? (string) $dateBounds->max_date : '2025-12-31';

        return new SupplierFilterOptionsDto(
            suppliers: $suppliers,
            warehouses: $warehouses,
            statuses: [
                ['value' => 'on_time', 'label' => 'В срок'],
                ['value' => 'delayed', 'label' => 'С задержкой'],
                ['value' => 'partial', 'label' => 'Частично'],
            ],
            minDate: $minDate,
            maxDate: $maxDate,
        );
    }

    private function baseDeliveriesQuery(
        string $workspaceId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $supplierId = null,
        ?string $warehouseId = null,
    ): Builder {
        $query = DB::table('fact_supplier_deliveries as d')
            ->where('d.workspace_id', $workspaceId);

        if ($dateFrom !== null) {
            $query->where('d.order_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('d.order_date', '<=', $dateTo);
        }
        if ($supplierId !== null) {
            $query->where('d.supplier_id', $supplierId);
        }
        if ($warehouseId !== null) {
            $query->where('d.warehouse_id', $warehouseId);
        }

        return $query;
    }

    private function mapSupplierPerformanceRow(object $row): SupplierPerformanceItemDto
    {
        $tot = (int) $row->total_deliveries;
        $onTime = (int) $row->on_time_deliveries;
        $delayed = (int) $row->delayed_deliveries;
        $partial = (int) $row->partial_deliveries;
        $ord = (int) $row->ordered_quantity;
        $rec = (int) $row->received_quantity;
        $def = (int) ($row->defect_quantity ?? 0);

        $onTimeRate = SupplierMetrics::calculateOnTimeRate($onTime, $tot);
        $delayRate = SupplierMetrics::calculateDelayRate($delayed, $tot);
        $fulfillment = SupplierMetrics::calculateFulfillmentRate($rec, $ord);
        $defectRate = SupplierMetrics::calculateDefectRate($def, $rec);
        $avgLead = round((float) ($row->avg_lead_time_days ?? 0.0), 1);
        $avgDelay = round((float) ($row->avg_delay_days ?? 0.0), 1);
        $score = SupplierMetrics::calculateReliabilityScore($onTimeRate, $fulfillment, $defectRate);
        $tier = SupplierMetrics::classifyReliabilityTier($score);

        return new SupplierPerformanceItemDto(
            supplierId: (string) $row->supplier_id,
            supplierName: (string) $row->supplier_name,
            totalDeliveries: $tot,
            onTimeDeliveries: $onTime,
            delayedDeliveries: $delayed,
            partialDeliveries: $partial,
            totalSpend: round((float) $row->total_spend, 2),
            orderedQuantity: $ord,
            receivedQuantity: $rec,
            defectQuantity: $def,
            onTimeRate: $onTimeRate,
            delayRate: $delayRate,
            fulfillmentRate: $fulfillment,
            defectRate: $defectRate,
            avgLeadTimeDays: $avgLead,
            avgDelayDays: $avgDelay,
            reliabilityScore: $score,
            reliabilityTier: $tier->value,
        );
    }
}
