<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum AlertStatus: string
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Открыт',
            self::ACKNOWLEDGED => 'В работе',
            self::RESOLVED => 'Решён',
        };
    }

    public function isActive(): bool
    {
        return $this === self::OPEN || $this === self::ACKNOWLEDGED;
    }
}
