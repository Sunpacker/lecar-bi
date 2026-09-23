<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Events;

use App\Modules\Alerting\Domain\AlertId;
use DateTimeImmutable;

final class AlertAcknowledged
{
    public function __construct(
        private AlertId $alertId,
        private string $workspaceId,
        private string $acknowledgedBy,
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

    public function acknowledgedBy(): string
    {
        return $this->acknowledgedBy;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
