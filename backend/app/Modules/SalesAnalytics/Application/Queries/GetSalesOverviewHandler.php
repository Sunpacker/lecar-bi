<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesOverviewDto;
use App\Modules\SalesAnalytics\Domain\DateRange;

final readonly class GetSalesOverviewHandler
{
    public function __construct(
        private SalesAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSalesOverviewQuery $query): SalesOverviewDto
    {
        $dateRange = DateRange::create($query->dateFrom, $query->dateTo);

        $criteria = new SalesFilterCriteriaDto(
            dateFrom: $dateRange->from(),
            dateTo: $dateRange->to(),
            categoryId: $query->categoryId,
            regionId: $query->regionId,
        );

        return $this->readModel->getSalesOverview($query->workspaceId, $criteria);
    }
}
