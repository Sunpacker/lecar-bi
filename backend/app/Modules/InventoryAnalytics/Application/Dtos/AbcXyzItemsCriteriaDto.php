<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzItemsCriteriaDto
{
    public function __construct(
        public int $periodDays = 90,
        public ?string $warehouseId = null,
        public ?string $categoryId = null,
        public ?string $supplierId = null,
        public ?string $abcClass = null,
        public ?string $xyzClass = null,
        public ?string $group = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'total_revenue',
        public string $sortDirection = 'desc',
    ) {}
}
