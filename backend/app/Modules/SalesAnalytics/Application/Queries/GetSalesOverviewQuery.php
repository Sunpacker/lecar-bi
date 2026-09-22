<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

final readonly class GetSalesOverviewQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
    ) {}
}
