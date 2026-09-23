<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum RuleComparator: string
{
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case EQUAL = 'eq';

    public function symbol(): string
    {
        return match ($this) {
            self::LESS_THAN => '<',
            self::LESS_THAN_OR_EQUAL => '<=',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_OR_EQUAL => '>=',
            self::EQUAL => '=',
        };
    }

    public function evaluate(float $actual, float $threshold): bool
    {
        return match ($this) {
            self::LESS_THAN => $actual < $threshold,
            self::LESS_THAN_OR_EQUAL => $actual <= $threshold,
            self::GREATER_THAN => $actual > $threshold,
            self::GREATER_THAN_OR_EQUAL => $actual >= $threshold,
            self::EQUAL => abs($actual - $threshold) < 0.0001,
        };
    }
}
