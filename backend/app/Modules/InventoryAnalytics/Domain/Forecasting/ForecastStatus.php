<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain\Forecasting;

enum ForecastStatus: string
{
    case Ready = 'ready';
    case Stale = 'stale';
    case InsufficientData = 'insufficient_data';
    case LimitedByStockouts = 'limited_by_stockouts';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Готов',
            self::Stale => 'Устарел',
            self::InsufficientData => 'Недостаточно данных',
            self::LimitedByStockouts => 'Ограничен дефицитом',
            self::Failed => 'Ошибка расчета',
        };
    }
}
