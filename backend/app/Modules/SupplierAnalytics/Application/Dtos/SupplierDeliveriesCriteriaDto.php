<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierDeliveriesCriteriaDto
{
    public function __construct(
        public ?string $supplierId = null,
        public ?string $warehouseId = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'order_date',
        public string $sortDirection = 'desc',
    ) {}
}
