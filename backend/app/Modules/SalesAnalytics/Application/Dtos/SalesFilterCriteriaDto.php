<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesFilterCriteriaDto
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
    ) {}
}
