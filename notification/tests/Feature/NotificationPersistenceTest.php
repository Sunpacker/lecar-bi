<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationService\Notification\Application\AlertTriggeredV1;
use NotificationService\Notification\Application\ConsumeAlertTriggered;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Domain\Notification;
use NotificationService\Notification\Domain\NotificationId;
use NotificationService\Notification\Domain\NotificationSeverity;
use NotificationService\Notification\Infrastructure\Persistence\ConsumedEventModel;
use NotificationService\Notification\Infrastructure\Persistence\NotificationModel;
use NotificationService\Tests\TestCase;
use RuntimeException;

final class NotificationPersistenceTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as traitRefreshDatabase;
    }

    private NotificationRepository $notificationRepo;

    private ConsumedEventRepository $consumedEventRepo;

    private TransactionManager $transactionManager;

    private ConsumeAlertTriggered $handler;

    public function refreshDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            return;
        }

        $this->traitRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO sqlite driver not available on host CLI; persistence is verified via Docker container in integration checkpoint');
        }

        $this->notificationRepo = $this->app->make(NotificationRepository::class);
        $this->consumedEventRepo = $this->app->make(ConsumedEventRepository::class);
        $this->transactionManager = $this->app->make(TransactionManager::class);

        $this->handler = new ConsumeAlertTriggered(
            $this->notificationRepo,
            $this->consumedEventRepo,
            $this->transactionManager
        );
    }

    public function test_persists_notification_and_consumed_event(): void
    {
        $event = $this->createEvent('evt-persisted-1');
        $notificationId = new NotificationId('c0000001-0000-4000-8000-000000000001');

        $result = $this->handler->handle($event, '1000-0', $notificationId);

        $this->assertTrue($result->isProcessed());
        $this->assertSame('evt-persisted-1', $result->eventId);

        // Verify in database tables directly
        $this->assertDatabaseHas('notifications', [
            'id' => 'c0000001-0000-4000-8000-000000000001',
            'source_event_id' => 'evt-persisted-1',
            'workspace_id' => 'ws-1',
            'severity' => 'critical',
            'title' => 'Critical stock deficit',
        ]);

        $this->assertDatabaseHas('consumed_events', [
            'event_id' => 'evt-persisted-1',
            'stream_message_id' => '1000-0',
            'workspace_id' => 'ws-1',
        ]);

        // Verify retrieval via repository
        $saved = $this->notificationRepo->findBySourceEventId('evt-persisted-1');
        $this->assertNotNull($saved);
        $this->assertSame('c0000001-0000-4000-8000-000000000001', $saved->id()->toString());
        $this->assertSame(NotificationSeverity::CRITICAL, $saved->severity());
        $this->assertSame('inventory', $saved->analyticalContext()['target']);
    }

    public function test_duplicate_delivery_is_idempotent(): void
    {
        $event = $this->createEvent('evt-dup-1');
        $notificationId1 = new NotificationId('c0000001-0000-4000-8000-000000000002');
        $notificationId2 = new NotificationId('c0000001-0000-4000-8000-000000000003');

        $firstResult = $this->handler->handle($event, '2000-0', $notificationId1);
        $this->assertTrue($firstResult->isProcessed());

        $secondResult = $this->handler->handle($event, '2000-1', $notificationId2);
        $this->assertFalse($secondResult->isProcessed());
        $this->assertTrue($secondResult->isDuplicate());

        $this->assertSame(1, NotificationModel::query()->where('source_event_id', 'evt-dup-1')->count());
        $this->assertSame(1, ConsumedEventModel::query()->where('event_id', 'evt-dup-1')->count());
    }

    public function test_concurrent_unique_violation_handled_gracefully(): void
    {
        $event = $this->createEvent('evt-concurrent-1');

        // Pre-insert into consumed_events to simulate race condition where another worker just inserted
        $this->consumedEventRepo->register(
            'evt-concurrent-1',
            '3000-0',
            $event->eventType,
            $event->eventVersion,
            $event->producer,
            $event->workspaceId,
            $event->occurredAt,
            new DateTimeImmutable
        );

        // Attempting to re-register directly does not throw QueryException
        $this->consumedEventRepo->register(
            'evt-concurrent-1',
            '3000-1',
            $event->eventType,
            $event->eventVersion,
            $event->producer,
            $event->workspaceId,
            $event->occurredAt,
            new DateTimeImmutable
        );

        $this->assertSame(1, ConsumedEventModel::query()->where('event_id', 'evt-concurrent-1')->count());
    }

    public function test_transaction_rolls_back_on_failure(): void
    {
        $failingRepo = new class implements NotificationRepository
        {
            public function save(Notification $notification): void
            {
                throw new RuntimeException('Intentional DB failure');
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

        $event = $this->createEvent('evt-rollback-1');

        try {
            $handler->handle($event, '4000-0');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Intentional DB failure', $e->getMessage());
        }

        $this->assertSame(0, NotificationModel::query()->where('source_event_id', 'evt-rollback-1')->count());
        $this->assertSame(0, ConsumedEventModel::query()->where('event_id', 'evt-rollback-1')->count());
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
            ruleName: 'Critical stock deficit',
            severity: NotificationSeverity::CRITICAL,
            metric: 'quantity_available',
            comparator: 'lte',
            currentValue: 0.0,
            thresholdValue: 5.0,
            analyticalContext: [
                'target' => 'inventory',
                'sku' => 'OIL-5W40-001',
            ]
        );
    }
}
