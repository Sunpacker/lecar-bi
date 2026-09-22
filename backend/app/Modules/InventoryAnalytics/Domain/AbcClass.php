<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Domain;

enum AbcClass: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Класс A (Высокий оборот)',
            self::B => 'Класс B (Средний оборот)',
            self::C => 'Класс C (Низкий оборот)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::A => 'Ключевые позиции, формирующие до 80% выручки бизнеса (Pareto). Требуют строгого контроля доступности.',
            self::B => 'Товары умеренного спроса, обеспечивающие следующие 15% выручки (80-95%). Базовый ассортимент.',
            self::C => 'Хвост ассортимента (оставшиеся 5% выручки либо товары без продаж). Потенциальные неликвиды.',
        };
    }

    public function cumulativeShareThreshold(): float
    {
        return match ($this) {
            self::A => 0.80,
            self::B => 0.95,
            self::C => 1.00,
        };
    }
}
