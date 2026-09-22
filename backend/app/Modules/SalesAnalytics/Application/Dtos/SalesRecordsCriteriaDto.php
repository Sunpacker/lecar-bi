<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRecordsCriteriaDto
{
    public function __construct(
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
