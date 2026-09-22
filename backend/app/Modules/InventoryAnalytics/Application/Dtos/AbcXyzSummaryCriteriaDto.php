<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzSummaryCriteriaDto
{
    public function __construct(
        public int $periodDays = 90,
        public ?string $warehouseId = null,
        public ?string $categoryId = null,
        public ?string $supplierId = null,
    ) {}
}
