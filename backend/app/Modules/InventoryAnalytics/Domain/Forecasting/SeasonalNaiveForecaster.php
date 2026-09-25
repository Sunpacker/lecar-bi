<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use DateInterval;

class SeasonalNaiveForecaster implements ForecasterInterface
{
    /**
     * @return ForecastPointValue[]
     */
    public function forecast(SalesTimeSeries $series, ForecastHorizon $horizon): array
    {
        $points = [];
        $overallAverage = $series->averageDailySales();

        for ($day = 1; $day <= $horizon->value; $day++) {
            $forecastDate = $series->asOfDate->add(new DateInterval("P{$day}D"));
            $dow = (int) $forecastDate->format('N');

            $dowPoints = $series->getPointsByDayOfWeek($dow);
            $censoredDowPoints = array_filter($dowPoints, fn (DailySalesPoint $p) => ! $p->isStockoutDay);

            if (count($censoredDowPoints) > 0) {
                $sum = array_sum(array_map(fn (DailySalesPoint $p) => $p->quantity, $censoredDowPoints));
                $estimate = $sum / count($censoredDowPoints);
            } else {
                $estimate = $overallAverage;
            }

            $points[] = new ForecastPointValue(
                date: $forecastDate,
                pointEstimate: max(0.0, round($estimate, 4))
            );
        }

        return $points;
    }

    public function method(): ForecastMethod
    {
        return ForecastMethod::SeasonalNaiveDow;
    }
}
