<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Notification;

use DateTimeImmutable;
use NotificationService\Notification\Application\AlertTriggeredV1;
use NotificationService\Notification\Application\ConsumeAlertTriggered;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Domain\Notification;
use NotificationService\Notification\Domain\NotificationId;
use NotificationService\Notification\Domain\NotificationSeverity;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConsumeAlertTriggeredTest extends TestCase
{
    private InMemoryNotificationRepository $notificationRepo;

    private InMemoryConsumedEventRepository $consumedEventRepo;

    private InMemoryTransactionManager $transactionManager;

    private ConsumeAlertTriggered $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationRepo = new InMemoryNotificationRepository;
        $this->consumedEventRepo = new InMemoryConsumedEventRepository;
        $this->transactionManager = new InMemoryTransactionManager;

        $this->handler = new ConsumeAlertTriggered(
            $this->notificationRepo,
            $this->consumedEventRepo,
            $this->transactionManager
        );
    }

    public function test_consumes_new_event_and_persists_notification_and_consumed_event(): void
    {
        $event = $this->createEvent('evt-001');
        $notificationId = new NotificationId('notif-001');
        $now = new DateTimeImmutable('2026-09-23T11:00:00+00:00');

        $result = $this->handler->handle($event, '1234567890-0', $notificationId, $now);

        $this->assertTrue($result->isProcessed());
        $this->assertFalse($result->isDuplicate());
        $this->assertSame('evt-001', $result->eventId);

        $saved = $this->notificationRepo->findBySourceEventId('evt-001');
        $this->assertNotNull($saved);
        $this->assertSame('notif-001', $saved->id()->toString());
        $this->assertSame('ws-1', $saved->workspaceId());
        $this->assertSame('Low Stock', $saved->title());
        $this->assertSame(NotificationSeverity::CRITICAL, $saved->severity());

        $this->assertTrue($this->consumedEventRepo->hasBeenProcessed('evt-001'));
    }

    public function test_duplicate_event_is_noop_and_returns_duplicate_status(): void
    {
        $event = $this->createEvent('evt-001');
        $notificationId = new NotificationId('notif-001');

        // First execution
        $firstResult = $this->handler->handle($event, '1234567890-0', $notificationId);
        $this->assertTrue($firstResult->isProcessed());

        // Second execution with same event_id
        $secondResult = $this->handler->handle($event, '1234567890-1');
        $this->assertFalse($secondResult->isProcessed());
        $this->assertTrue($secondResult->isDuplicate());
        $this->assertSame('evt-001', $secondResult->eventId);

        // Still only 1 notification
        $this->assertCount(1, $this->notificationRepo->all());
    }

    public function test_transaction_rolls_back_if_repository_fails(): void
    {
        $failingRepo = new class implements NotificationRepository
        {
            public function save(Notification $notification): void
            {
                throw new RuntimeException('DB write failure');
            }

            public function findBySourceEventId(string $sourceEventId): ?Notification
            {
                return null;
            }
        };

        $handler = new ConsumeAlertTriggered(
            $failingRepo,
            $this->consumedEventRepo,
            $this->transactionManager
        );

        $event = $this->createEvent('evt-fail');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB write failure');

        try {
            $handler->handle($event, '12345-0');
        } finally {
            $this->assertFalse($this->consumedEventRepo->hasBeenProcessed('evt-fail'));
        }
    }

    private function createEvent(string $eventId): AlertTriggeredV1
    {
        return new AlertTriggeredV1(
            eventId: $eventId,
            eventType: 'alert.triggered',
            eventVersion: 1,
            occurredAt: new DateTimeImmutable('2026-09-23T10:00:00+00:00'),
            producer: 'analytics',
            workspaceId: 'ws-1',
            alertId: 'alt-001',
            ruleId: 'rule-001',
            ruleName: 'Low Stock',
            severity: NotificationSeverity::CRITICAL,
            metric: 'quantity_available',
            comparator: 'lte',
            currentValue: 3.0,
            thresholdValue: 10.0,
            analyticalContext: ['target' => 'inventory']
        );
    }
}

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<string, Notification> */
    private array $items = [];

    public function save(Notification $notification): void
    {
        $this->items[$notification->sourceEventId()] = $notification;
    }

    public function findBySourceEventId(string $sourceEventId): ?Notification
    {
        return $this->items[$sourceEventId] ?? null;
    }

    /** @return array<string, Notification> */
    public function all(): array
    {
        return $this->items;
    }
}

final class InMemoryConsumedEventRepository implements ConsumedEventRepository
{
    /** @var array<string, bool> */
    private array $processed = [];

    public function hasBeenProcessed(string $eventId): bool
    {
        return $this->processed[$eventId] ?? false;
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
        $this->processed[$eventId] = true;
    }
}

final class InMemoryTransactionManager implements TransactionManager
{
    public function transaction(\Closure $operation): mixed
    {
        return $operation();
    }
}
