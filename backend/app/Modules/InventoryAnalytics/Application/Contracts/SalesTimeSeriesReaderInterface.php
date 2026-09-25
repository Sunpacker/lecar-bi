<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Contracts;

use App\Modules\InventoryAnalytics\Domain\Forecasting\SalesTimeSeries;

interface SalesTimeSeriesReaderInterface
{
    public function getDailySalesTimeSeries(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        string $asOfDate,
        int $lookbackDays = 365
    ): SalesTimeSeries;
}
