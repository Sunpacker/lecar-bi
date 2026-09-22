<?php

namespace App\Modules\SalesAnalytics\Application\Dtos;

final readonly class SalesRegionBreakdownDto
{
    public function __construct(
        public string $regionId,
        public string $regionName,
        public string $regionCode,
        public float $revenue,
        public int $orderCount,
        public float $revenueShare,
    ) {}
}
