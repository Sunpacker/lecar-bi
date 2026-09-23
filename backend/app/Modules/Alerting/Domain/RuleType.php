<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum RuleType: string
{
    case OUT_OF_STOCK = 'out_of_stock';
    case CRITICAL_STOCK = 'critical_stock';
    case OVERSTOCK = 'overstock';
    case REORDER_POINT = 'reorder_point';

    public function label(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'Дефицит (нулевой остаток)',
            self::CRITICAL_STOCK => 'Критический остаток',
            self::OVERSTOCK => 'Затоваривание (избыток)',
            self::REORDER_POINT => 'Точка дозаказа',
        };
    }
}
