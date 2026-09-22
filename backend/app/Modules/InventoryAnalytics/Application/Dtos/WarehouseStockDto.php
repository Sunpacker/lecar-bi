<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class WarehouseStockDto
{
    public function __construct(
        public string $warehouseId,
        public string $warehouseName,
        public string $warehouseCode,
        public int $totalQuantity,
        public float $totalValue,
        public int $itemsCount,
        public int $criticalCount,
        public int $overstockCount,
    ) {}
}
