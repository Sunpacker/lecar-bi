<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierPerformanceCriteriaDto
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $warehouseId = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'total_spend',
        public string $sortDirection = 'desc',
    ) {}
}
