<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

use InvalidArgumentException;

enum ForecastHorizon: int
{
    case Week = 7;
    case TwoWeeks = 14;
    case Month = 28;

    public static function fromDays(int $days): self
    {
        return self::tryFrom($days) ?? throw new InvalidArgumentException("Unknown horizon days: {$days}");
    }
}
