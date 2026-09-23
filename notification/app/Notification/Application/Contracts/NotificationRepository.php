<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application\Contracts;

use NotificationService\Notification\Domain\Notification;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    public function findBySourceEventId(string $sourceEventId): ?Notification;
}
