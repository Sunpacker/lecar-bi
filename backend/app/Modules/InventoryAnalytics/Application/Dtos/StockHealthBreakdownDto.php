<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class StockHealthBreakdownDto
{
    public function __construct(
        public string $status,
        public string $label,
        public int $itemsCount,
        public float $totalValue,
        public float $share,
    ) {}
}
