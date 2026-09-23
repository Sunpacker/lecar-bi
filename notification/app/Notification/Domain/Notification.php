<?php

declare(strict_types=1);

namespace NotificationService\Notification\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class Notification
{
    /**
     * @param  array<string, mixed>  $analyticalContext
     */
    public function __construct(
        private readonly NotificationId $id,
        private readonly string $sourceEventId,
        private readonly string $workspaceId,
        private readonly string $alertId,
        private readonly ?string $ruleId,
        private readonly NotificationSeverity $severity,
        private readonly string $title,
        private readonly string $body,
        private readonly array $analyticalContext,
        private readonly DateTimeImmutable $occurredAt,
        private readonly DateTimeImmutable $createdAt
    ) {
        if (trim($sourceEventId) === '') {
            throw new InvalidArgumentException('sourceEventId cannot be empty');
        }
        if (trim($workspaceId) === '') {
            throw new InvalidArgumentException('workspaceId cannot be empty');
        }
        if (trim($alertId) === '') {
            throw new InvalidArgumentException('alertId cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('title cannot be empty');
        }
    }

    public function id(): NotificationId
    {
        return $this->id;
    }

    public function sourceEventId(): string
    {
        return $this->sourceEventId;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function alertId(): string
    {
        return $this->alertId;
    }

    public function ruleId(): ?string
    {
        return $this->ruleId;
    }

    public function severity(): NotificationSeverity
    {
        return $this->severity;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticalContext(): array
    {
        return $this->analyticalContext;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
