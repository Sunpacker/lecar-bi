<?php

namespace Tests\Unit\Modules\Dashboard\Domain;

use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Exceptions\InvalidFilterException;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SavedViewDomainTest extends TestCase
{
    public function test_saved_view_id_validation_and_generation(): void
    {
        $id = SavedViewId::generate();
        self::assertNotEmpty($id->value());

        $sameId = new SavedViewId($id->value());
        self::assertTrue($id->equals($sameId));
        self::assertSame($id->value(), (string) $id);

        $this->expectException(InvalidArgumentException::class);
        new SavedViewId('invalid-uuid');
    }

    public function test_dashboard_filters_date_range_validation(): void
    {
        $validFilters = new DashboardFilters(
            dateRange: 'custom',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: 'reg-1',
        );
        self::assertSame('2026-01-01', $validFilters->dateFrom);
        self::assertSame('2026-01-31', $validFilters->dateTo);

        $this->expectException(InvalidFilterException::class);
        new DashboardFilters(
            dateFrom: '2026-02-01',
            dateTo: '2026-01-01',
        );
    }

    public function test_dashboard_filters_dataset_compatibility_resolver(): void
    {
        $filters = new DashboardFilters(
            dateRange: '30d',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: 'reg-1',
            warehouseId: 'wh-1',
            stockHealth: 'low_stock',
        );

        $salesFilters = $filters->forSalesDataset();
        self::assertSame('cat-1', $salesFilters->categoryId);
        self::assertSame('reg-1', $salesFilters->regionId);
        self::assertNull($salesFilters->warehouseId);
        self::assertNull($salesFilters->stockHealth);

        $inventoryFilters = $filters->forInventoryDataset();
        self::assertSame('wh-1', $inventoryFilters->warehouseId);
        self::assertSame('low_stock', $inventoryFilters->stockHealth);
        self::assertNull($inventoryFilters->regionId);

        $array = $filters->toArray();
        self::assertSame('30d', $array['date_range']);
        self::assertSame('wh-1', $array['warehouse_id']);

        $restored = DashboardFilters::fromArray($array);
        self::assertSame('30d', $restored->dateRange);
        self::assertSame('wh-1', $restored->warehouseId);
    }

    public function test_saved_view_lifecycle(): void
    {
        $dashboardId = DashboardId::generate();
        $viewId = SavedViewId::generate();
        $filters = new DashboardFilters(dateRange: '90d');

        $view = new SavedView(
            id: $viewId,
            dashboardId: $dashboardId,
            name: 'Default Q1 View',
            filters: $filters,
            isDefault: false,
        );

        self::assertSame($viewId, $view->id());
        self::assertSame($dashboardId, $view->dashboardId());
        self::assertSame('Default Q1 View', $view->name());
        self::assertFalse($view->isDefault());

        $view->rename('Updated Q1 View');
        self::assertSame('Updated Q1 View', $view->name());

        $view->markAsDefault(true);
        self::assertTrue($view->isDefault());

        $newFilters = new DashboardFilters(dateRange: '180d');
        $view->updateFilters($newFilters);
        self::assertSame('180d', $view->filters()->dateRange);
    }

    public function test_empty_saved_view_name_throws_exception(): void
    {
        $dashboardId = DashboardId::generate();
        $viewId = SavedViewId::generate();
        $filters = new DashboardFilters(dateRange: '30d');

        $this->expectException(InvalidArgumentException::class);
        new SavedView(
            id: $viewId,
            dashboardId: $dashboardId,
            name: '   ',
            filters: $filters,
        );
    }
}
