<?php

namespace Tests\Unit\Modules\InventoryAnalytics\Infrastructure;

use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryAnalyticsReadModelTest extends TestCase
{
    #[Test]
    public function in_memory_read_model_returns_summary_with_deterministic_aggregates(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel;
        $summary = $readModel->getInventorySummary('ws-1', new InventorySummaryCriteriaDto(null, null));

        self::assertGreaterThan(0, $summary->totalItems);
        self::assertGreaterThan(0, $summary->totalQuantityOnHand);
        self::assertGreaterThan(0, $summary->totalInventoryValue);
        self::assertNotEmpty($summary->healthBreakdown);
        self::assertNotEmpty($summary->warehouses);
        self::assertNotNull($summary->asOfDate);
    }

    #[Test]
    public function in_memory_read_model_paginates_and_filters_items(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel;

        // 1. All items
        $criteria = new InventoryItemsCriteriaDto(
            warehouseId: null,
            stockHealth: null,
            search: null,
            page: 1,
            perPage: 5,
            sortBy: 'quantity_available',
            sortDirection: 'asc'
        );
        $result = $readModel->getInventoryItems('ws-1', $criteria);

        self::assertLessThanOrEqual(5, count($result->items));
        self::assertGreaterThanOrEqual(1, $result->total);

        // 2. Filter by status
        $criticalCriteria = new InventoryItemsCriteriaDto(
            warehouseId: null,
            stockHealth: 'critical',
            search: null,
            page: 1,
            perPage: 10,
            sortBy: 'quantity_available',
            sortDirection: 'asc'
        );
        $criticalResult = $readModel->getInventoryItems('ws-1', $criticalCriteria);
        foreach ($criticalResult->items as $item) {
            self::assertSame('critical', $item->stockHealth);
        }
    }

    #[Test]
    public function in_memory_read_model_returns_filter_options(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel;
        $filters = $readModel->getFilterOptions('ws-1');

        self::assertNotEmpty($filters->warehouses);
        self::assertNotEmpty($filters->statuses);
        self::assertNotEmpty($filters->categories);
        self::assertNotEmpty($filters->suppliers);
        self::assertSame('2025-12-31', $filters->latestSnapshotDate);
    }

    #[Test]
    public function in_memory_read_model_returns_abc_xyz_summary(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel;
        $criteria = new AbcXyzSummaryCriteriaDto(periodDays: 90);
        $summary = $readModel->getAbcXyzSummary('ws-1', $criteria);

        self::assertSame(5, $summary->totalProducts);
        self::assertSame(300000.0, $summary->totalRevenue);
        self::assertCount(9, $summary->matrix);
        self::assertCount(3, $summary->abcDistribution);
        self::assertCount(3, $summary->xyzDistribution);
        self::assertSame(90, $summary->periodDays);
    }

    #[Test]
    public function in_memory_read_model_filters_and_paginates_abc_xyz_items(): void
    {
        $readModel = new InMemoryInventoryAnalyticsReadModel;
        $criteria = new AbcXyzItemsCriteriaDto(
            periodDays: 90,
            group: 'AX',
            page: 1,
            perPage: 10
        );

        $result = $readModel->getAbcXyzItems('ws-1', $criteria);

        self::assertNotEmpty($result->items);
        foreach ($result->items as $item) {
            self::assertSame('AX', $item->abcXyzGroup);
        }
    }
}
