<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastDto;

readonly class GetForecastHandler
{
    public function __construct(
        private ForecastReadModelInterface $readModel
    ) {}

    public function handle(GetForecastQuery $query): ?ForecastDto
    {
        return $this->readModel->getLatestForecast(
            $query->workspaceId,
            $query->productId,
            $query->warehouseId,
            $query->horizonDays
        );
    }
}
