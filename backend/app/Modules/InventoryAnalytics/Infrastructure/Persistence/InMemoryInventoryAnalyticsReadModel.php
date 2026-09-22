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

final class InMemoryInventoryAnalyticsReadModel implements InventoryAnalyticsReadModelInterface
{
    /**
     * @var array<int, InventoryItemDto>
     */
    private array $items = [];

    public function __construct()
    {
        $this->seedDefaultItems();
    }

    /**
     * @param  array<int, InventoryItemDto>  $items
     */
    public function seedItems(array $items): void
    {
        $this->items = $items;
    }

    public function getInventorySummary(string $workspaceId, InventorySummaryCriteriaDto $criteria): InventorySummaryDto
    {
        $filteredItems = array_values(array_filter(
            $this->items,
            fn (InventoryItemDto $item) => $criteria->warehouseId === null || $item->warehouseId === $criteria->warehouseId
        ));

        $totalItems = count($filteredItems);
        $totalOnHand = 0;
        $totalReserved = 0;
        $totalAvailable = 0;
        $totalValue = 0.0;
        $criticalCount = 0;
        $overstockCount = 0;
        $outOfStockCount = 0;
        $optimalCount = 0;

        $healthValues = [
            'optimal' => 0.0,
            'critical' => 0.0,
            'overstock' => 0.0,
            'out_of_stock' => 0.0,
        ];
        $healthCounts = [
            'optimal' => 0,
            'critical' => 0,
            'overstock' => 0,
            'out_of_stock' => 0,
        ];

        foreach ($filteredItems as $item) {
            $totalOnHand += $item->quantityOnHand;
            $totalReserved += $item->quantityReserved;
            $totalAvailable += $item->quantityAvailable;
            $totalValue += $item->inventoryValue;

            match ($item->stockHealth) {
                'critical' => $criticalCount++,
                'overstock' => $overstockCount++,
                'out_of_stock' => $outOfStockCount++,
                default => $optimalCount++,
            };

            $status = $item->stockHealth;
            if (isset($healthCounts[$status])) {
                $healthCounts[$status]++;
                $healthValues[$status] += $item->inventoryValue;
            }
        }

        $healthBreakdown = [
            new StockHealthBreakdownDto(
                'optimal',
                'В норме',
                $healthCounts['optimal'],
                $healthValues['optimal'],
                $totalItems > 0 ? round($healthCounts['optimal'] / $totalItems, 4) : 0.0
            ),
            new StockHealthBreakdownDto(
                'critical',
                'Критический',
                $healthCounts['critical'],
                $healthValues['critical'],
                $totalItems > 0 ? round($healthCounts['critical'] / $totalItems, 4) : 0.0
            ),
            new StockHealthBreakdownDto(
                'overstock',
                'Избыток',
                $healthCounts['overstock'],
                $healthValues['overstock'],
                $totalItems > 0 ? round($healthCounts['overstock'] / $totalItems, 4) : 0.0
            ),
            new StockHealthBreakdownDto(
                'out_of_stock',
                'Дефицит',
                $healthCounts['out_of_stock'],
                $healthValues['out_of_stock'],
                $totalItems > 0 ? round($healthCounts['out_of_stock'] / $totalItems, 4) : 0.0
            ),
        ];

        $warehouses = [
            new WarehouseStockDto(
                warehouseId: 'wh-msk-central',
                warehouseName: 'Центральный склад Москва',
                warehouseCode: 'WH-MSK-01',
                totalQuantity: 450,
                totalValue: 1250000.0,
                itemsCount: 15,
                criticalCount: 2,
                overstockCount: 1
            ),
            new WarehouseStockDto(
                warehouseId: 'wh-spb-north',
                warehouseName: 'Логистический хаб СПб',
                warehouseCode: 'WH-SPB-01',
                totalQuantity: 320,
                totalValue: 850000.0,
                itemsCount: 12,
                criticalCount: 1,
                overstockCount: 2
            ),
            new WarehouseStockDto(
                warehouseId: 'wh-sam-volga',
                warehouseName: 'Региональный склад Самара',
                warehouseCode: 'WH-SAM-01',
                totalQuantity: 180,
                totalValue: 420000.0,
                itemsCount: 10,
                criticalCount: 1,
                overstockCount: 0
            ),
            new WarehouseStockDto(
                warehouseId: 'wh-ekb-ural',
                warehouseName: 'Уральский распредцентр',
                warehouseCode: 'WH-EKB-01',
                totalQuantity: 250,
                totalValue: 680000.0,
                itemsCount: 11,
                criticalCount: 0,
                overstockCount: 3
            ),
        ];

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
            averageDaysOfStock: 28.4,
            healthBreakdown: $healthBreakdown,
            warehouses: $warehouses,
            asOfDate: $criteria->asOfDate ?? '2025-12-31',
        );
    }

    public function getInventoryItems(string $workspaceId, InventoryItemsCriteriaDto $criteria): InventoryItemsPaginatedDto
    {
        $filtered = array_values(array_filter($this->items, function (InventoryItemDto $item) use ($criteria) {
            if ($criteria->warehouseId !== null && $item->warehouseId !== $criteria->warehouseId) {
                return false;
            }

            if ($criteria->stockHealth !== null && $item->stockHealth !== $criteria->stockHealth) {
                return false;
            }

            if ($criteria->search !== null && $criteria->search !== '') {
                $search = mb_strtolower($criteria->search);
                $nameMatch = str_contains(mb_strtolower($item->productName), $search);
                $skuMatch = str_contains(mb_strtolower($item->productSku), $search);
                if (! $nameMatch && ! $skuMatch) {
                    return false;
                }
            }

            return true;
        }));

        usort($filtered, function (InventoryItemDto $a, InventoryItemDto $b) use ($criteria) {
            $direction = $criteria->sortDirection === 'desc' ? -1 : 1;

            return match ($criteria->sortBy) {
                'quantity_on_hand' => ($a->quantityOnHand <=> $b->quantityOnHand) * $direction,
                'quantity_available' => ($a->quantityAvailable <=> $b->quantityAvailable) * $direction,
                'inventory_value' => ($a->inventoryValue <=> $b->inventoryValue) * $direction,
                'sales_velocity' => ($a->salesVelocity <=> $b->salesVelocity) * $direction,
                'days_of_stock' => (($a->daysOfStock ?? 999999) <=> ($b->daysOfStock ?? 999999)) * $direction,
                default => (strcmp($a->productName, $b->productName)) * $direction,
            };
        });

        $total = count($filtered);
        $perPage = max(1, $criteria->perPage);
        $page = max(1, $criteria->page);
        $totalPages = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filtered, $offset, $perPage);

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
        return new InventoryFilterOptionsDto(
            warehouses: [
                ['id' => 'wh-msk-central', 'name' => 'Центральный склад Москва', 'code' => 'WH-MSK-01'],
                ['id' => 'wh-spb-north', 'name' => 'Логистический хаб СПб', 'code' => 'WH-SPB-01'],
                ['id' => 'wh-sam-volga', 'name' => 'Региональный склад Самара', 'code' => 'WH-SAM-01'],
                ['id' => 'wh-ekb-ural', 'name' => 'Уральский распредцентр', 'code' => 'WH-EKB-01'],
            ],
            statuses: [
                ['value' => 'optimal', 'label' => 'В норме'],
                ['value' => 'critical', 'label' => 'Критический'],
                ['value' => 'overstock', 'label' => 'Избыток'],
                ['value' => 'out_of_stock', 'label' => 'Дефицит'],
            ],
            latestSnapshotDate: '2025-12-31',
        );
    }

    private function seedDefaultItems(): void
    {
        $this->items = [
            new InventoryItemDto(
                id: 'inv-1',
                productId: 'prod-conti-wint-16',
                productName: 'Шина зимняя Continental WinterContact TS 870 205/55 R16',
                productSku: 'TIRE-CONTI-WINT-16',
                categoryId: 'cat-tires-wheels',
                categoryName: 'Шины и диски',
                warehouseId: 'wh-msk-central',
                warehouseName: 'Центральный склад Москва',
                warehouseCode: 'WH-MSK-01',
                quantityOnHand: 0,
                quantityReserved: 0,
                quantityAvailable: 0,
                unitCost: 6500.0,
                inventoryValue: 0.0,
                salesVelocity: 2.8,
                daysOfStock: 0.0,
                stockHealth: 'out_of_stock',
                stockHealthLabel: 'Дефицит',
                safetyStock: 30,
                reorderPoint: 60
            ),
            new InventoryItemDto(
                id: 'inv-2',
                productId: 'prod-brembo-pad-front',
                productName: 'Колодки тормозные передние Brembo P 85 020',
                productSku: 'BRAKE-BREMBO-PAD-F',
                categoryId: 'cat-brakes',
                categoryName: 'Тормозная система',
                warehouseId: 'wh-msk-central',
                warehouseName: 'Центральный склад Москва',
                warehouseCode: 'WH-MSK-01',
                quantityOnHand: 8,
                quantityReserved: 2,
                quantityAvailable: 6,
                unitCost: 2800.0,
                inventoryValue: 22400.0,
                salesVelocity: 1.2,
                daysOfStock: 5.0,
                stockHealth: 'critical',
                stockHealthLabel: 'Критический',
                safetyStock: 15,
                reorderPoint: 30
            ),
            new InventoryItemDto(
                id: 'inv-3',
                productId: 'prod-michelin-primacy-17',
                productName: 'Шина летняя Michelin Primacy 4 225/50 R17',
                productSku: 'TIRE-MICH-PRIM-17',
                categoryId: 'cat-tires-wheels',
                categoryName: 'Шины и диски',
                warehouseId: 'wh-ekb-ural',
                warehouseName: 'Уральский распредцентр',
                warehouseCode: 'WH-EKB-01',
                quantityOnHand: 180,
                quantityReserved: 10,
                quantityAvailable: 170,
                unitCost: 8200.0,
                inventoryValue: 1476000.0,
                salesVelocity: 0.8,
                daysOfStock: 212.5,
                stockHealth: 'overstock',
                stockHealthLabel: 'Избыток',
                safetyStock: 30,
                reorderPoint: 60
            ),
            new InventoryItemDto(
                id: 'inv-4',
                productId: 'prod-castrol-edge-5w30',
                productName: 'Масло моторное Castrol EDGE 5W-30 LL 4л',
                productSku: 'OIL-CAST-EDGE-5W30-4L',
                categoryId: 'cat-oils-fluids',
                categoryName: 'Масла и автохимия',
                warehouseId: 'wh-spb-north',
                warehouseName: 'Логистический хаб СПб',
                warehouseCode: 'WH-SPB-01',
                quantityOnHand: 65,
                quantityReserved: 5,
                quantityAvailable: 60,
                unitCost: 3400.0,
                inventoryValue: 221000.0,
                salesVelocity: 2.0,
                daysOfStock: 30.0,
                stockHealth: 'optimal',
                stockHealthLabel: 'В норме',
                safetyStock: 15,
                reorderPoint: 30
            ),
            new InventoryItemDto(
                id: 'inv-5',
                productId: 'prod-mann-filter-w712',
                productName: 'Фильтр масляный Mann-Filter W 712/94',
                productSku: 'FILT-MANN-W712',
                categoryId: 'cat-filters',
                categoryName: 'Фильтры',
                warehouseId: 'wh-sam-volga',
                warehouseName: 'Региональный склад Самара',
                warehouseCode: 'WH-SAM-01',
                quantityOnHand: 45,
                quantityReserved: 5,
                quantityAvailable: 40,
                unitCost: 650.0,
                inventoryValue: 29250.0,
                salesVelocity: 1.5,
                daysOfStock: 26.7,
                stockHealth: 'optimal',
                stockHealthLabel: 'В норме',
                safetyStock: 15,
                reorderPoint: 30
            ),
        ];
    }
}
