<?php

declare(strict_types=1);

namespace NotificationService\Notification\Domain;

use InvalidArgumentException;

enum NotificationSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'info' => self::INFO,
            'warning' => self::WARNING,
            'critical' => self::CRITICAL,
            default => throw new InvalidArgumentException("Invalid notification severity: {$value}"),
        };
    }
}
