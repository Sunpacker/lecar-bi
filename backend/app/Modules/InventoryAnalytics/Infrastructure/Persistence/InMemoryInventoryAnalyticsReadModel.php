<?php

declare(strict_types=1);

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
use Carbon\Carbon;

final class InMemoryInventoryAnalyticsReadModel implements InventoryAnalyticsReadModelInterface
{
    /**
     * @var array<int, InventoryItemDto>
     */
    private array $items = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $abcXyzRawProducts = [];

    public function __construct()
    {
        $this->seedDefaultItems();
        $this->seedDefaultAbcXyzProducts();
    }

    /**
     * @param  array<int, InventoryItemDto>  $items
     */
    public function seedItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @param  list<array<string, mixed>>  $rawProducts
     */
    public function seedAbcXyzProducts(array $rawProducts): void
    {
        $this->abcXyzRawProducts = $rawProducts;
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
                'wh-msk-central',
                'Центральный склад Москва',
                'WH-MSK-01',
                6,
                22400.0,
                2,
                1,
                0
            ),
            new WarehouseStockDto(
                'wh-spb-north',
                'Логистический хаб СПб',
                'WH-SPB-01',
                60,
                221000.0,
                1,
                0,
                0
            ),
            new WarehouseStockDto(
                'wh-sam-volga',
                'Региональный склад Самара',
                'WH-SAM-01',
                40,
                29250.0,
                1,
                0,
                0
            ),
            new WarehouseStockDto(
                'wh-ekb-ural',
                'Уральский распредцентр',
                'WH-EKB-01',
                170,
                1476000.0,
                1,
                0,
                1
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
            averageDaysOfStock: 68.5,
            healthBreakdown: $healthBreakdown,
            warehouses: $warehouses,
            asOfDate: '2025-12-31'
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
                $searchLower = mb_strtolower($criteria->search);
                $nameLower = mb_strtolower($item->productName);
                $skuLower = mb_strtolower($item->productSku);
                if (! str_contains($nameLower, $searchLower) && ! str_contains($skuLower, $searchLower)) {
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

    public function getAbcXyzSummary(string $workspaceId, AbcXyzSummaryCriteriaDto $criteria): AbcXyzSummaryDto
    {
        $filtered = $this->filterRawProducts($this->abcXyzRawProducts, $criteria->warehouseId, $criteria->categoryId, $criteria->supplierId);
        $analysis = AbcXyzCalculator::analyze($filtered);

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

        $endDate = '2025-12-31';
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
        $filtered = $this->filterRawProducts($this->abcXyzRawProducts, $criteria->warehouseId, $criteria->categoryId, $criteria->supplierId);
        $analysis = AbcXyzCalculator::analyze($filtered);

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
            categories: [
                ['id' => 'cat-tires-wheels', 'name' => 'Шины и диски', 'code' => 'TIRES'],
                ['id' => 'cat-brakes', 'name' => 'Тормозная система', 'code' => 'BRAKES'],
                ['id' => 'cat-oils-fluids', 'name' => 'Масла и автохимия', 'code' => 'FLUIDS'],
                ['id' => 'cat-filters', 'name' => 'Фильтры', 'code' => 'FILTERS'],
            ],
            suppliers: [
                ['id' => 'sup-eurotech', 'name' => 'EuroTech Components Ltd'],
                ['id' => 'sup-vostok', 'name' => 'Восток Авто Дистрибьюшн'],
                ['id' => 'sup-rusauto', 'name' => 'РусАвто Импорт'],
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function filterRawProducts(array $raw, ?string $warehouseId, ?string $categoryId, ?string $supplierId): array
    {
        return array_values(array_filter($raw, function ($item) use ($warehouseId, $categoryId, $supplierId) {
            if ($warehouseId !== null && isset($item['warehouse_id']) && $item['warehouse_id'] !== $warehouseId) {
                return false;
            }
            if ($categoryId !== null && isset($item['category_id']) && $item['category_id'] !== $categoryId) {
                return false;
            }
            if ($supplierId !== null && isset($item['supplier_id']) && $item['supplier_id'] !== $supplierId) {
                return false;
            }

            return true;
        }));
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

    private function seedDefaultAbcXyzProducts(): void
    {
        $this->abcXyzRawProducts = [
            [
                'id' => 'item-1',
                'product_id' => 'prod-conti-wint-16',
                'product_name' => 'Шина зимняя Continental WinterContact TS 870 205/55 R16',
                'product_sku' => 'TIRE-CONTI-WINT-16',
                'category_id' => 'cat-tires-wheels',
                'category_name' => 'Шины и диски',
                'brand_name' => 'Continental',
                'supplier_id' => 'sup-eurotech',
                'supplier_name' => 'EuroTech Components Ltd',
                'warehouse_id' => 'wh-msk-central',
                'revenue' => 150000.0,
                'units_sold' => 20,
                'period_sales' => [7.0, 6.0, 7.0],
                'current_stock' => 0,
                'inventory_value' => 0.0,
            ],
            [
                'id' => 'item-2',
                'product_id' => 'prod-michelin-primacy-17',
                'product_name' => 'Шина летняя Michelin Primacy 4 225/50 R17',
                'product_sku' => 'TIRE-MICH-PRIM-17',
                'category_id' => 'cat-tires-wheels',
                'category_name' => 'Шины и диски',
                'brand_name' => 'Michelin',
                'supplier_id' => 'sup-eurotech',
                'supplier_name' => 'EuroTech Components Ltd',
                'warehouse_id' => 'wh-ekb-ural',
                'revenue' => 100000.0,
                'units_sold' => 10,
                'period_sales' => [3.0, 4.0, 3.0],
                'current_stock' => 170,
                'inventory_value' => 1476000.0,
            ],
            [
                'id' => 'item-3',
                'product_id' => 'prod-castrol-edge-5w30',
                'product_name' => 'Масло моторное Castrol EDGE 5W-30 LL 4л',
                'product_sku' => 'OIL-CAST-EDGE-5W30-4L',
                'category_id' => 'cat-oils-fluids',
                'category_name' => 'Масла и автохимия',
                'brand_name' => 'Castrol',
                'supplier_id' => 'sup-vostok',
                'supplier_name' => 'Восток Авто Дистрибьюшн',
                'warehouse_id' => 'wh-spb-north',
                'revenue' => 35000.0,
                'units_sold' => 10,
                'period_sales' => [3.0, 3.0, 4.0],
                'current_stock' => 60,
                'inventory_value' => 221000.0,
            ],
            [
                'id' => 'item-4',
                'product_id' => 'prod-brembo-pad-front',
                'product_name' => 'Колодки тормозные передние Brembo P 85 020',
                'product_sku' => 'BRAKE-BREMBO-PAD-F',
                'category_id' => 'cat-brakes',
                'category_name' => 'Тормозная система',
                'brand_name' => 'Brembo',
                'supplier_id' => 'sup-eurotech',
                'supplier_name' => 'EuroTech Components Ltd',
                'warehouse_id' => 'wh-msk-central',
                'revenue' => 10000.0,
                'units_sold' => 4,
                'period_sales' => [1.0, 2.0, 1.0],
                'current_stock' => 6,
                'inventory_value' => 22400.0,
            ],
            [
                'id' => 'item-5',
                'product_id' => 'prod-mann-filter-w712',
                'product_name' => 'Фильтр масляный Mann-Filter W 712/94',
                'product_sku' => 'FILT-MANN-W712',
                'category_id' => 'cat-filters',
                'category_name' => 'Фильтры',
                'brand_name' => 'Mann-Filter',
                'supplier_id' => 'sup-rusauto',
                'supplier_name' => 'РусАвто Импорт',
                'warehouse_id' => 'wh-sam-volga',
                'revenue' => 5000.0,
                'units_sold' => 8,
                'period_sales' => [2.0, 4.0, 2.0],
                'current_stock' => 40,
                'inventory_value' => 29250.0,
            ],
        ];
    }
}
