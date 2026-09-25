<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

readonly class GetForecastQuery
{
    public function __construct(
        public string $workspaceId,
        public string $productId,
        public string $warehouseId,
        public int $horizonDays = 28,
        public ?string $asOfDate = null
    ) {}
}
