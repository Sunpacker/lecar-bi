<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesCategoryBreakdownDto
{
    public function __construct(
        public string $categoryId,
        public string $categoryName,
        public float $revenue,
        public int $orderCount,
        public float $revenueShare,
    ) {}
}
