<?php

declare(strict_types=1);

namespace Tests\Unit\Outbox;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository;
use App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use App\Shared\Infrastructure\Transport\InMemoryIntegrationEventTransport;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the outbox publisher pipeline using InMemory implementations.
 * Covers: retry backoff, failed status, retry reset, at-least-once delivery.
 */
final class OutboxPublisherTest extends TestCase
{
    private InMemoryOutboxRepository $outbox;

    private InMemoryIntegrationEventTransport $transport;

    protected function setUp(): void
    {
        $this->outbox = new InMemoryOutboxRepository;
        $this->transport = new InMemoryIntegrationEventTransport;
    }

    public function test_successful_publish_marks_message_published_with_stream_id(): void
    {
        $event = $this->createIntegrationEvent('evt-001');
        $this->outbox->register($event);

        $this->runPublisher();

        $messages = $this->outbox->byStatus('published');
        self::assertCount(1, $messages);
        self::assertSame('evt-001', $messages[0]['id']);
        self::assertSame('1000000000000-0', $messages[0]['redis_message_id']);
        self::assertNotNull($messages[0]['published_at']);
    }

    public function test_transport_failure_schedules_retry_with_backoff(): void
    {
        $event = $this->createIntegrationEvent('evt-002');
        $this->outbox->register($event);

        $this->transport->failOnNextCall('Connection refused');
        $this->runPublisher();

        $messages = $this->outbox->byStatus('pending');
        self::assertCount(1, $messages);
        self::assertSame(1, $messages[0]['attempt_count']);
        self::assertNotNull($messages[0]['next_attempt_at']);
        self::assertStringContainsString('Connection refused', (string) $messages[0]['last_error']);
    }

    public function test_tenth_failure_transitions_message_to_failed(): void
    {
        $event = $this->createIntegrationEvent('evt-003');
        $this->outbox->register($event);

        // Simulate 9 previous failures by incrementing attempt_count directly
        for ($i = 0; $i < 9; $i++) {
            $this->transport->failOnNextCall('Transport error');
            $this->outbox->scheduleRetry('evt-003', new DateTimeImmutable('-1 second'), 'Transport error');
        }

        // Reset pending status so it gets claimed again
        $this->outbox->resetForRetry('evt-003');

        // On the 10th attempt...
        // Wait — we need to manually set attempt_count = 9 in the message
        // After resetForRetry status = 'pending', but attempt_count is still 9 from scheduleRetry
        // Let's force-process by directly calling scheduleRetry one more time to hit the limit
        $this->outbox->scheduleRetry('evt-003', new DateTimeImmutable('+1 minute'), 'Final error');

        $messages = $this->outbox->byStatus('failed');
        self::assertCount(1, $messages);
        self::assertSame(10, $messages[0]['attempt_count']);
    }

    public function test_reset_for_retry_moves_failed_message_back_to_pending(): void
    {
        $event = $this->createIntegrationEvent('evt-004');
        $this->outbox->register($event);

        // Drive to failed state (10 attempts)
        for ($i = 0; $i < 10; $i++) {
            $this->outbox->scheduleRetry('evt-004', new DateTimeImmutable('-1 second'), 'Error '.$i);
        }

        self::assertCount(1, $this->outbox->byStatus('failed'));

        // Reset for manual retry
        $this->outbox->resetForRetry('evt-004');

        self::assertCount(0, $this->outbox->byStatus('failed'));
        self::assertCount(1, $this->outbox->byStatus('pending'));
    }

    public function test_idempotent_registration_does_not_create_duplicate(): void
    {
        $event = $this->createIntegrationEvent('evt-005');

        $this->outbox->register($event);
        $this->outbox->register($event); // duplicate registration

        $all = $this->outbox->all();
        self::assertCount(1, $all);
    }

    public function test_retry_preserves_original_event_id(): void
    {
        $event = $this->createIntegrationEvent('evt-006');
        $this->outbox->register($event);

        // First attempt fails
        $this->transport->failOnNextCall('Error');
        $this->runPublisher();

        // Second attempt — reset failure
        $this->transport->resetFailure();

        // Force message back to pending with past next_attempt
        $this->outbox->scheduleRetry('evt-006', new DateTimeImmutable('-1 second'), 'err');
        // scheduleRetry increments attempt_count and sets to pending; re-run publisher
        // We need to set status=pending manually, which scheduleRetry does
        $this->runPublisher();

        // The published event should still have the original event_id
        self::assertCount(1, $this->transport->published());
        self::assertSame('evt-006', $this->transport->published()[0]->eventId);
    }

    public function test_stale_processing_lock_is_recovered(): void
    {
        $event = $this->createIntegrationEvent('evt-007');
        $this->outbox->register($event);

        // Manually set status to 'processing' with old lock time to simulate crash
        // InMemory: claim will recover stale locks during claimPendingBatch
        // We'll set it directly
        $messages = $this->outbox->all();
        // We call claimPendingBatch which sets status=processing
        $batch = $this->outbox->claimPendingBatch(1);
        self::assertCount(1, $batch);

        // Now recoverStaleLocks with threshold = 0 seconds (immediately stale)
        $this->outbox->recoverStaleLocks(0);

        // Message should be back to pending
        self::assertCount(1, $this->outbox->byStatus('pending'));
    }

    public function test_retry_backoff_schedule(): void
    {
        // Verify that nextRetryAt produces increasing delays
        $previous = new DateTimeImmutable;
        $delays = [];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $next = EloquentOutboxRepository::nextRetryAt($attempt);
            $delays[] = $next->getTimestamp() - $previous->getTimestamp();
        }

        // All delays should be positive (future timestamps)
        foreach ($delays as $delay) {
            self::assertGreaterThan(0, $delay);
        }

        // Delays should be non-decreasing (backoff)
        for ($i = 1; $i < count($delays); $i++) {
            self::assertGreaterThanOrEqual($delays[$i - 1], $delays[$i]);
        }
    }

    /**
     * Simulates running the publisher job logic without the Laravel container.
     */
    private function runPublisher(): void
    {
        $batchSize = 100;
        $messages = $this->outbox->claimPendingBatch($batchSize);

        foreach ($messages as $message) {
            $eventId = (string) $message['id'];

            try {
                $integrationEvent = $this->messageToIntegrationEvent($message);
                $transportMessageId = $this->transport->publish($integrationEvent);
                $this->outbox->markPublished($eventId, $transportMessageId);
            } catch (\Throwable $e) {
                $currentAttempts = (int) ($message['attempt_count'] ?? 0);
                $nextAttemptAt = EloquentOutboxRepository::nextRetryAt($currentAttempts);
                $this->outbox->scheduleRetry($eventId, $nextAttemptAt, $e->getMessage());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageToIntegrationEvent(array $message): IntegrationEvent
    {
        return new IntegrationEvent(
            eventId: (string) $message['id'],
            eventType: (string) $message['event_type'],
            eventVersion: (int) $message['event_version'],
            occurredAt: (string) ($message['occurred_at'] ?? '2026-09-23T10:00:00+00:00'),
            producer: (string) $message['producer'],
            workspaceId: (string) $message['workspace_id'],
            aggregate: [
                'type' => (string) $message['aggregate_type'],
                'id' => (string) $message['aggregate_id'],
            ],
            payload: (array) ($message['envelope']['payload'] ?? []),
        );
    }

    private function createIntegrationEvent(string $eventId): IntegrationEvent
    {
        return new IntegrationEvent(
            eventId: $eventId,
            eventType: 'alert.triggered',
            eventVersion: 1,
            occurredAt: '2026-09-23T10:00:00+00:00',
            producer: 'analytics',
            workspaceId: 'ws-test',
            aggregate: ['type' => 'alert', 'id' => 'alt-001'],
            payload: [
                'rule_id' => 'rule-1',
                'rule_name' => 'Test Rule',
                'severity' => 'critical',
                'metric' => 'quantity_available',
                'comparator' => 'lte',
                'current_value' => 5.0,
                'threshold_value' => 10.0,
                'analytical_context' => ['target' => 'inventory'],
            ],
        );
    }
}
