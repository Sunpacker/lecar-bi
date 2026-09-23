<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Events;

use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\DomainEventId;
use DateTimeImmutable;

final class AlertTriggered implements DomainEvent
{
    public function __construct(
        private readonly DomainEventId $eventId,
        private readonly AlertId $alertId,
        private readonly string $workspaceId,
        private readonly ?AlertRuleId $ruleId,
        private readonly string $ruleName,
        private readonly AlertSeverity $severity,
        private readonly RuleMetric $metric,
        private readonly RuleComparator $comparator,
        private readonly float $currentValue,
        private readonly float $thresholdValue,
        private readonly AlertContext $context,
        private readonly DateTimeImmutable $occurredAt,
    ) {}

    public function eventId(): DomainEventId
    {
        return $this->eventId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function alertId(): AlertId
    {
        return $this->alertId;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function ruleId(): ?AlertRuleId
    {
        return $this->ruleId;
    }

    public function ruleName(): string
    {
        return $this->ruleName;
    }

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function metric(): RuleMetric
    {
        return $this->metric;
    }

    public function comparator(): RuleComparator
    {
        return $this->comparator;
    }

    public function currentValue(): float
    {
        return $this->currentValue;
    }

    public function thresholdValue(): float
    {
        return $this->thresholdValue;
    }

    public function context(): AlertContext
    {
        return $this->context;
    }
}
