<?php

namespace App\Modules\SalesAnalytics\Infrastructure\Persistence;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesCategoryBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRegionBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesTrendPointDto;

final class InMemorySalesAnalyticsReadModel implements SalesAnalyticsReadModelInterface
{
    /**
     * @var array<int, SalesRecordDto>
     */
    private array $records = [];

    /**
     * @param  array<int, SalesRecordDto>  $records
     */
    public function seedRecords(array $records): void
    {
        $this->records = $records;
    }

    public function getSalesOverview(string $workspaceId, SalesFilterCriteriaDto $criteria): SalesOverviewDto
    {
        return new SalesOverviewDto(
            summary: new SalesSummaryDto(
                totalRevenue: 1250000.0,
                orderCount: 320,
                averageOrderValue: 3906.25,
                grossProfit: 375000.0,
                marginRate: 0.3,
            ),
            trend: [
                new SalesTrendPointDto('2025-01-01', 45000.0, 12),
                new SalesTrendPointDto('2025-01-02', 52000.0, 14),
                new SalesTrendPointDto('2025-01-03', 48000.0, 11),
            ],
            categories: [
                new SalesCategoryBreakdownDto('cat-tires', 'Шины', 600000.0, 150, 0.48),
                new SalesCategoryBreakdownDto('cat-oils', 'Масла и техжидкости', 400000.0, 110, 0.32),
                new SalesCategoryBreakdownDto('cat-brakes', 'Тормозная система', 250000.0, 60, 0.20),
            ],
            regions: [
                new SalesRegionBreakdownDto('reg-msk', 'Москва и МО', 'MSK', 750000.0, 190, 0.6),
                new SalesRegionBreakdownDto('reg-spb', 'Санкт-Петербург и ЛО', 'SPB', 350000.0, 90, 0.28),
                new SalesRegionBreakdownDto('reg-sbr', 'Сибирь', 'SBR', 150000.0, 40, 0.12),
            ],
        );
    }

    public function getFilterOptions(string $workspaceId): SalesFilterOptionsDto
    {
        return new SalesFilterOptionsDto(
            categories: [
                ['id' => 'cat-tires', 'name' => 'Шины'],
                ['id' => 'cat-oils', 'name' => 'Масла и техжидкости'],
                ['id' => 'cat-brakes', 'name' => 'Тормозная система'],
            ],
            regions: [
                ['id' => 'reg-msk', 'name' => 'Москва и МО', 'code' => 'MSK'],
                ['id' => 'reg-spb', 'name' => 'Санкт-Петербург и ЛО', 'code' => 'SPB'],
                ['id' => 'reg-sbr', 'name' => 'Сибирь', 'code' => 'SBR'],
            ],
            minDate: '2025-01-01',
            maxDate: '2025-12-31',
        );
    }

    public function getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria): SalesRecordsPaginatedDto
    {
        $filtered = array_filter($this->records, function (SalesRecordDto $item) use ($criteria) {
            if ($criteria->dateFrom !== null && $item->orderDate < $criteria->dateFrom) {
                return false;
            }
            if ($criteria->dateTo !== null && $item->orderDate > $criteria->dateTo) {
                return false;
            }
            if ($criteria->categoryId !== null && $item->categoryId !== $criteria->categoryId) {
                return false;
            }
            if ($criteria->regionId !== null && $item->regionId !== $criteria->regionId) {
                return false;
            }

            return true;
        });

        // Sorting
        usort($filtered, function (SalesRecordDto $a, SalesRecordDto $b) use ($criteria) {
            $valA = match ($criteria->sortBy) {
                'order_date' => $a->orderDate,
                'order_number' => $a->orderNumber,
                'product_name' => $a->productName,
                'total_price' => $a->totalPrice,
                'quantity' => $a->quantity,
                'gross_profit' => $a->grossProfit,
                default => $a->orderDate,
            };
            $valB = match ($criteria->sortBy) {
                'order_date' => $b->orderDate,
                'order_number' => $b->orderNumber,
                'product_name' => $b->productName,
                'total_price' => $b->totalPrice,
                'quantity' => $b->quantity,
                'gross_profit' => $b->grossProfit,
                default => $b->orderDate,
            };

            $cmp = $valA <=> $valB;

            return $criteria->sortDirection === 'asc' ? $cmp : -$cmp;
        });

        $total = count($filtered);
        $offset = ($criteria->page - 1) * $criteria->perPage;
        $items = array_slice($filtered, $offset, $criteria->perPage);
        $totalPages = (int) max(1, ceil($total / $criteria->perPage));

        return new SalesRecordsPaginatedDto(
            items: $items,
            total: $total,
            page: $criteria->page,
            perPage: $criteria->perPage,
            totalPages: $totalPages,
        );
    }
}
