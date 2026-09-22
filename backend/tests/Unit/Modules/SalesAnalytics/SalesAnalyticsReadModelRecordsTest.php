<?php

namespace Tests\Unit\Modules\SalesAnalytics;

use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use PHPUnit\Framework\TestCase;

final class SalesAnalyticsReadModelRecordsTest extends TestCase
{
    public function test_in_memory_records_filtering_sorting_and_pagination(): void
    {
        $model = new InMemorySalesAnalyticsReadModel;
        $model->seedRecords([
            new SalesRecordDto(
                id: 'item-1',
                orderId: 'ord-1',
                orderNumber: 'ORD-101',
                orderDate: '2026-01-10',
                productId: 'prod-1',
                productName: 'Колодки',
                productSku: 'SKU-1',
                categoryId: 'cat-1',
                categoryName: 'Тормоза',
                regionId: 'reg-1',
                regionName: 'Москва',
                brandName: 'Brembo',
                quantity: 2,
                unitPrice: 2000.0,
                totalPrice: 4000.0,
                grossProfit: 1500.0,
                status: 'completed',
            ),
            new SalesRecordDto(
                id: 'item-2',
                orderId: 'ord-2',
                orderNumber: 'ORD-102',
                orderDate: '2026-01-20',
                productId: 'prod-2',
                productName: 'Диски',
                productSku: 'SKU-2',
                categoryId: 'cat-1',
                categoryName: 'Тормоза',
                regionId: 'reg-2',
                regionName: 'СПб',
                brandName: 'Ferodo',
                quantity: 1,
                unitPrice: 6000.0,
                totalPrice: 6000.0,
                grossProfit: 2000.0,
                status: 'completed',
            ),
        ]);

        $result = $model->getSalesRecords('ws-1', new SalesRecordsCriteriaDto(
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            page: 1,
            perPage: 1,
            sortBy: 'total_price',
            sortDirection: 'desc',
        ));

        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->totalPages);
        $this->assertCount(1, $result->items);
        $this->assertSame('ORD-102', $result->items[0]->orderNumber);
        $this->assertSame(6000.0, $result->items[0]->totalPrice);

        // Check page 2
        $page2 = $model->getSalesRecords('ws-1', new SalesRecordsCriteriaDto(
            dateFrom: '2026-01-01',
            dateTo: '2026-01-31',
            page: 2,
            perPage: 1,
            sortBy: 'total_price',
            sortDirection: 'desc',
        ));
        $this->assertCount(1, $page2->items);
        $this->assertSame('ORD-101', $page2->items[0]->orderNumber);
    }
}
