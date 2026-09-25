<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\CachedInventoryAnalyticsReadModel;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\CachedSalesAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierSummaryDto;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\CachedSupplierAnalyticsReadModel;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsCacheIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function sales_overview_caches_result_and_invalidates_on_version_bump(): void
    {
        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $versionStore = new AnalyticsDatasetVersionStore;
        $cache = new AnalyticsResultCache(Cache::store('array'));

        $summary1 = new SalesSummaryDto(
            totalRevenue: 1000.0,
            orderCount: 10,
            averageOrderValue: 100.0,
            grossProfit: 300.0,
            marginRate: 30.0
        );
        $overview1 = new SalesOverviewDto(
            summary: $summary1,
            trend: [],
            categories: [],
            regions: []
        );

        $criteria = new SalesFilterCriteriaDto(null, null, null, null, null);

        // Delegate should only be called ONCE for two identical calls
        $mockDelegate->shouldReceive('getSalesOverview')
            ->once()
            ->with('ws-1', $criteria)
            ->andReturn($overview1);

        $cachedReadModel = new CachedSalesAnalyticsReadModel($mockDelegate, $cache, $versionStore);

        // 1. Cold call -> Cache MISS (hits delegate)
        $res1 = $cachedReadModel->getSalesOverview('ws-1', $criteria);
        self::assertSame(1000.0, $res1->summary->totalRevenue);

        // 2. Warm call -> Cache HIT (does NOT hit delegate)
        $res2 = $cachedReadModel->getSalesOverview('ws-1', $criteria);
        self::assertSame(1000.0, $res2->summary->totalRevenue);

        // 3. Different workspace -> Cache MISS (hits delegate with ws-2)
        $summaryWs2 = new SalesSummaryDto(500.0, 5, 100.0, 150.0, 30.0);
        $overviewWs2 = new SalesOverviewDto($summaryWs2, [], [], []);
        $mockDelegate->shouldReceive('getSalesOverview')
            ->once()
            ->with('ws-2', $criteria)
            ->andReturn($overviewWs2);

        $resWs2 = $cachedReadModel->getSalesOverview('ws-2', $criteria);
        self::assertSame(500.0, $resWs2->summary->totalRevenue);

        // 4. Version bump -> Invalidation! Old key unreachable, calls delegate again
        $versionStore->bumpVersion('ws-1', 'sales');

        $summaryUpdated = new SalesSummaryDto(1500.0, 15, 100.0, 450.0, 30.0);
        $overviewUpdated = new SalesOverviewDto($summaryUpdated, [], [], []);
        $mockDelegate->shouldReceive('getSalesOverview')
            ->once()
            ->with('ws-1', $criteria)
            ->andReturn($overviewUpdated);

        $resAfterBump = $cachedReadModel->getSalesOverview('ws-1', $criteria);
        self::assertSame(1500.0, $resAfterBump->summary->totalRevenue);
    }

    #[Test]
    public function sales_records_is_never_cached(): void
    {
        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $versionStore = new AnalyticsDatasetVersionStore;
        $cache = new AnalyticsResultCache(Cache::store('array'));

        $recordsDto = new SalesRecordsPaginatedDto([], 0, 1, 10, 0);
        $criteria = new SalesRecordsCriteriaDto(page: 1, perPage: 10);

        // High-cardinality endpoint: delegate must be called each time
        $mockDelegate->shouldReceive('getSalesRecords')
            ->twice()
            ->with('ws-1', $criteria)
            ->andReturn($recordsDto);

        $cachedReadModel = new CachedSalesAnalyticsReadModel($mockDelegate, $cache, $versionStore);

        $cachedReadModel->getSalesRecords('ws-1', $criteria);
        $cachedReadModel->getSalesRecords('ws-1', $criteria);
    }

    #[Test]
    public function inventory_summary_caches_while_items_bypass_cache(): void
    {
        $mockDelegate = Mockery::mock(InventoryAnalyticsReadModelInterface::class);
        $versionStore = new AnalyticsDatasetVersionStore;
        $cache = new AnalyticsResultCache(Cache::store('array'));

        $summary = new InventorySummaryDto(
            totalItems: 50,
            totalQuantityOnHand: 500,
            totalQuantityReserved: 50,
            totalQuantityAvailable: 450,
            totalInventoryValue: 20000.0,
            criticalCount: 2,
            overstockCount: 5,
            outOfStockCount: 1,
            optimalCount: 42,
            averageDaysOfStock: 22.0,
            healthBreakdown: [],
            warehouses: [],
            asOfDate: '2026-09-25'
        );

        $summaryCriteria = new InventorySummaryCriteriaDto(null, null);

        // Summary is cached (1 call for 2 requests)
        $mockDelegate->shouldReceive('getInventorySummary')
            ->once()
            ->with('ws-1', $summaryCriteria)
            ->andReturn($summary);

        $itemsDto = new InventoryItemsPaginatedDto([], 0, 1, 10, 0);
        $itemsCriteria = new InventoryItemsCriteriaDto(null, null, null, 1, 10, 'quantity_available', 'asc');

        // Items is NOT cached (2 calls for 2 requests)
        $mockDelegate->shouldReceive('getInventoryItems')
            ->twice()
            ->with('ws-1', $itemsCriteria)
            ->andReturn($itemsDto);

        $cachedReadModel = new CachedInventoryAnalyticsReadModel($mockDelegate, $cache, $versionStore);

        self::assertSame(50, $cachedReadModel->getInventorySummary('ws-1', $summaryCriteria)->totalItems);
        self::assertSame(50, $cachedReadModel->getInventorySummary('ws-1', $summaryCriteria)->totalItems);

        $cachedReadModel->getInventoryItems('ws-1', $itemsCriteria);
        $cachedReadModel->getInventoryItems('ws-1', $itemsCriteria);
    }

    #[Test]
    public function supplier_overview_caches_while_deliveries_bypass_cache(): void
    {
        $mockDelegate = Mockery::mock(SupplierAnalyticsReadModelInterface::class);
        $versionStore = new AnalyticsDatasetVersionStore;
        $cache = new AnalyticsResultCache(Cache::store('array'));

        $supSummary = new SupplierSummaryDto(
            totalDeliveries: 150,
            onTimeDeliveries: 140,
            delayedDeliveries: 10,
            partialDeliveries: 0,
            totalSpend: 75000.0,
            totalOrderedQuantity: 1500,
            totalReceivedQuantity: 1450,
            totalDefectQuantity: 5,
            onTimeRate: 94.5,
            delayRate: 5.5,
            fulfillmentRate: 96.6,
            defectRate: 0.3,
            averageLeadTimeDays: 4.2,
            averageDelayDays: 1.1
        );
        $overview = new SupplierOverviewDto(
            summary: $supSummary,
            statusBreakdown: [],
            trends: [],
            topSuppliers: []
        );

        $overviewCriteria = new SupplierOverviewCriteriaDto(null, null, null);

        // Overview is cached (1 call for 2 requests)
        $mockDelegate->shouldReceive('getSupplierOverview')
            ->once()
            ->with('ws-1', $overviewCriteria)
            ->andReturn($overview);

        $deliveriesDto = new SupplierDeliveriesPaginatedDto([], 0, 1, 10, 0);
        $deliveriesCriteria = new SupplierDeliveriesCriteriaDto(page: 1, perPage: 10);

        // Deliveries is NOT cached (2 calls for 2 requests)
        $mockDelegate->shouldReceive('getSupplierDeliveries')
            ->twice()
            ->with('ws-1', $deliveriesCriteria)
            ->andReturn($deliveriesDto);

        $cachedReadModel = new CachedSupplierAnalyticsReadModel($mockDelegate, $cache, $versionStore);

        self::assertSame(150, $cachedReadModel->getSupplierOverview('ws-1', $overviewCriteria)->summary->totalDeliveries);
        self::assertSame(150, $cachedReadModel->getSupplierOverview('ws-1', $overviewCriteria)->summary->totalDeliveries);

        $cachedReadModel->getSupplierDeliveries('ws-1', $deliveriesCriteria);
        $cachedReadModel->getSupplierDeliveries('ws-1', $deliveriesCriteria);
    }
}
