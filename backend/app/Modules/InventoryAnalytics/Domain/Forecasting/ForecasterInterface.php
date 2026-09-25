<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

interface ForecasterInterface
{
    /**
     * @return ForecastPointValue[]
     */
    public function forecast(SalesTimeSeries $series, ForecastHorizon $horizon): array;

    public function method(): ForecastMethod;
}
