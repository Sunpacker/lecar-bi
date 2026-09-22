<?php

namespace App\Modules\SalesAnalytics\Application\Queries;

final readonly class GetSalesRecordsQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'order_date',
        public string $sortDirection = 'desc',
    ) {}
}
