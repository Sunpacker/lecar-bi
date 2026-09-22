<?php

namespace Tests\Unit\Modules\SalesAnalytics\Application;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesCategoryBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRegionBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesTrendPointDto;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsQuery;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsApplicationTest extends TestCase
{
    #[Test]
    public function it_handles_sales_overview_query(): void
    {
        $mockReadModel = $this->createMock(SalesAnalyticsReadModelInterface::class);

        $expectedOverview = new SalesOverviewDto(
            summary: new SalesSummaryDto(150000.0, 50, 3000.0, 45000.0, 0.3),
            trend: [new SalesTrendPointDto('2025-01-01', 50000.0, 15)],
            categories: [new SalesCategoryBreakdownDto('cat-1', 'Tires', 100000.0, 30, 0.6667)],
            regions: [new SalesRegionBreakdownDto('reg-1', 'Moscow', 'MSK', 150000.0, 50, 1.0)],
        );

        $mockReadModel->expects(self::once())
            ->method('getSalesOverview')
            ->with('ws-1', self::isInstanceOf(SalesFilterCriteriaDto::class))
            ->willReturn($expectedOverview);

        $handler = new GetSalesOverviewHandler($mockReadModel);
        $result = $handler->handle(new GetSalesOverviewQuery('ws-1', '2025-01-01', '2025-01-31', 'cat-1', null));

        self::assertSame(150000.0, $result->summary->totalRevenue);
        self::assertSame(50, $result->summary->orderCount);
        self::assertCount(1, $result->trend);
        self::assertCount(1, $result->categories);
        self::assertCount(1, $result->regions);
    }

    #[Test]
    public function it_handles_sales_filter_options_query(): void
    {
        $mockReadModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $expectedFilters = new SalesFilterOptionsDto(
            categories: [['id' => 'cat-1', 'name' => 'Tires']],
            regions: [['id' => 'reg-1', 'name' => 'Moscow', 'code' => 'MSK']],
            minDate: '2025-01-01',
            maxDate: '2025-12-31',
        );

        $mockReadModel->expects(self::once())
            ->method('getFilterOptions')
            ->with('ws-1')
            ->willReturn($expectedFilters);

        $handler = new GetSalesFilterOptionsHandler($mockReadModel);
        $result = $handler->handle(new GetSalesFilterOptionsQuery('ws-1'));

        self::assertSame('2025-01-01', $result->minDate);
        self::assertCount(1, $result->categories);
    }
}
