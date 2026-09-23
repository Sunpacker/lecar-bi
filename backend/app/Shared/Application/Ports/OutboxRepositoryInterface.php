<?php

declare(strict_types=1);

namespace App\Shared\Application\Ports;

use App\Shared\Application\IntegrationEvent;

interface OutboxRepositoryInterface
{
    /**
     * Registers an integration event in the outbox table within the current transaction.
     * If a message with the same event_id already exists, the call is a no-op (idempotent).
     */
    public function register(IntegrationEvent $event): void;

    /**
     * Claims a batch of pending outbox messages for processing.
     * Uses FOR UPDATE SKIP LOCKED to prevent concurrent processing.
     *
     * @return list<array<string, mixed>>
     */
    public function claimPendingBatch(int $batchSize): array;

    /**
     * Marks a message as successfully published to the transport.
     */
    public function markPublished(string $eventId, string $transportMessageId): void;

    /**
     * Schedules a retry with the given next-attempt time, incrementing attempt count.
     * If max attempts are exceeded, transitions the message to 'failed' status.
     */
    public function scheduleRetry(string $eventId, \DateTimeImmutable $nextAttemptAt, string $lastError): void;

    /**
     * Resets a 'failed' message back to 'pending' for manual retry.
     * No-op if the message is not in 'failed' status.
     */
    public function resetForRetry(string $eventId): void;

    /**
     * Returns stale 'processing' messages (locked longer than $staleAfterSeconds) back to 'pending'.
     */
    public function recoverStaleLocks(int $staleAfterSeconds): void;
}
