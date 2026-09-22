<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventorySummaryDto
{
    /**
     * @param  list<StockHealthBreakdownDto>  $healthBreakdown
     * @param  list<WarehouseStockDto>  $warehouses
     */
    public function __construct(
        public int $totalItems,
        public int $totalQuantityOnHand,
        public int $totalQuantityReserved,
        public int $totalQuantityAvailable,
        public float $totalInventoryValue,
        public int $criticalCount,
        public int $overstockCount,
        public int $outOfStockCount,
        public int $optimalCount,
        public ?float $averageDaysOfStock,
        public array $healthBreakdown,
        public array $warehouses,
        public string $asOfDate,
    ) {}
}
