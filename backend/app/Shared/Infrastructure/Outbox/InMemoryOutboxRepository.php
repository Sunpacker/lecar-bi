<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Application\Ports\OutboxRepositoryInterface;
use DateTimeImmutable;

/**
 * In-memory outbox repository for unit tests.
 * Not suitable for production use.
 */
final class InMemoryOutboxRepository implements OutboxRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $messages = [];

    public function register(IntegrationEvent $event): void
    {
        // Idempotent: do nothing if event_id already registered
        if (isset($this->messages[$event->eventId])) {
            return;
        }

        $this->messages[$event->eventId] = [
            'id' => $event->eventId,
            'event_type' => $event->eventType,
            'event_version' => $event->eventVersion,
            'producer' => $event->producer,
            'workspace_id' => $event->workspaceId,
            'aggregate_type' => $event->aggregate['type'],
            'aggregate_id' => $event->aggregate['id'],
            'envelope' => $event->toEnvelope(),
            'occurred_at' => $event->occurredAt,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'locked_at' => null,
            'published_at' => null,
            'redis_message_id' => null,
            'last_error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimPendingBatch(int $batchSize): array
    {
        $batch = [];
        $count = 0;

        foreach ($this->messages as $id => &$message) {
            if ($count >= $batchSize) {
                break;
            }

            if ($message['status'] !== 'pending') {
                continue;
            }

            $nextAttempt = $message['next_attempt_at'];
            if ($nextAttempt !== null && new DateTimeImmutable($nextAttempt) > new DateTimeImmutable) {
                continue;
            }

            $message['status'] = 'processing';
            $message['locked_at'] = (new DateTimeImmutable)->format('Y-m-d H:i:s');
            $batch[] = $message;
            $count++;
        }

        return $batch;
    }

    public function markPublished(string $eventId, string $transportMessageId): void
    {
        if (! isset($this->messages[$eventId])) {
            return;
        }

        $this->messages[$eventId]['status'] = 'published';
        $this->messages[$eventId]['redis_message_id'] = $transportMessageId;
        $this->messages[$eventId]['published_at'] = (new DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->messages[$eventId]['locked_at'] = null;
    }

    public function scheduleRetry(string $eventId, DateTimeImmutable $nextAttemptAt, string $lastError): void
    {
        if (! isset($this->messages[$eventId])) {
            return;
        }

        $newAttemptCount = ((int) $this->messages[$eventId]['attempt_count']) + 1;

        if ($newAttemptCount >= 10) {
            $this->messages[$eventId]['status'] = 'failed';
            $this->messages[$eventId]['attempt_count'] = $newAttemptCount;
            $this->messages[$eventId]['locked_at'] = null;
            $this->messages[$eventId]['last_error'] = mb_substr($lastError, 0, 1000);

            return;
        }

        $this->messages[$eventId]['status'] = 'pending';
        $this->messages[$eventId]['attempt_count'] = $newAttemptCount;
        $this->messages[$eventId]['next_attempt_at'] = $nextAttemptAt->format('Y-m-d H:i:s');
        $this->messages[$eventId]['locked_at'] = null;
        $this->messages[$eventId]['last_error'] = mb_substr($lastError, 0, 1000);
    }

    public function resetForRetry(string $eventId): void
    {
        if (! isset($this->messages[$eventId])) {
            return;
        }

        if ($this->messages[$eventId]['status'] !== 'failed') {
            return;
        }

        $this->messages[$eventId]['status'] = 'pending';
        $this->messages[$eventId]['next_attempt_at'] = (new DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->messages[$eventId]['last_error'] = null;
    }

    public function recoverStaleLocks(int $staleAfterSeconds): void
    {
        $threshold = new DateTimeImmutable("-{$staleAfterSeconds} seconds");

        foreach ($this->messages as &$message) {
            if ($message['status'] !== 'processing') {
                continue;
            }

            $lockedAt = $message['locked_at'];
            if ($lockedAt !== null && new DateTimeImmutable($lockedAt) <= $threshold) {
                $message['status'] = 'pending';
                $message['locked_at'] = null;
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->messages;
    }

    /** @return list<array<string, mixed>> */
    public function byStatus(string $status): array
    {
        return array_values(array_filter($this->messages, fn (array $m) => $m['status'] === $status));
    }
}
