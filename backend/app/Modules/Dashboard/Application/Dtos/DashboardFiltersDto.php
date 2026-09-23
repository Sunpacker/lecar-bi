<?php

namespace App\Modules\Dashboard\Application\Dtos;

final readonly class DashboardFiltersDto
{
    public function __construct(
        public ?string $dateRange = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $categoryId = null,
        public ?string $regionId = null,
        public ?string $warehouseId = null,
        public ?string $stockHealth = null,
    ) {}
}
