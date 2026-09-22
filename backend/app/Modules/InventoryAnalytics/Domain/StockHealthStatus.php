<?php

namespace App\Modules\InventoryAnalytics\Domain;

enum StockHealthStatus: string
{
    case OUT_OF_STOCK = 'out_of_stock';
    case CRITICAL = 'critical';
    case OPTIMAL = 'optimal';
    case OVERSTOCK = 'overstock';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'Дефицит',
            self::CRITICAL => 'Критический',
            self::OPTIMAL => 'В норме',
            self::OVERSTOCK => 'Избыток',
        };
    }
}
