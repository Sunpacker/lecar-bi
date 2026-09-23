<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SupplierAnalytics\Application;

use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\DeliveryStatusBreakdownDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesPaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierFilterOptionsDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceItemDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformancePaginatedDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierSummaryDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierTrendPointDto;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierDeliveriesHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierDeliveriesQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierFilterOptionsHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierFilterOptionsQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierOverviewHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierOverviewQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierPerformanceHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierPerformanceQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupplierAnalyticsApplicationTest extends TestCase
{
    #[Test]
    public function get_supplier_overview_handler_delegates_to_read_model(): void
    {
        $criteria = new SupplierOverviewCriteriaDto('2025-01-01', '2025-12-31', null, null);
        $expectedDto = new SupplierOverviewDto(
            summary: new SupplierSummaryDto(
                totalDeliveries: 10,
                onTimeDeliveries: 8,
                delayedDeliveries: 2,
                partialDeliveries: 1,
                totalSpend: 50000.0,
                totalOrderedQuantity: 500,
                totalReceivedQuantity: 490,
                totalDefectQuantity: 5,
                onTimeRate: 80.0,
                delayRate: 20.0,
                fulfillmentRate: 98.0,
                defectRate: 1.0,
                averageLeadTimeDays: 5.5,
                averageDelayDays: 2.0,
            ),
            statusBreakdown: [
                new DeliveryStatusBreakdownDto('on_time', 8, 80.0, 400),
                new DeliveryStatusBreakdownDto('delayed', 2, 20.0, 90),
            ],
            trends: [
                new SupplierTrendPointDto('2025-01', 10, 8, 50000.0, 80.0, 98.0, 5.5),
            ],
            topSuppliers: [
                new SupplierPerformanceItemDto(
                    supplierId: 'sup-1',
                    supplierName: 'Bosch Tier-1',
                    totalDeliveries: 10,
                    onTimeDeliveries: 8,
                    delayedDeliveries: 2,
                    partialDeliveries: 1,
                    totalSpend: 50000.0,
                    orderedQuantity: 500,
                    receivedQuantity: 490,
                    defectQuantity: 5,
                    onTimeRate: 80.0,
                    delayRate: 20.0,
                    fulfillmentRate: 98.0,
                    defectRate: 1.0,
                    avgLeadTimeDays: 5.5,
                    avgDelayDays: 2.0,
                    reliabilityScore: 0.79,
                    reliabilityTier: 'acceptable',
                ),
            ],
        );

        $readModel = $this->createMock(SupplierAnalyticsReadModelInterface::class);
        $readModel->expects(self::once())
            ->method('getSupplierOverview')
            ->with('ws-1', $criteria)
            ->willReturn($expectedDto);

        $handler = new GetSupplierOverviewHandler($readModel);
        $result = $handler->handle(new GetSupplierOverviewQuery('ws-1', $criteria));

        self::assertSame($expectedDto, $result);
        self::assertIsArray($result->toArray());
        self::assertArrayHasKey('summary', $result->toArray());
    }

    #[Test]
    public function get_supplier_performance_handler_delegates_to_read_model(): void
    {
        $criteria = new SupplierPerformanceCriteriaDto(null, null, null, null, 1, 20, 'total_spend', 'desc');
        $expectedDto = new SupplierPerformancePaginatedDto(
            items: [],
            total: 0,
            page: 1,
            perPage: 20,
            totalPages: 0,
        );

        $readModel = $this->createMock(SupplierAnalyticsReadModelInterface::class);
        $readModel->expects(self::once())
            ->method('getSupplierPerformance')
            ->with('ws-1', $criteria)
            ->willReturn($expectedDto);

        $handler = new GetSupplierPerformanceHandler($readModel);
        $result = $handler->handle(new GetSupplierPerformanceQuery('ws-1', $criteria));

        self::assertSame($expectedDto, $result);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function get_supplier_deliveries_handler_delegates_to_read_model(): void
    {
        $criteria = new SupplierDeliveriesCriteriaDto(null, null, null, null, null, null, 1, 20, 'order_date', 'desc');
        $expectedDto = new SupplierDeliveriesPaginatedDto(
            items: [],
            total: 0,
            page: 1,
            perPage: 20,
            totalPages: 0,
        );

        $readModel = $this->createMock(SupplierAnalyticsReadModelInterface::class);
        $readModel->expects(self::once())
            ->method('getSupplierDeliveries')
            ->with('ws-1', $criteria)
            ->willReturn($expectedDto);

        $handler = new GetSupplierDeliveriesHandler($readModel);
        $result = $handler->handle(new GetSupplierDeliveriesQuery('ws-1', $criteria));

        self::assertSame($expectedDto, $result);
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function get_supplier_filter_options_handler_delegates_to_read_model(): void
    {
        $expectedDto = new SupplierFilterOptionsDto(
            suppliers: [['id' => 'sup-1', 'name' => 'Supplier 1']],
            warehouses: [['id' => 'wh-1', 'name' => 'Warehouse 1']],
            statuses: [
                ['value' => 'on_time', 'label' => 'В срок'],
                ['value' => 'delayed', 'label' => 'С задержкой'],
                ['value' => 'partial', 'label' => 'Частично'],
            ],
            minDate: '2025-01-01',
            maxDate: '2025-12-31',
        );

        $readModel = $this->createMock(SupplierAnalyticsReadModelInterface::class);
        $readModel->expects(self::once())
            ->method('getFilterOptions')
            ->with('ws-1')
            ->willReturn($expectedDto);

        $handler = new GetSupplierFilterOptionsHandler($readModel);
        $result = $handler->handle(new GetSupplierFilterOptionsQuery('ws-1'));

        self::assertSame($expectedDto, $result);
        self::assertCount(1, $result->suppliers);
    }
}
