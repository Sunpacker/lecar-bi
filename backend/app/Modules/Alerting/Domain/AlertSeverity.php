<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum AlertSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Информация',
            self::WARNING => 'Предупреждение',
            self::CRITICAL => 'Критический',
        };
    }
}
