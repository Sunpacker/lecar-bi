<?php

declare(strict_types=1);

namespace NotificationService\Integration\Application;

final class RouteResult
{
    /**
     * @param  array<string, mixed>|null  $fields
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $eventId = null,
        public readonly ?string $reason = null,
        public readonly ?array $fields = null
    ) {}

    public static function processed(string $eventId): self
    {
        return new self('processed', $eventId);
    }

    public static function duplicate(string $eventId): self
    {
        return new self('duplicate', $eventId);
    }

    public static function ignored(string $reason): self
    {
        return new self('ignored', null, $reason);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function deadLetter(string $reason, array $fields, ?string $eventId = null): self
    {
        return new self('dead_letter', $eventId, $reason, $fields);
    }

    public function shouldAck(): bool
    {
        return in_array($this->status, ['processed', 'duplicate', 'ignored'], true);
    }

    public function isDeadLetter(): bool
    {
        return $this->status === 'dead_letter';
    }
}
