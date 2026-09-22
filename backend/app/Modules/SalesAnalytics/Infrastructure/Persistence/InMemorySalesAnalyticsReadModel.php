<?php

namespace App\Modules\SalesAnalytics\Infrastructure\Persistence;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesCategoryBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterOptionsDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRegionBreakdownDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesSummaryDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesTrendPointDto;

final class InMemorySalesAnalyticsReadModel implements SalesAnalyticsReadModelInterface
{
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
}
