<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateTimeImmutable;

readonly class DailySalesPoint
{
    public function __construct(
        public DateTimeImmutable $date,
        public float $quantity,
        public bool $isStockoutDay
    ) {}
}
