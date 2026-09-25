<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

readonly class PredictionInterval
{
    public function __construct(
        public float $lower,
        public float $upper,
        public float $level = 0.80
    ) {}

    public function width(): float
    {
        return $this->upper - $this->lower;
    }
}
