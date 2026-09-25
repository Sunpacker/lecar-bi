<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateTimeImmutable;

readonly class StockRiskResult
{
    public function __construct(
        public int $currentQuantityAvailable,
        public int $currentSafetyStock,
        public int $currentReorderPoint,
        public ?DateTimeImmutable $estimatedDepletionDate,
        public ?DateTimeImmutable $estimatedReorderThresholdDate,
        public ?DateTimeImmutable $estimatedOrderPlacementDate,
        public ?int $medianLeadTimeDays,
        public ?string $leadTimeSource,
        public string $assumptions = '{"no_future_deliveries":true}'
    ) {}
}
