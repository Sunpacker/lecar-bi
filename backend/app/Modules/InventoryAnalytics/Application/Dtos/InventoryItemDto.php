<?php

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class InventoryItemDto
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $categoryId,
        public string $categoryName,
        public string $warehouseId,
        public string $warehouseName,
        public string $warehouseCode,
        public int $quantityOnHand,
        public int $quantityReserved,
        public int $quantityAvailable,
        public float $unitCost,
        public float $inventoryValue,
        public float $salesVelocity,
        public ?float $daysOfStock,
        public string $stockHealth,
        public string $stockHealthLabel,
        public int $safetyStock,
        public int $reorderPoint,
    ) {}
}
