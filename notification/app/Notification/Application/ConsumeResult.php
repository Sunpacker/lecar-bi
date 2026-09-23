<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application;

use NotificationService\Notification\Domain\Notification;

final class ConsumeResult
{
    private function __construct(
        public readonly string $status,
        public readonly string $eventId,
        public readonly ?Notification $notification = null
    ) {}

    public static function processed(Notification $notification): self
    {
        return new self('processed', $notification->sourceEventId(), $notification);
    }

    public static function duplicate(string $eventId): self
    {
        return new self('duplicate', $eventId, null);
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isDuplicate(): bool
    {
        return $this->status === 'duplicate';
    }
}
