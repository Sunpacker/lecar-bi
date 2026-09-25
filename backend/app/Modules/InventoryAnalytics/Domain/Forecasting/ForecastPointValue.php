<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateTimeImmutable;

readonly class ForecastPointValue
{
    public function __construct(
        public DateTimeImmutable $date,
        public float $pointEstimate,
        public ?float $lowerBound = null,
        public ?float $upperBound = null,
        public ?float $intervalLevel = null
    ) {}
}
