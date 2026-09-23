<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Dtos;

final readonly class SupplierOverviewCriteriaDto
{
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $supplierId = null,
        public ?string $warehouseId = null,
    ) {}
}
