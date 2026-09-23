<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain\Events;

use App\Modules\Alerting\Domain\AlertId;
use DateTimeImmutable;

final class AlertResolved
{
    public function __construct(
        private AlertId $alertId,
        private string $workspaceId,
        private string $resolvedBy,
        private ?string $resolutionNote,
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

    public function resolvedBy(): string
    {
        return $this->resolvedBy;
    }

    public function resolutionNote(): ?string
    {
        return $this->resolutionNote;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
