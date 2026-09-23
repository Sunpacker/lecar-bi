<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

final readonly class InventoryCandidateDto
{
    public function __construct(
        public string $productId,
        public string $productName,
        public string $productSku,
        public ?string $categoryId,
        public ?string $categoryName,
        public string $warehouseId,
        public string $warehouseName,
        public int $quantityOnHand,
        public int $quantityReserved,
        public int $quantityAvailable,
        public int $safetyStock,
        public int $reorderPoint,
        public float $unitCost,
        public float $inventoryValue,
        public float $dailyVelocity,
        public ?float $daysOfStock,
    ) {}
}
