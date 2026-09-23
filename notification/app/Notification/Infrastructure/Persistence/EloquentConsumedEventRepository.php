<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;

final class EloquentConsumedEventRepository implements ConsumedEventRepository
{
    public function hasBeenProcessed(string $eventId): bool
    {
        return ConsumedEventModel::query()->where('event_id', $eventId)->exists();
    }

    public function register(
        string $eventId,
        string $streamMessageId,
        string $eventType,
        int $eventVersion,
        string $producer,
        string $workspaceId,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $processedAt
    ): void {
        try {
            ConsumedEventModel::query()->create([
                'event_id' => $eventId,
                'stream_message_id' => $streamMessageId,
                'event_type' => $eventType,
                'event_version' => $eventVersion,
                'producer' => $producer,
                'workspace_id' => $workspaceId,
                'occurred_at' => $occurredAt,
                'processed_at' => $processedAt,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return;
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        if ($code === '23505') {
            return true;
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unique') || str_contains($msg, 'duplicate');
    }
}
