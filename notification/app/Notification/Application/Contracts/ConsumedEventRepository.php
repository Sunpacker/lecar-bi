<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application\Contracts;

use DateTimeImmutable;

interface ConsumedEventRepository
{
    public function hasBeenProcessed(string $eventId): bool;

    public function register(
        string $eventId,
        string $streamMessageId,
        string $eventType,
        int $eventVersion,
        string $producer,
        string $workspaceId,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $processedAt
    ): void;
}
