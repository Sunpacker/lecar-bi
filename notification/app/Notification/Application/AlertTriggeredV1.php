<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application;

use DateTimeImmutable;
use NotificationService\Notification\Domain\NotificationSeverity;

final class AlertTriggeredV1
{
    /**
     * @param  array<string, mixed>  $analyticalContext
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly DateTimeImmutable $occurredAt,
        public readonly string $producer,
        public readonly string $workspaceId,
        public readonly string $alertId,
        public readonly ?string $ruleId,
        public readonly string $ruleName,
        public readonly NotificationSeverity $severity,
        public readonly string $metric,
        public readonly string $comparator,
        public readonly float $currentValue,
        public readonly float $thresholdValue,
        public readonly array $analyticalContext
    ) {}
}
