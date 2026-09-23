<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryFilterOptionsDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsPaginatedDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\AbcXyzAnalyticsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventoryFilterOptionsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventoryItemsQuery;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries\InventorySummaryQuery;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsQueryPlanTest extends TestCase
{
    #[Test]
    public function sales_01_explain_plan_uses_index_only_scan_without_disk_spill(): void
    {
        $planPath = base_path('../docs/performance/plans/phase-16-after-sql/SALES-01.json');
        self::assertFileExists($planPath, 'After-SQL plan for SALES-01 must exist');

        /** @var list<array<string, mixed>> $planData */
        $planData = json_decode((string) file_get_contents($planPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($planData);

        $salesPlan = $planData[0];
        self::assertContains('Index Only Scan', $salesPlan['dominant_plan_nodes']);
        self::assertNotContains('Seq Scan', $salesPlan['dominant_plan_nodes']);

        // Assert no temp disk spills in plan
        $planDetails = $salesPlan['plan'][0]['Plan'] ?? [];
        $tempRead = $planDetails['Temp Read Blocks'] ?? 0;
        $tempWritten = $planDetails['Temp Written Blocks'] ?? 0;

        self::assertSame(0, $tempRead, 'SALES-01 should not spill to disk (Temp Read Blocks must be 0)');
        self::assertSame(0, $tempWritten, 'SALES-01 should not spill to disk (Temp Written Blocks must be 0)');
    }

    #[Test]
    public function inv_04_explain_plan_uses_index_scans_for_snapshot_and_pagination(): void
    {
        $planPath = base_path('../docs/performance/plans/phase-16-after-sql/INV-04.json');
        self::assertFileExists($planPath, 'After-SQL plan for INV-04 must exist');

        /** @var list<array<string, mixed>> $planData */
        $planData = json_decode((string) file_get_contents($planPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($planData);

        // First query in INV-04 is max(snapshot_date)
        $snapshotPlan = $planData[0];
        self::assertContains('Index Only Scan', $snapshotPlan['dominant_plan_nodes']);
    }

    #[Test]
    public function inventory_analytics_read_model_facade_delegates_to_collaborators(): void
    {
        $mockSummary = Mockery::mock(InventorySummaryQuery::class);
        $mockItems = Mockery::mock(InventoryItemsQuery::class);
        $mockFilter = Mockery::mock(InventoryFilterOptionsQuery::class);
        $mockAbcXyz = Mockery::mock(AbcXyzAnalyticsQuery::class);

        $expectedSummaryDto = new InventorySummaryDto(
            totalItems: 10,
            totalQuantityOnHand: 100,
            totalQuantityReserved: 10,
            totalQuantityAvailable: 90,
            totalInventoryValue: 5000.0,
            criticalCount: 1,
            overstockCount: 2,
            outOfStockCount: 0,
            optimalCount: 7,
            averageDaysOfStock: 15.5,
            healthBreakdown: [],
            warehouses: [],
            asOfDate: '2026-09-23',
        );

        $summaryCriteria = new InventorySummaryCriteriaDto(null, null);
        $mockSummary->shouldReceive('execute')
            ->once()
            ->with('ws-test', $summaryCriteria)
            ->andReturn($expectedSummaryDto);

        $expectedItemsDto = new InventoryItemsPaginatedDto([], 0, 1, 10, 0);
        $itemsCriteria = new InventoryItemsCriteriaDto(null, null, null, 1, 10, 'quantity_available', 'asc');
        $mockItems->shouldReceive('execute')
            ->once()
            ->with('ws-test', $itemsCriteria)
            ->andReturn($expectedItemsDto);

        $expectedFilterDto = new InventoryFilterOptionsDto([], [], '2026-09-23', [], []);
        $mockFilter->shouldReceive('execute')
            ->once()
            ->with('ws-test')
            ->andReturn($expectedFilterDto);

        $expectedAbcSummaryDto = new AbcXyzSummaryDto(0, 0.0, 0.0, [], [], [], 30, '2026-08-24', '2026-09-23');
        $abcSummaryCriteria = new AbcXyzSummaryCriteriaDto(30, null, null, null);
        $mockAbcXyz->shouldReceive('getSummary')
            ->once()
            ->with('ws-test', $abcSummaryCriteria)
            ->andReturn($expectedAbcSummaryDto);

        $expectedAbcItemsDto = new AbcXyzProductItemsPaginatedDto([], 0, 1, 10, 0);
        $abcItemsCriteria = new AbcXyzItemsCriteriaDto(periodDays: 30, page: 1, perPage: 10, sortBy: 'total_revenue', sortDirection: 'desc');
        $mockAbcXyz->shouldReceive('getItems')
            ->once()
            ->with('ws-test', $abcItemsCriteria)
            ->andReturn($expectedAbcItemsDto);

        $readModel = new PostgresInventoryAnalyticsReadModel(
            summaryQuery: $mockSummary,
            itemsQuery: $mockItems,
            filterOptionsQuery: $mockFilter,
            abcXyzQuery: $mockAbcXyz,
        );

        self::assertInstanceOf(InventoryAnalyticsReadModelInterface::class, $readModel);
        self::assertSame($expectedSummaryDto, $readModel->getInventorySummary('ws-test', $summaryCriteria));
        self::assertSame($expectedItemsDto, $readModel->getInventoryItems('ws-test', $itemsCriteria));
        self::assertSame($expectedFilterDto, $readModel->getFilterOptions('ws-test'));
        self::assertSame($expectedAbcSummaryDto, $readModel->getAbcXyzSummary('ws-test', $abcSummaryCriteria));
        self::assertSame($expectedAbcItemsDto, $readModel->getAbcXyzItems('ws-test', $abcItemsCriteria));
    }
}
