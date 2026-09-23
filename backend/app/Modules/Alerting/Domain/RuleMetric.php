<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum RuleMetric: string
{
    case QUANTITY_AVAILABLE = 'quantity_available';
    case DAYS_OF_STOCK = 'days_of_stock';
    case INVENTORY_VALUE = 'inventory_value';

    public function label(): string
    {
        return match ($this) {
            self::QUANTITY_AVAILABLE => 'Доступное количество',
            self::DAYS_OF_STOCK => 'Дни запаса (Days of Stock)',
            self::INVENTORY_VALUE => 'Стоимость запасов',
        };
    }
}
