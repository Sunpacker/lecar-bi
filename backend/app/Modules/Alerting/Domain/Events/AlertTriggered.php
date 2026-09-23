<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Events;

use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use DateTimeImmutable;

final class AlertTriggered
{
    public function __construct(
        private AlertId $alertId,
        private string $workspaceId,
        private ?AlertRuleId $ruleId,
        private AlertSeverity $severity,
        private DateTimeImmutable $occurredAt,
    ) {}

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

    public function severity(): AlertSeverity
    {
        return $this->severity;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
