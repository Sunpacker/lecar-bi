<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

readonly class ForecastStockRiskDto
{
    public function __construct(
        public int $currentQuantityAvailable,
        public int $currentSafetyStock,
        public int $currentReorderPoint,
        public ?string $estimatedDepletionDate,
        public ?string $estimatedReorderThresholdDate,
        public ?string $estimatedOrderPlacementDate,
        public ?int $medianLeadTimeDays,
        public ?string $leadTimeSource,
        public string $assumptions
    ) {}
}
