<?php

namespace Tests\Unit\Modules\SalesAnalytics;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsQuery;
use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use PHPUnit\Framework\TestCase;

final class GetSalesRecordsHandlerTest extends TestCase
{
    public function test_delegates_to_read_model_with_valid_criteria(): void
    {
        $expectedDto = new SalesRecordsPaginatedDto(
            items: [
                new SalesRecordDto(
                    id: 'item-1',
                    orderId: 'ord-1',
                    orderNumber: 'ORD-1001',
                    orderDate: '2026-01-15',
                    productId: 'prod-1',
                    productName: 'Тормозные колодки',
                    productSku: 'BRK-001',
                    categoryId: 'cat-1',
                    categoryName: 'Тормозная система',
                    regionId: 'reg-1',
                    regionName: 'Москва',
                    brandName: 'Brembo',
                    quantity: 2,
                    unitPrice: 3500.0,
                    totalPrice: 7000.0,
                    grossProfit: 2500.0,
                    status: 'completed',
                ),
            ],
            total: 1,
            page: 1,
            perPage: 20,
            totalPages: 1,
        );

        $readModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $readModel->expects($this->once())
            ->method('getSalesRecords')
            ->with(
                'ws-1',
                $this->callback(function (SalesRecordsCriteriaDto $criteria) {
                    return $criteria->dateFrom === '2026-01-01'
                        && $criteria->dateTo === '2026-01-31'
                        && $criteria->categoryId === 'cat-1'
                        && $criteria->page === 1
                        && $criteria->perPage === 20
                        && $criteria->sortBy === 'order_date'
                        && $criteria->sortDirection === 'desc';
                })
            )
            ->willReturn($expectedDto);

        $handler = new GetSalesRecordsHandler($readModel);
        $result = $handler->handle(new GetSalesRecordsQuery(
            workspaceId: 'ws-1',
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            categoryId: 'cat-1',
            regionId: null,
            page: 1,
            perPage: 20,
            sortBy: 'order_date',
            sortDirection: 'desc',
        ));

        $this->assertSame(1, $result->total);
        $this->assertCount(1, $result->items);
        $this->assertSame('ORD-1001', $result->items[0]->orderNumber);
    }

    public function test_throws_when_date_range_is_invalid(): void
    {
        $readModel = $this->createMock(SalesAnalyticsReadModelInterface::class);
        $handler = new GetSalesRecordsHandler($readModel);

        $this->expectException(InvalidDateRangeException::class);

        $handler->handle(new GetSalesRecordsQuery(
            workspaceId: 'ws-1',
            dateFrom: '2026-02-01',
            dateTo: '2026-01-01',
        ));
    }
}
