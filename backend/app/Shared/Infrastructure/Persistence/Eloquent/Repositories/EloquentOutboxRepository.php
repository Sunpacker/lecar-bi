<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Eloquent\Repositories;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Application\Ports\OutboxRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    private const RETRY_SCHEDULE_MINUTES = [1, 5, 15, 60, 360, 1440]; // 1m, 5m, 15m, 1h, 6h, 24h

    private const MAX_ATTEMPTS = 10;

    public function register(IntegrationEvent $event): void
    {
        $now = now()->toDateTimeString();

        // Idempotent: ignore if event_id already exists (e.g., duplicate registration in same tx is safe)
        DB::statement(
            <<<'SQL'
            INSERT INTO outbox_messages
                (id, event_type, event_version, producer, workspace_id, aggregate_type, aggregate_id,
                 envelope, occurred_at, status, attempt_count, next_attempt_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::timestamptz, 'pending', 0, NOW())
            ON CONFLICT (id) DO NOTHING
            SQL,
            [
                $event->eventId,
                $event->eventType,
                $event->eventVersion,
                $event->producer,
                $event->workspaceId,
                $event->aggregate['type'],
                $event->aggregate['id'],
                json_encode($event->toEnvelope()),
                $event->occurredAt,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimPendingBatch(int $batchSize): array
    {
        $now = now()->toDateTimeString();

        // Recover stale locks first (processing > 10 minutes)
        $this->recoverStaleLocks(600);

        $rows = DB::select(
            <<<'SQL'
            UPDATE outbox_messages
            SET status = 'processing',
                locked_at = NOW()
            WHERE id IN (
                SELECT id FROM outbox_messages
                WHERE status = 'pending'
                  AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                ORDER BY next_attempt_at ASC NULLS FIRST
                LIMIT ?
                FOR UPDATE SKIP LOCKED
            )
            RETURNING id, event_type, event_version, producer, workspace_id,
                      aggregate_type, aggregate_id, envelope, occurred_at,
                      status, attempt_count, next_attempt_at, locked_at,
                      published_at, redis_message_id, last_error
            SQL,
            [$batchSize]
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    public function markPublished(string $eventId, string $transportMessageId): void
    {
        DB::table('outbox_messages')
            ->where('id', $eventId)
            ->update([
                'status' => 'published',
                'redis_message_id' => $transportMessageId,
                'published_at' => now()->toDateTimeString(),
                'locked_at' => null,
            ]);
    }

    public function scheduleRetry(string $eventId, DateTimeImmutable $nextAttemptAt, string $lastError): void
    {
        $row = DB::table('outbox_messages')->where('id', $eventId)->first(['attempt_count']);

        if ($row === null) {
            return;
        }

        $newAttemptCount = ((int) $row->attempt_count) + 1;

        if ($newAttemptCount >= self::MAX_ATTEMPTS) {
            DB::table('outbox_messages')
                ->where('id', $eventId)
                ->update([
                    'status' => 'failed',
                    'attempt_count' => $newAttemptCount,
                    'locked_at' => null,
                    'last_error' => mb_substr($lastError, 0, 1000),
                ]);

            return;
        }

        DB::table('outbox_messages')
            ->where('id', $eventId)
            ->update([
                'status' => 'pending',
                'attempt_count' => $newAttemptCount,
                'next_attempt_at' => $nextAttemptAt->format('Y-m-d H:i:s'),
                'locked_at' => null,
                'last_error' => mb_substr($lastError, 0, 1000),
            ]);
    }

    public function resetForRetry(string $eventId): void
    {
        DB::table('outbox_messages')
            ->where('id', $eventId)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'next_attempt_at' => now()->toDateTimeString(),
                'last_error' => null,
            ]);
    }

    public function recoverStaleLocks(int $staleAfterSeconds): void
    {
        $threshold = now()->subSeconds($staleAfterSeconds)->toDateTimeString();

        DB::table('outbox_messages')
            ->where('status', 'processing')
            ->where('locked_at', '<=', $threshold)
            ->update([
                'status' => 'pending',
                'locked_at' => null,
                'next_attempt_at' => now()->toDateTimeString(),
            ]);
    }

    /**
     * Calculates the next retry time based on the current attempt count.
     */
    public static function nextRetryAt(int $currentAttemptCount): DateTimeImmutable
    {
        $scheduleIndex = min($currentAttemptCount, count(self::RETRY_SCHEDULE_MINUTES) - 1);
        $delayMinutes = self::RETRY_SCHEDULE_MINUTES[$scheduleIndex];

        return new DateTimeImmutable("+{$delayMinutes} minutes");
    }
}
