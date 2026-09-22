<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesSummaryDto
{
    public function __construct(
        public float $totalRevenue,
        public int $orderCount,
        public float $averageOrderValue,
        public float $grossProfit,
        public float $marginRate,
    ) {}
}
