<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

readonly class ForecastPointDto
{
    public function __construct(
        public string $date,
        public float $pointEstimate,
        public ?float $lowerBound = null,
        public ?float $upperBound = null,
        public ?float $intervalLevel = null,
        public ?float $actualValue = null,
        public bool $isStockoutDay = false
    ) {}
}
