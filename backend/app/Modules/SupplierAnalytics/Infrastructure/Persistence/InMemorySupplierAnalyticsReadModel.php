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

final class InMemorySupplierAnalyticsReadModel implements SupplierAnalyticsReadModelInterface
{
    /** @var list<array<string, mixed>> */
    private array $deliveries = [];

    public function __construct()
    {
        $this->seedDefaultDeliveries();
    }

    /** @param list<array<string, mixed>> $deliveries */
    public function seedDeliveries(array $deliveries): void
    {
        $this->deliveries = $deliveries;
    }

    public function getSupplierOverview(string $workspaceId, SupplierOverviewCriteriaDto $criteria): SupplierOverviewDto
    {
        $deliveries = $this->filterDeliveries(
            $workspaceId,
            dateFrom: $criteria->dateFrom,
            dateTo: $criteria->dateTo,
            supplierId: $criteria->supplierId,
            warehouseId: $criteria->warehouseId,
        );

        $totalDeliveries = count($deliveries);
        $onTime = 0;
        $delayed = 0;
        $partial = 0;
        $totalSpend = 0.0;
        $totalOrdered = 0;
        $totalReceived = 0;
        $totalDefect = 0;
        $totalLeadTime = 0;
        $totalDelayDays = 0;
        $statusCounts = ['on_time' => 0, 'delayed' => 0, 'partial' => 0];
        $statusQuantities = ['on_time' => 0, 'delayed' => 0, 'partial' => 0];
        $monthlyGroups = [];
        $supplierGroups = [];

        foreach ($deliveries as $del) {
            $status = (string) $del['delivery_status'];
            if ($status === 'on_time') {
                $onTime++;
            } elseif ($status === 'delayed') {
                $delayed++;
            } elseif ($status === 'partial') {
                $partial++;
            }

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
                $statusQuantities[$status] += (int) $del['received_quantity'];
            }

            $totalSpend += (float) $del['total_purchase_cost'];
            $totalOrdered += (int) $del['ordered_quantity'];
            $totalReceived += (int) $del['received_quantity'];
            $totalDefect += (int) ($del['defect_quantity'] ?? 0);
            $totalLeadTime += (int) $del['lead_time_days'];
            $totalDelayDays += (int) $del['delay_days'];

            // Monthly grouping for trends
            $month = substr((string) $del['order_date'], 0, 7);
            if (! isset($monthlyGroups[$month])) {
                $monthlyGroups[$month] = [
                    'period' => $month,
                    'deliveries' => 0,
                    'on_time' => 0,
                    'spend' => 0.0,
                    'ordered' => 0,
                    'received' => 0,
                    'lead_time' => 0,
                ];
            }
            $monthlyGroups[$month]['deliveries']++;
            if ($status === 'on_time') {
                $monthlyGroups[$month]['on_time']++;
            }
            $monthlyGroups[$month]['spend'] += (float) $del['total_purchase_cost'];
            $monthlyGroups[$month]['ordered'] += (int) $del['ordered_quantity'];
            $monthlyGroups[$month]['received'] += (int) $del['received_quantity'];
            $monthlyGroups[$month]['lead_time'] += (int) $del['lead_time_days'];

            // Supplier grouping for top suppliers
            $supId = (string) $del['supplier_id'];
            if (! isset($supplierGroups[$supId])) {
                $supplierGroups[$supId] = [
                    'id' => $supId,
                    'name' => (string) $del['supplier_name'],
                    'deliveries' => 0,
                    'on_time' => 0,
                    'delayed' => 0,
                    'partial' => 0,
                    'spend' => 0.0,
                    'ordered' => 0,
                    'received' => 0,
                    'defect' => 0,
                    'lead_time' => 0,
                    'delay_days' => 0,
                ];
            }
            $supplierGroups[$supId]['deliveries']++;
            if ($status === 'on_time') {
                $supplierGroups[$supId]['on_time']++;
            } elseif ($status === 'delayed') {
                $supplierGroups[$supId]['delayed']++;
            } elseif ($status === 'partial') {
                $supplierGroups[$supId]['partial']++;
            }
            $supplierGroups[$supId]['spend'] += (float) $del['total_purchase_cost'];
            $supplierGroups[$supId]['ordered'] += (int) $del['ordered_quantity'];
            $supplierGroups[$supId]['received'] += (int) $del['received_quantity'];
            $supplierGroups[$supId]['defect'] += (int) ($del['defect_quantity'] ?? 0);
            $supplierGroups[$supId]['lead_time'] += (int) $del['lead_time_days'];
            $supplierGroups[$supId]['delay_days'] += (int) $del['delay_days'];
        }

        $onTimeRate = SupplierMetrics::calculateOnTimeRate($onTime, $totalDeliveries);
        $delayRate = SupplierMetrics::calculateDelayRate($delayed, $totalDeliveries);
        $fulfillmentRate = SupplierMetrics::calculateFulfillmentRate($totalReceived, $totalOrdered);
        $defectRate = SupplierMetrics::calculateDefectRate($totalDefect, $totalReceived);
        $avgLeadTime = SupplierMetrics::calculateAverageLeadTime($totalLeadTime, $totalDeliveries);
        $avgDelayDays = SupplierMetrics::calculateAverageDelayDays($totalDelayDays, $delayed);

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

        $statusBreakdown = [];
        foreach (['on_time', 'delayed', 'partial'] as $st) {
            $cnt = $statusCounts[$st];
            $qty = $statusQuantities[$st];
            $share = $totalDeliveries > 0 ? round(($cnt / $totalDeliveries) * 100, 1) : 0.0;
            $statusBreakdown[] = new DeliveryStatusBreakdownDto($st, $cnt, $share, $qty);
        }

        ksort($monthlyGroups);
        $trends = [];
        foreach ($monthlyGroups as $group) {
            $tTotal = $group['deliveries'];
            $tOnTime = $group['on_time'];
            $tOnTimeRate = SupplierMetrics::calculateOnTimeRate($tOnTime, $tTotal);
            $tFulfillment = SupplierMetrics::calculateFulfillmentRate($group['received'], $group['ordered']);
            $tLead = SupplierMetrics::calculateAverageLeadTime($group['lead_time'], $tTotal);

            $trends[] = new SupplierTrendPointDto(
                period: $group['period'],
                deliveriesCount: $tTotal,
                onTimeDeliveries: $tOnTime,
                totalSpend: round($group['spend'], 2),
                onTimeRate: $tOnTimeRate,
                fulfillmentRate: $tFulfillment,
                avgLeadTimeDays: $tLead,
            );
        }

        // Top suppliers sorted by spend descending (top 5)
        uasort($supplierGroups, fn ($a, $b) => $b['spend'] <=> $a['spend']);
        $topSuppliers = [];
        foreach (array_slice($supplierGroups, 0, 5) as $sg) {
            $topSuppliers[] = $this->buildSupplierPerformanceItem($sg);
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
        $deliveries = $this->filterDeliveries(
            $workspaceId,
            dateFrom: $criteria->dateFrom,
            dateTo: $criteria->dateTo,
            warehouseId: $criteria->warehouseId,
        );

        $supplierGroups = [];
        foreach ($deliveries as $del) {
            $supId = (string) $del['supplier_id'];
            $supName = (string) $del['supplier_name'];

            if ($criteria->search !== null && $criteria->search !== '') {
                if (stripos($supName, $criteria->search) === false) {
                    continue;
                }
            }

            if (! isset($supplierGroups[$supId])) {
                $supplierGroups[$supId] = [
                    'id' => $supId,
                    'name' => $supName,
                    'deliveries' => 0,
                    'on_time' => 0,
                    'delayed' => 0,
                    'partial' => 0,
                    'spend' => 0.0,
                    'ordered' => 0,
                    'received' => 0,
                    'defect' => 0,
                    'lead_time' => 0,
                    'delay_days' => 0,
                ];
            }

            $status = (string) $del['delivery_status'];
            $supplierGroups[$supId]['deliveries']++;
            if ($status === 'on_time') {
                $supplierGroups[$supId]['on_time']++;
            } elseif ($status === 'delayed') {
                $supplierGroups[$supId]['delayed']++;
            } elseif ($status === 'partial') {
                $supplierGroups[$supId]['partial']++;
            }
            $supplierGroups[$supId]['spend'] += (float) $del['total_purchase_cost'];
            $supplierGroups[$supId]['ordered'] += (int) $del['ordered_quantity'];
            $supplierGroups[$supId]['received'] += (int) $del['received_quantity'];
            $supplierGroups[$supId]['defect'] += (int) ($del['defect_quantity'] ?? 0);
            $supplierGroups[$supId]['lead_time'] += (int) $del['lead_time_days'];
            $supplierGroups[$supId]['delay_days'] += (int) $del['delay_days'];
        }

        $items = [];
        foreach ($supplierGroups as $sg) {
            $items[] = $this->buildSupplierPerformanceItem($sg);
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
        $deliveries = $this->filterDeliveries(
            $workspaceId,
            dateFrom: $criteria->dateFrom,
            dateTo: $criteria->dateTo,
            supplierId: $criteria->supplierId,
            warehouseId: $criteria->warehouseId,
            status: $criteria->status,
            search: $criteria->search,
        );

        $sortBy = $criteria->sortBy;
        $desc = strtolower($criteria->sortDirection) === 'desc';

        usort($deliveries, function ($a, $b) use ($sortBy, $desc) {
            $valA = match ($sortBy) {
                'expected_delivery_date' => $a['expected_delivery_date'],
                'actual_delivery_date' => $a['actual_delivery_date'] ?? '',
                'lead_time_days' => $a['lead_time_days'],
                'delay_days' => $a['delay_days'],
                'total_purchase_cost' => $a['total_purchase_cost'],
                default => $a['order_date'],
            };

            $valB = match ($sortBy) {
                'expected_delivery_date' => $b['expected_delivery_date'],
                'actual_delivery_date' => $b['actual_delivery_date'] ?? '',
                'lead_time_days' => $b['lead_time_days'],
                'delay_days' => $b['delay_days'],
                'total_purchase_cost' => $b['total_purchase_cost'],
                default => $b['order_date'],
            };

            $cmp = is_string($valA) ? strcmp($valA, (string) $valB) : ($valA <=> $valB);

            return $desc ? -$cmp : $cmp;
        });

        $total = count($deliveries);
        $page = max(1, $criteria->page);
        $perPage = max(1, $criteria->perPage);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $offset = ($page - 1) * $perPage;
        $pagedDeliveries = array_slice($deliveries, $offset, $perPage);

        $items = array_map(function ($del) {
            return new SupplierDeliveryItemDto(
                id: (string) $del['id'],
                orderDate: (string) $del['order_date'],
                expectedDeliveryDate: (string) $del['expected_delivery_date'],
                actualDeliveryDate: $del['actual_delivery_date'] ? (string) $del['actual_delivery_date'] : null,
                supplierId: (string) $del['supplier_id'],
                supplierName: (string) $del['supplier_name'],
                productId: (string) $del['product_id'],
                productName: (string) $del['product_name'],
                productSku: (string) $del['product_sku'],
                warehouseId: (string) $del['warehouse_id'],
                warehouseName: (string) $del['warehouse_name'],
                orderedQuantity: (int) $del['ordered_quantity'],
                receivedQuantity: (int) $del['received_quantity'],
                defectQuantity: (int) ($del['defect_quantity'] ?? 0),
                unitPurchaseCost: (float) $del['unit_purchase_cost'],
                totalPurchaseCost: (float) $del['total_purchase_cost'],
                deliveryStatus: (string) $del['delivery_status'],
                leadTimeDays: (int) $del['lead_time_days'],
                delayDays: (int) $del['delay_days'],
            );
        }, $pagedDeliveries);

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
        $deliveries = $this->filterDeliveries($workspaceId);

        $suppliers = [];
        $warehouses = [];
        $minDate = '2099-12-31';
        $maxDate = '1970-01-01';

        foreach ($deliveries as $del) {
            $sId = (string) $del['supplier_id'];
            $sName = (string) $del['supplier_name'];
            if (! isset($suppliers[$sId])) {
                $suppliers[$sId] = ['id' => $sId, 'name' => $sName];
            }

            $wId = (string) $del['warehouse_id'];
            $wName = (string) $del['warehouse_name'];
            if (! isset($warehouses[$wId])) {
                $warehouses[$wId] = ['id' => $wId, 'name' => $wName];
            }

            $od = (string) $del['order_date'];
            if ($od < $minDate) {
                $minDate = $od;
            }
            if ($od > $maxDate) {
                $maxDate = $od;
            }
        }

        if (empty($deliveries)) {
            $minDate = '2025-01-01';
            $maxDate = '2025-12-31';
        }

        return new SupplierFilterOptionsDto(
            suppliers: array_values($suppliers),
            warehouses: array_values($warehouses),
            statuses: [
                ['value' => 'on_time', 'label' => 'В срок'],
                ['value' => 'delayed', 'label' => 'С задержкой'],
                ['value' => 'partial', 'label' => 'Частично'],
            ],
            minDate: $minDate,
            maxDate: $maxDate,
        );
    }

    /**
     * @param  array<string, mixed>  $sg
     */
    private function buildSupplierPerformanceItem(array $sg): SupplierPerformanceItemDto
    {
        $tot = $sg['deliveries'];
        $onTime = $sg['on_time'];
        $delayed = $sg['delayed'];
        $partial = $sg['partial'];
        $ord = $sg['ordered'];
        $rec = $sg['received'];
        $def = $sg['defect'];

        $onTimeRate = SupplierMetrics::calculateOnTimeRate($onTime, $tot);
        $delayRate = SupplierMetrics::calculateDelayRate($delayed, $tot);
        $fulfillment = SupplierMetrics::calculateFulfillmentRate($rec, $ord);
        $defectRate = SupplierMetrics::calculateDefectRate($def, $rec);
        $avgLead = SupplierMetrics::calculateAverageLeadTime($sg['lead_time'], $tot);
        $avgDelay = SupplierMetrics::calculateAverageDelayDays($sg['delay_days'], $delayed);
        $score = SupplierMetrics::calculateReliabilityScore($onTimeRate, $fulfillment, $defectRate);
        $tier = SupplierMetrics::classifyReliabilityTier($score);

        return new SupplierPerformanceItemDto(
            supplierId: $sg['id'],
            supplierName: $sg['name'],
            totalDeliveries: $tot,
            onTimeDeliveries: $onTime,
            delayedDeliveries: $delayed,
            partialDeliveries: $partial,
            totalSpend: round($sg['spend'], 2),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function filterDeliveries(
        string $workspaceId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $supplierId = null,
        ?string $warehouseId = null,
        ?string $status = null,
        ?string $search = null,
    ): array {
        return array_values(array_filter($this->deliveries, function ($del) use ($workspaceId, $dateFrom, $dateTo, $supplierId, $warehouseId, $status, $search) {
            if ($del['workspace_id'] !== $workspaceId) {
                return false;
            }
            if ($dateFrom !== null && $del['order_date'] < $dateFrom) {
                return false;
            }
            if ($dateTo !== null && $del['order_date'] > $dateTo) {
                return false;
            }
            if ($supplierId !== null && $del['supplier_id'] !== $supplierId) {
                return false;
            }
            if ($warehouseId !== null && $del['warehouse_id'] !== $warehouseId) {
                return false;
            }
            if ($status !== null && $del['delivery_status'] !== $status) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $pName = (string) ($del['product_name'] ?? '');
                $sName = (string) ($del['supplier_name'] ?? '');
                $sku = (string) ($del['product_sku'] ?? '');
                if (stripos($pName, $search) === false && stripos($sName, $search) === false && stripos($sku, $search) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function seedDefaultDeliveries(): void
    {
        $suppliers = [
            ['id' => 'sup-bosch', 'name' => 'Bosch Automotive'],
            ['id' => 'sup-mann', 'name' => 'MANN+HUMMEL Filter'],
            ['id' => 'sup-brembo', 'name' => 'Brembo Brake Systems'],
            ['id' => 'sup-ngk', 'name' => 'NGK Spark Plugs'],
            ['id' => 'sup-valeo', 'name' => 'Valeo Electrical'],
        ];

        $warehouses = [
            ['id' => 'wh-central', 'name' => 'Центральный хаб (Москва)'],
            ['id' => 'wh-spb', 'name' => 'Северо-Западный склад (СПб)'],
        ];

        $products = [
            ['id' => 'prod-1', 'name' => 'Свеча зажигания Platinum', 'sku' => 'SP-PL-001', 'cost' => 450.0],
            ['id' => 'prod-2', 'name' => 'Фильтр масляный Pro', 'sku' => 'OF-PR-002', 'cost' => 650.0],
            ['id' => 'prod-3', 'name' => 'Тормозные колодки Ceramic', 'sku' => 'BP-CE-003', 'cost' => 2200.0],
            ['id' => 'prod-4', 'name' => 'Диск тормозной вентилируемый', 'sku' => 'BD-VN-004', 'cost' => 3800.0],
            ['id' => 'prod-5', 'name' => 'Генератор 120A', 'sku' => 'AL-12-005', 'cost' => 12500.0],
        ];

        $statuses = ['on_time', 'on_time', 'on_time', 'delayed', 'partial'];

        $id = 1;
        foreach (['ws-1', 'ws-2'] as $ws) {
            for ($month = 1; $month <= 12; $month++) {
                $monthStr = sprintf('2025-%02d', $month);
                foreach ([5, 12, 19, 26] as $day) {
                    $orderDate = sprintf('%s-%02d', $monthStr, $day);
                    $sup = $suppliers[($id + $month) % count($suppliers)];
                    $wh = $warehouses[$id % count($warehouses)];
                    $prod = $products[($id * 2) % count($products)];
                    $status = $statuses[($id + $day) % count($statuses)];

                    $leadDays = 5 + ($id % 7);
                    $delayDays = match ($status) {
                        'delayed' => 3 + ($id % 5),
                        'partial' => 1,
                        default => 0,
                    };

                    $expectedDate = date('Y-m-d', strtotime("{$orderDate} + {$leadDays} days"));
                    $actualDate = date('Y-m-d', strtotime("{$expectedDate} + {$delayDays} days"));

                    $orderedQty = 50 + (($id * 17) % 150);
                    $receivedQty = match ($status) {
                        'partial' => (int) round($orderedQty * 0.85),
                        default => $orderedQty,
                    };

                    $defectQty = match ($status) {
                        'delayed', 'partial' => ($id % 2 === 0) ? 2 : 0,
                        default => 0,
                    };

                    $unitCost = $prod['cost'];
                    $totalCost = round($unitCost * $receivedQty, 2);

                    $this->deliveries[] = [
                        'id' => sprintf('del-%s-%04d', $ws, $id),
                        'workspace_id' => $ws,
                        'supplier_id' => $sup['id']."-{$ws}",
                        'supplier_name' => $sup['name'],
                        'warehouse_id' => $wh['id']."-{$ws}",
                        'warehouse_name' => $wh['name'],
                        'product_id' => $prod['id']."-{$ws}",
                        'product_name' => $prod['name'],
                        'product_sku' => $prod['sku'],
                        'order_date' => $orderDate,
                        'expected_delivery_date' => $expectedDate,
                        'actual_delivery_date' => $actualDate,
                        'ordered_quantity' => $orderedQty,
                        'received_quantity' => $receivedQty,
                        'defect_quantity' => $defectQty,
                        'unit_purchase_cost' => $unitCost,
                        'total_purchase_cost' => $totalCost,
                        'delivery_status' => $status,
                        'lead_time_days' => $leadDays,
                        'delay_days' => $delayDays,
                    ];
                    $id++;
                }
            }
        }
    }
}
