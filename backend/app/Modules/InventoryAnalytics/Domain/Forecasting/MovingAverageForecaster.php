<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateInterval;

class MovingAverageForecaster implements ForecasterInterface
{
    /**
     * @return ForecastPointValue[]
     */
    public function forecast(SalesTimeSeries $series, ForecastHorizon $horizon): array
    {
        $censored = $series->censoredSeries();
        $recent = array_slice($censored, -28);

        if (count($recent) > 0) {
            $sum = array_sum(array_map(fn (DailySalesPoint $p) => $p->quantity, $recent));
            $estimate = $sum / count($recent);
        } else {
            $estimate = $series->averageDailySales();
        }

        $pointEstimate = max(0.0, round($estimate, 4));
        $points = [];

        for ($day = 1; $day <= $horizon->value; $day++) {
            $forecastDate = $series->asOfDate->add(new DateInterval("P{$day}D"));
            $points[] = new ForecastPointValue(
                date: $forecastDate,
                pointEstimate: $pointEstimate
            );
        }

        return $points;
    }

    public function method(): ForecastMethod
    {
        return ForecastMethod::MovingAverage28d;
    }
}
