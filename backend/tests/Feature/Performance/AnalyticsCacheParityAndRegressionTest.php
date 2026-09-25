<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\CachedInventoryAnalyticsReadModel;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\CachedSalesAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierSummaryDto;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\CachedSupplierAnalyticsReadModel;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsCacheParityAndRegressionTest extends TestCase
{
    private AnalyticsDatasetVersionStore $versionStore;

    private AnalyticsResultCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->versionStore = new AnalyticsDatasetVersionStore;
        $this->cache = new AnalyticsResultCache(Cache::store('array'));
    }

    #[Test]
    public function sales_overview_maintains_exact_parity_across_disabled_cold_and_warm_cache(): void
    {
        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);

        $expectedOverview = new SalesOverviewDto(
            summary: new SalesSummaryDto(
                totalRevenue: 2500000.50,
                orderCount: 420,
                averageOrderValue: 5952.38,
                grossProfit: 625000.12,
                marginRate: 25.0
            ),
            trend: [
                ['date' => '2026-09-01', 'revenue' => 100000.0, 'order_count' => 15],
            ],
            categories: [
                ['category_id' => 'cat-1', 'category_name' => 'Brakes', 'revenue' => 500000.0, 'percentage' => 20.0],
            ],
            regions: [
                ['region_id' => 'reg-1', 'region_name' => 'Central', 'revenue' => 1500000.0, 'order_count' => 250],
            ]
        );

        $criteria = new SalesFilterCriteriaDto(
            dateFrom: '2026-09-01',
            dateTo: '2026-09-30',
            categoryId: 'cat-1',
            regionId: 'reg-1'
        );

        // Cold + Disabled will call delegate (2 calls total, warm will hit cache)
        $mockDelegate->shouldReceive('getSalesOverview')
            ->twice()
            ->with('ws-parity-1', $criteria)
            ->andReturn($expectedOverview);

        // 1. Cache Disabled (direct delegate call)
        $disabledResult = $mockDelegate->getSalesOverview('ws-parity-1', $criteria);

        // 2. Cold Cache (delegates and writes to cache)
        $cachedModel = new CachedSalesAnalyticsReadModel($mockDelegate, $this->cache, $this->versionStore);
        $coldResult = $cachedModel->getSalesOverview('ws-parity-1', $criteria);

        // 3. Warm Cache (hits cache, delegate NOT called)
        $warmResult = $cachedModel->getSalesOverview('ws-parity-1', $criteria);

        // Parity Assertions
        self::assertEquals($disabledResult, $coldResult, 'Cold cache result must match disabled result exactly');
        self::assertEquals($coldResult, $warmResult, 'Warm cache result must match cold cache result exactly');
        self::assertSame(2500000.50, $warmResult->summary->totalRevenue);
        self::assertSame(420, $warmResult->summary->orderCount);
        self::assertSame(25.0, $warmResult->summary->marginRate);
    }

    #[Test]
    public function inventory_and_abc_xyz_summary_maintain_exact_parity_across_cache_states(): void
    {
        $mockDelegate = Mockery::mock(InventoryAnalyticsReadModelInterface::class);

        $invSummary = new InventorySummaryDto(
            totalItems: 120,
            totalQuantityOnHand: 2500,
            totalQuantityReserved: 150,
            totalQuantityAvailable: 2350,
            totalInventoryValue: 1850000.0,
            criticalCount: 5,
            overstockCount: 12,
            outOfStockCount: 2,
            optimalCount: 101,
            averageDaysOfStock: 28.4,
            healthBreakdown: [],
            warehouses: [],
            asOfDate: '2026-09-25'
        );

        $abcSummary = new AbcXyzSummaryDto(
            totalProducts: 45,
            totalRevenue: 2500000.0,
            totalInventoryValue: 1850000.0,
            matrix: [],
            abcDistribution: [],
            xyzDistribution: [],
            periodDays: 90,
            startDate: '2026-06-27',
            endDate: '2026-09-25'
        );

        $invCriteria = new InventorySummaryCriteriaDto(warehouseId: 'wh-main', asOfDate: '2026-09-25');
        $abcCriteria = new AbcXyzSummaryCriteriaDto(periodDays: 90, warehouseId: 'wh-main');

        $mockDelegate->shouldReceive('getInventorySummary')->twice()->with('ws-parity-1', $invCriteria)->andReturn($invSummary);
        $mockDelegate->shouldReceive('getAbcXyzSummary')->twice()->with('ws-parity-1', $abcCriteria)->andReturn($abcSummary);

        $cachedModel = new CachedInventoryAnalyticsReadModel($mockDelegate, $this->cache, $this->versionStore);

        // Inventory summary parity
        $invDisabled = $mockDelegate->getInventorySummary('ws-parity-1', $invCriteria);
        $invCold = $cachedModel->getInventorySummary('ws-parity-1', $invCriteria);
        $invWarm = $cachedModel->getInventorySummary('ws-parity-1', $invCriteria);
        self::assertEquals($invDisabled, $invCold);
        self::assertEquals($invCold, $invWarm);

        // ABC/XYZ summary parity
        $abcDisabled = $mockDelegate->getAbcXyzSummary('ws-parity-1', $abcCriteria);
        $abcCold = $cachedModel->getAbcXyzSummary('ws-parity-1', $abcCriteria);
        $abcWarm = $cachedModel->getAbcXyzSummary('ws-parity-1', $abcCriteria);
        self::assertEquals($abcDisabled, $abcCold);
        self::assertEquals($abcCold, $abcWarm);
    }

    #[Test]
    public function supplier_overview_maintains_exact_parity_across_cache_states(): void
    {
        $mockDelegate = Mockery::mock(SupplierAnalyticsReadModelInterface::class);

        $supOverview = new SupplierOverviewDto(
            summary: new SupplierSummaryDto(
                totalDeliveries: 85,
                onTimeDeliveries: 80,
                delayedDeliveries: 5,
                partialDeliveries: 0,
                totalSpend: 450000.0,
                totalOrderedQuantity: 1000,
                totalReceivedQuantity: 990,
                totalDefectQuantity: 2,
                onTimeRate: 94.1,
                delayRate: 5.9,
                fulfillmentRate: 99.0,
                defectRate: 0.2,
                averageLeadTimeDays: 5.0,
                averageDelayDays: 1.2
            ),
            statusBreakdown: [],
            trends: [],
            topSuppliers: []
        );

        $supCriteria = new SupplierOverviewCriteriaDto(
            dateFrom: '2026-01-01',
            dateTo: '2026-06-30',
            supplierId: 'sup-1',
            warehouseId: 'wh-1'
        );

        $mockDelegate->shouldReceive('getSupplierOverview')->twice()->with('ws-parity-1', $supCriteria)->andReturn($supOverview);

        $cachedModel = new CachedSupplierAnalyticsReadModel($mockDelegate, $this->cache, $this->versionStore);

        $supDisabled = $mockDelegate->getSupplierOverview('ws-parity-1', $supCriteria);
        $supCold = $cachedModel->getSupplierOverview('ws-parity-1', $supCriteria);
        $supWarm = $cachedModel->getSupplierOverview('ws-parity-1', $supCriteria);

        self::assertEquals($supDisabled, $supCold);
        self::assertEquals($supCold, $supWarm);
        self::assertSame(94.1, $supWarm->summary->onTimeRate);
    }

    #[Test]
    public function filter_options_parity_across_all_three_domains(): void
    {
        $salesMock = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $invMock = Mockery::mock(InventoryAnalyticsReadModelInterface::class);
        $supMock = Mockery::mock(SupplierAnalyticsReadModelInterface::class);

        $salesOpts = new SalesFilterOptionsDto(categories: [], regions: [], minDate: '2026-01-01', maxDate: '2026-09-25');
        $invOpts = new InventoryFilterOptionsDto(warehouses: [], statuses: [], latestSnapshotDate: '2026-09-25', categories: [], suppliers: []);
        $supOpts = new SupplierFilterOptionsDto(suppliers: [], warehouses: [], statuses: ['delivered'], minDate: '2026-01-01', maxDate: '2026-09-25');

        $salesMock->shouldReceive('getFilterOptions')->twice()->with('ws-filters-1')->andReturn($salesOpts);
        $invMock->shouldReceive('getFilterOptions')->twice()->with('ws-filters-1')->andReturn($invOpts);
        $supMock->shouldReceive('getFilterOptions')->twice()->with('ws-filters-1')->andReturn($supOpts);

        $cachedSales = new CachedSalesAnalyticsReadModel($salesMock, $this->cache, $this->versionStore);
        $cachedInv = new CachedInventoryAnalyticsReadModel($invMock, $this->cache, $this->versionStore);
        $cachedSup = new CachedSupplierAnalyticsReadModel($supMock, $this->cache, $this->versionStore);

        // Sales filters parity
        self::assertEquals($salesMock->getFilterOptions('ws-filters-1'), $cachedSales->getFilterOptions('ws-filters-1'));
        self::assertEquals($salesOpts, $cachedSales->getFilterOptions('ws-filters-1'));

        // Inventory filters parity
        self::assertEquals($invMock->getFilterOptions('ws-filters-1'), $cachedInv->getFilterOptions('ws-filters-1'));
        self::assertEquals($invOpts, $cachedInv->getFilterOptions('ws-filters-1'));

        // Supplier filters parity
        self::assertEquals($supMock->getFilterOptions('ws-filters-1'), $cachedSup->getFilterOptions('ws-filters-1'));
        self::assertEquals($supOpts, $cachedSup->getFilterOptions('ws-filters-1'));
    }

    #[Test]
    public function cross_workspace_cache_isolation_strictly_prevents_leakage(): void
    {
        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $criteria = new SalesFilterCriteriaDto;

        $overviewWs1 = new SalesOverviewDto(
            summary: new SalesSummaryDto(100.0, 1, 100.0, 20.0, 20.0),
            trend: [], categories: [], regions: []
        );

        $overviewWs2 = new SalesOverviewDto(
            summary: new SalesSummaryDto(999.0, 9, 111.0, 222.0, 22.2),
            trend: [], categories: [], regions: []
        );

        $mockDelegate->shouldReceive('getSalesOverview')->once()->with('ws-alpha', $criteria)->andReturn($overviewWs1);
        $mockDelegate->shouldReceive('getSalesOverview')->once()->with('ws-beta', $criteria)->andReturn($overviewWs2);

        $cachedModel = new CachedSalesAnalyticsReadModel($mockDelegate, $this->cache, $this->versionStore);

        // 1. Fetch for ws-alpha (cached under ws-alpha)
        $resAlpha1 = $cachedModel->getSalesOverview('ws-alpha', $criteria);
        self::assertSame(100.0, $resAlpha1->summary->totalRevenue);

        // 2. Fetch for ws-beta with identical criteria (must NOT hit ws-alpha cache)
        $resBeta1 = $cachedModel->getSalesOverview('ws-beta', $criteria);
        self::assertSame(999.0, $resBeta1->summary->totalRevenue);

        // 3. Repeated calls hit respective isolated caches
        $resAlpha2 = $cachedModel->getSalesOverview('ws-alpha', $criteria);
        $resBeta2 = $cachedModel->getSalesOverview('ws-beta', $criteria);

        self::assertSame(100.0, $resAlpha2->summary->totalRevenue);
        self::assertSame(999.0, $resBeta2->summary->totalRevenue);
    }

    #[Test]
    public function fail_open_behavior_when_redis_throws_exception(): void
    {
        $mockStore = Mockery::mock(CacheContract::class);
        $failingCache = new AnalyticsResultCache($mockStore);

        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $criteria = new SalesFilterCriteriaDto;

        $overview = new SalesOverviewDto(
            summary: new SalesSummaryDto(500.0, 5, 100.0, 100.0, 20.0),
            trend: [], categories: [], regions: []
        );

        // Cache store throws on get (e.g. Redis connection timeout)
        $mockStore->shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('Redis connection timed out (server unreachable)'));

        // Cache store also throws on put (fail-open write)
        $mockStore->shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('Redis OOM (maxmemory reached)'));

        // Delegate must still be called and response returned successfully
        $mockDelegate->shouldReceive('getSalesOverview')
            ->once()
            ->with('ws-failopen', $criteria)
            ->andReturn($overview);

        $cachedModel = new CachedSalesAnalyticsReadModel($mockDelegate, $failingCache, $this->versionStore);

        // Does NOT throw exception! Returns DTO smoothly.
        $result = $cachedModel->getSalesOverview('ws-failopen', $criteria);

        self::assertSame(500.0, $result->summary->totalRevenue);
    }

    #[Test]
    public function schema_version_bump_invalidates_prior_cache_entries(): void
    {
        $mockDelegate = Mockery::mock(SalesAnalyticsReadModelInterface::class);
        $criteria = new SalesFilterCriteriaDto;

        $overviewV1 = new SalesOverviewDto(
            summary: new SalesSummaryDto(100.0, 1, 100.0, 10.0, 10.0),
            trend: [], categories: [], regions: []
        );
        $overviewV2 = new SalesOverviewDto(
            summary: new SalesSummaryDto(200.0, 2, 100.0, 20.0, 20.0),
            trend: [], categories: [], regions: []
        );

        $mockDelegate->shouldReceive('getSalesOverview')->once()->with('ws-schema', $criteria)->andReturn($overviewV1);
        $mockDelegate->shouldReceive('getSalesOverview')->once()->with('ws-schema', $criteria)->andReturn($overviewV2);

        $cachedModel = new CachedSalesAnalyticsReadModel($mockDelegate, $this->cache, $this->versionStore);

        // 1. Schema version 1
        config(['analytics.schema_version' => 1]);
        $res1 = $cachedModel->getSalesOverview('ws-schema', $criteria);
        self::assertSame(100.0, $res1->summary->totalRevenue);

        // 2. Schema version bump to 2 -> Bypasses version 1 key, calls delegate
        config(['analytics.schema_version' => 2]);
        $res2 = $cachedModel->getSalesOverview('ws-schema', $criteria);
        self::assertSame(200.0, $res2->summary->totalRevenue);
    }
}
