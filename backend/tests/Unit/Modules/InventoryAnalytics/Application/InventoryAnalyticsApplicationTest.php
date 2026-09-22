<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Application;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\StockHealthBreakdownDto;
use App\Modules\InventoryAnalytics\Application\Dtos\WarehouseStockDto;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryAnalyticsApplicationTest extends TestCase
{
    #[Test]
    public function get_inventory_summary_handler_delegates_to_read_model(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedDto = new InventorySummaryDto(
            totalItems: 10,
            totalQuantityOnHand: 500,
            totalQuantityReserved: 50,
            totalQuantityAvailable: 450,
            totalInventoryValue: 120000.0,
            criticalCount: 2,
            overstockCount: 1,
            outOfStockCount: 1,
            optimalCount: 6,
            averageDaysOfStock: 25.5,
            healthBreakdown: [
                new StockHealthBreakdownDto('optimal', 'В норме', 6, 80000.0, 0.6667),
            ],
            warehouses: [
                new WarehouseStockDto('wh-1', 'Основной склад', 'WH-01', 500, 120000.0, 10, 2, 1),
            ],
            asOfDate: '2025-12-31',
        );

        $readModel->expects(self::once())
            ->method('getInventorySummary')
            ->with('ws-1', self::callback(fn (InventorySummaryCriteriaDto $c) => $c->warehouseId === 'wh-1' && $c->asOfDate === '2025-12-31'))
            ->willReturn($expectedDto);

        $handler = new GetInventorySummaryHandler($readModel);
        $result = $handler->handle(new GetInventorySummaryQuery('ws-1', 'wh-1', '2025-12-31'));

        self::assertSame(500, $result->totalQuantityOnHand);
        self::assertSame(120000.0, $result->totalInventoryValue);
        self::assertSame('2025-12-31', $result->asOfDate);
        self::assertCount(1, $result->healthBreakdown);
        self::assertCount(1, $result->warehouses);
    }

    #[Test]
    public function get_inventory_items_handler_normalizes_criteria_and_delegates(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedPaginated = new InventoryItemsPaginatedDto(
            items: [
                new InventoryItemDto(
                    id: 'inv-1',
                    productId: 'prod-1',
                    productName: 'Шина летняя',
                    productSku: 'SKU-001',
                    categoryId: 'cat-1',
                    categoryName: 'Шины',
                    warehouseId: 'wh-1',
                    warehouseName: 'Москва Склад',
                    warehouseCode: 'WH-MSK',
                    quantityOnHand: 40,
                    quantityReserved: 5,
                    quantityAvailable: 35,
                    unitCost: 3500.0,
                    inventoryValue: 140000.0,
                    salesVelocity: 1.5,
                    daysOfStock: 23.3,
                    stockHealth: 'optimal',
                    stockHealthLabel: 'В норме',
                    safetyStock: 15,
                    reorderPoint: 30,
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            totalPages: 1,
        );

        $readModel->expects(self::once())
            ->method('getInventoryItems')
            ->with('ws-1', self::callback(fn (InventoryItemsCriteriaDto $c) => $c->page === 1 && $c->perPage === 20 && $c->sortBy === 'quantity_available'))
            ->willReturn($expectedPaginated);

        $handler = new GetInventoryItemsHandler($readModel);
        $result = $handler->handle(new GetInventoryItemsQuery(
            workspaceId: 'ws-1',
            warehouseId: null,
            stockHealth: null,
            search: null,
            page: 1,
            perPage: 20,
            sortBy: 'quantity_available',
            sortDirection: 'asc',
        ));

        self::assertCount(1, $result->items);
        self::assertSame(1, $result->total);
        self::assertSame('Шина летняя', $result->items[0]->productName);
    }

    #[Test]
    public function get_inventory_filter_options_handler_delegates(): void
    {
        $readModel = $this->createMock(InventoryAnalyticsReadModelInterface::class);
        $expectedFilters = new InventoryFilterOptionsDto(
            warehouses: [['id' => 'wh-1', 'name' => 'Москва', 'code' => 'WH-MSK']],
            statuses: [['value' => 'critical', 'label' => 'Критический']],
            latestSnapshotDate: '2025-12-31',
        );

        $readModel->expects(self::once())
            ->method('getFilterOptions')
            ->with('ws-1')
            ->willReturn($expectedFilters);

        $handler = new GetInventoryFilterOptionsHandler($readModel);
        $result = $handler->handle(new GetInventoryFilterOptionsQuery('ws-1'));

        self::assertSame('2025-12-31', $result->latestSnapshotDate);
        self::assertCount(1, $result->warehouses);
        self::assertCount(1, $result->statuses);
    }
}
