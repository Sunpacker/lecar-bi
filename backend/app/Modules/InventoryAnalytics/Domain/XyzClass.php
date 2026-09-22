<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain;

enum XyzClass: string
{
    case X = 'X';
    case Y = 'Y';
    case Z = 'Z';

    public function label(): string
    {
        return match ($this) {
            self::X => 'Класс X (Стабильный спрос)',
            self::Y => 'Класс Y (Колеблющийся спрос)',
            self::Z => 'Класс Z (Непредсказуемый спрос)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::X => 'Коэффициент вариации CV <= 15%. Высокая точность прогноза расхода, стабильное потребление.',
            self::Y => 'Коэффициент вариации 15% < CV <= 35%. Умеренная вариативность (сезонность, периодические закупки).',
            self::Z => 'Коэффициент вариации CV > 35% либо нерегулярный/нулевой спрос. Высокий риск ошибки прогноза.',
        };
    }

    public function cvUpperThreshold(): ?float
    {
        return match ($this) {
            self::X => 0.15,
            self::Y => 0.35,
            self::Z => null,
        };
    }
}
