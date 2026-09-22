<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventorySummaryCriteriaDto
{
    public function __construct(
        public ?string $warehouseId = null,
        public ?string $asOfDate = null,
    ) {}
}
