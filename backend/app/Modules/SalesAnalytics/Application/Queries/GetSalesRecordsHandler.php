<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsPaginatedDto;
use App\Modules\SalesAnalytics\Domain\DateRange;

final readonly class GetSalesRecordsHandler
{
    public function __construct(
        private SalesAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetSalesRecordsQuery $query): SalesRecordsPaginatedDto
    {
        $dateRange = DateRange::create($query->dateFrom, $query->dateTo);

        $criteria = new SalesRecordsCriteriaDto(
            dateFrom: $dateRange->from(),
            dateTo: $dateRange->to(),
            categoryId: $query->categoryId,
            regionId: $query->regionId,
            page: max(1, $query->page),
            perPage: max(1, min(100, $query->perPage)),
            sortBy: $query->sortBy,
            sortDirection: strtolower($query->sortDirection) === 'asc' ? 'asc' : 'desc',
        );

        return $this->readModel->getSalesRecords($query->workspaceId, $criteria);
    }
}
