<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesTrendPointDto
{
    public function __construct(
        public string $date,
        public float $revenue,
        public int $orderCount,
    ) {}
}
