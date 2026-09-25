<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

enum ForecastMethod: string
{
    case SeasonalNaiveDow = 'seasonal_naive_dow';
    case MovingAverage28d = 'moving_average_28d';

    public function label(): string
    {
        return match ($this) {
            self::SeasonalNaiveDow => 'Сезонная наивная модель (день недели)',
            self::MovingAverage28d => 'Скользящее среднее (28 дней)',
        };
    }
}
