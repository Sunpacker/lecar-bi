<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Contracts;

use App\Modules\InventoryAnalytics\Application\Dtos\ForecastDto;

interface ForecastReadModelInterface
{
    public function getLatestForecast(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        int $horizonDays
    ): ?ForecastDto;
}
