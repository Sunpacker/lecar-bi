<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Commands;

readonly class GenerateForecastCommand
{
    public function __construct(
        public string $workspaceId,
        public string $productId,
        public string $warehouseId,
        public int $horizonDays = 28
    ) {}
}
