<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SupplierAnalytics\Infrastructure;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\InMemorySupplierAnalyticsReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupplierAnalyticsReadModelTest extends TestCase
{
    #[Test]
    public function in_memory_read_model_returns_overview_with_aggregated_metrics(): void
    {
        $readModel = new InMemorySupplierAnalyticsReadModel;
        $overview = $readModel->getSupplierOverview('ws-1', new SupplierOverviewCriteriaDto);

        self::assertGreaterThan(0, $overview->summary->totalDeliveries);
        self::assertGreaterThan(0, $overview->summary->totalSpend);
        self::assertGreaterThan(0.0, $overview->summary->onTimeRate);
        self::assertGreaterThan(0.0, $overview->summary->fulfillmentRate);
        self::assertNotEmpty($overview->statusBreakdown);
        self::assertNotEmpty($overview->trends);
        self::assertNotEmpty($overview->topSuppliers);
    }

    #[Test]
    public function in_memory_read_model_paginates_and_sorts_performance(): void
    {
        $readModel = new InMemorySupplierAnalyticsReadModel;

        $criteria = new SupplierPerformanceCriteriaDto(
            page: 1,
            perPage: 3,
            sortBy: 'total_spend',
            sortDirection: 'desc',
        );

        $result = $readModel->getSupplierPerformance('ws-1', $criteria);

        self::assertLessThanOrEqual(3, count($result->items));
        self::assertGreaterThanOrEqual(1, $result->total);
        self::assertSame(1, $result->page);

        // Verify descending order by spend
        if (count($result->items) >= 2) {
            self::assertGreaterThanOrEqual($result->items[1]->totalSpend, $result->items[0]->totalSpend);
        }
    }

    #[Test]
    public function in_memory_read_model_filters_deliveries(): void
    {
        $readModel = new InMemorySupplierAnalyticsReadModel;

        $criteria = new SupplierDeliveriesCriteriaDto(
            status: 'on_time',
            page: 1,
            perPage: 10,
        );

        $result = $readModel->getSupplierDeliveries('ws-1', $criteria);

        foreach ($result->items as $item) {
            self::assertSame('on_time', $item->deliveryStatus);
        }
    }

    #[Test]
    public function in_memory_read_model_returns_filter_options(): void
    {
        $readModel = new InMemorySupplierAnalyticsReadModel;
        $filters = $readModel->getFilterOptions('ws-1');

        self::assertNotEmpty($filters->suppliers);
        self::assertNotEmpty($filters->warehouses);
        self::assertNotEmpty($filters->statuses);
        self::assertNotEmpty($filters->minDate);
        self::assertNotEmpty($filters->maxDate);
    }
}
