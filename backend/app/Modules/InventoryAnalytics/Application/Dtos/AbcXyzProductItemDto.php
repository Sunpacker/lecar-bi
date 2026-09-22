<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

final readonly class AbcXyzProductItemDto
{
    /**
     * @param  list<float>  $periodSales
     */
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $categoryId,
        public string $categoryName,
        public string $brandName,
        public ?string $supplierId,
        public ?string $supplierName,
        public float $totalRevenue,
        public int $totalUnitsSold,
        public float $revenueShare,
        public float $cumulativeRevenueShare,
        public string $abcClass,
        public array $periodSales,
        public float $averageSales,
        public float $standardDeviation,
        public ?float $coefficientOfVariation,
        public string $xyzClass,
        public string $abcXyzGroup,
        public int $currentStock,
        public float $inventoryValue,
    ) {}
}
