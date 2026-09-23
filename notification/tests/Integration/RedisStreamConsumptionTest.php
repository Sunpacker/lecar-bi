<?php

declare(strict_types=1);

namespace NotificationService\Tests\Integration;

use NotificationService\Integration\Application\IntegrationEventRouter;
use NotificationService\Integration\Infrastructure\Redis\RedisDeadLetterPublisher;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamConsumer;
use NotificationService\Notification\Application\AlertTriggeredV1Decoder;
use NotificationService\Notification\Application\ConsumeAlertTriggered;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Domain\Notification;
use NotificationService\Notification\Domain\NotificationId;
use NotificationService\Notification\Infrastructure\Persistence\DuplicateEventException;
use NotificationService\Tests\Fakes\FakeRedisStreamClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RedisStreamConsumptionTest extends TestCase
{
    private FakeRedisStreamClient $redisClient;

    private RedisDeadLetterPublisher $deadLetterPublisher;

    private InMemoryNotificationRepository $notificationRepo;

    private InMemoryConsumedEventRepository $consumedEventRepo;

    private InMemoryTransactionManager $transactionManager;

    private RedisStreamConsumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisClient = new FakeRedisStreamClient;
        $this->deadLetterPublisher = new RedisDeadLetterPublisher(
            $this->redisClient,
            'autobi.integration-events.dead-letter'
        );

        $this->notificationRepo = new InMemoryNotificationRepository;
        $this->consumedEventRepo = new InMemoryConsumedEventRepository;
        $this->transactionManager = new InMemoryTransactionManager;

        $useCase = new ConsumeAlertTriggered(
            $this->notificationRepo,
            $this->consumedEventRepo,
            $this->transactionManager
        );

        $decoder = new AlertTriggeredV1Decoder;
        $router = new IntegrationEventRouter($decoder, $useCase);

        $this->consumer = new RedisStreamConsumer(
            client: $this->redisClient,
            router: $router,
            deadLetterPublisher: $this->deadLetterPublisher,
            logger: new NullLogger,
            streamName: 'autobi.integration-events',
            groupName: 'notification-service-v1',
            consumerName: 'worker-test'
        );
    }

    public function test_end_to_end_consumption_saves_notification_and_consumed_event(): void
    {
        $fixtureJson = (string) file_get_contents(
            dirname(__DIR__, 3).'/contracts/events/alert-triggered.v1.example.json'
        );
        $envelope = json_decode($fixtureJson, true, 512, JSON_THROW_ON_ERROR);

        $this->redisClient->setNewMessages([
            '1000-0' => [
                'event_id' => $envelope['event_id'],
                'event_type' => $envelope['event_type'],
                'event_version' => (string) $envelope['event_version'],
                'occurred_at' => $envelope['occurred_at'],
                'producer' => $envelope['producer'],
                'workspace_id' => $envelope['workspace_id'],
                'aggregate_type' => $envelope['aggregate']['type'],
                'aggregate_id' => $envelope['aggregate']['id'],
                'payload' => json_encode($envelope['payload'], JSON_THROW_ON_ERROR),
            ],
        ]);

        $processedCount = $this->consumer->consumeCycle();

        $this->assertSame(1, $processedCount);
        $this->assertTrue($this->redisClient->isAcked('1000-0'));

        // Verify notification saved
        $notifications = $this->notificationRepo->all();
        $this->assertCount(1, $notifications);
        $notification = $notifications[0];
        $this->assertSame($envelope['event_id'], $notification->sourceEventId());
        $this->assertSame($envelope['workspace_id'], $notification->workspaceId());
        $this->assertSame('critical', $notification->severity()->value);
        $this->assertSame('Reorder Point — Тормозные колодки', $notification->title());
        $this->assertStringContainsString('Reorder Point — Тормозные колодки', $notification->body());
        $this->assertStringContainsString('quantity_available lte 20', $notification->body());

        // Verify consumed_events entry saved
        $this->assertTrue($this->consumedEventRepo->hasBeenProcessed($envelope['event_id']));
    }

    public function test_duplicate_consumption_is_idempotent(): void
    {
        $fixtureJson = (string) file_get_contents(
            dirname(__DIR__, 3).'/contracts/events/alert-triggered.v1.example.json'
        );
        $envelope = json_decode($fixtureJson, true, 512, JSON_THROW_ON_ERROR);

        $message = [
            'event_id' => $envelope['event_id'],
            'event_type' => $envelope['event_type'],
            'event_version' => (string) $envelope['event_version'],
            'occurred_at' => $envelope['occurred_at'],
            'producer' => $envelope['producer'],
            'workspace_id' => $envelope['workspace_id'],
            'aggregate_type' => $envelope['aggregate']['type'],
            'aggregate_id' => $envelope['aggregate']['id'],
            'payload' => json_encode($envelope['payload'], JSON_THROW_ON_ERROR),
        ];

        // First cycle
        $this->redisClient->setNewMessages(['1000-0' => $message]);
        $this->consumer->consumeCycle();
        $this->assertCount(1, $this->notificationRepo->all());

        // Second cycle with same event_id
        $this->redisClient->setNewMessages(['1001-0' => $message]);
        $processedCount = $this->consumer->consumeCycle();

        $this->assertSame(1, $processedCount);
        $this->assertTrue($this->redisClient->isAcked('1001-0'));
        // Count must still be 1 (no duplicate notification)
        $this->assertCount(1, $this->notificationRepo->all());
    }

    public function test_malformed_event_goes_to_dead_letter_and_does_not_save_notification(): void
    {
        $this->redisClient->setNewMessages([
            '2000-0' => [
                'event_id' => 'malformed-evt',
                'event_type' => 'alert.triggered',
                'event_version' => '1',
                'occurred_at' => 'not-a-timestamp',
                'workspace_id' => '',
                'aggregate_type' => 'alert',
                'aggregate_id' => '',
                'payload' => 'not-json',
            ],
        ]);

        $this->consumer->consumeCycle();

        $this->assertTrue($this->redisClient->isAcked('2000-0'));
        $this->assertCount(0, $this->notificationRepo->all());

        $dlqMessages = $this->redisClient->publishedTo('autobi.integration-events.dead-letter');
        $this->assertCount(1, $dlqMessages);
        $this->assertSame('2000-0', $dlqMessages[0]['source_stream_id']);
        $this->assertSame('malformed-evt', $dlqMessages[0]['source_event_id']);
        $this->assertStringContainsString('Malformed JSON envelope', (string) $dlqMessages[0]['reason']);
    }

    public function test_unsupported_version_goes_to_dead_letter(): void
    {
        $this->redisClient->setNewMessages([
            '3000-0' => [
                'event_id' => 'unsupported-ver-evt',
                'event_type' => 'alert.triggered',
                'event_version' => '99',
                'workspace_id' => 'ws-1',
            ],
        ]);

        $this->consumer->consumeCycle();

        $this->assertTrue($this->redisClient->isAcked('3000-0'));
        $this->assertCount(0, $this->notificationRepo->all());

        $dlqMessages = $this->redisClient->publishedTo('autobi.integration-events.dead-letter');
        $this->assertCount(1, $dlqMessages);
        $this->assertStringContainsString('Unsupported event_version', (string) $dlqMessages[0]['reason']);
    }
}

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<string, Notification> */
    private array $notifications = [];

    public function save(Notification $notification): void
    {
        $this->notifications[$notification->id()->value()] = $notification;
    }

    public function findById(NotificationId $id): ?Notification
    {
        return $this->notifications[$id->value()] ?? null;
    }

    public function findBySourceEventId(string $sourceEventId): ?Notification
    {
        foreach ($this->notifications as $notification) {
            if ($notification->sourceEventId() === $sourceEventId) {
                return $notification;
            }
        }

        return null;
    }

    /**
     * @return list<Notification>
     */
    public function all(): array
    {
        return array_values($this->notifications);
    }
}

final class InMemoryConsumedEventRepository implements ConsumedEventRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $consumed = [];

    public function hasBeenProcessed(string $eventId): bool
    {
        return isset($this->consumed[$eventId]);
    }

    public function register(
        string $eventId,
        string $streamMessageId,
        string $eventType,
        int $eventVersion,
        string $producer,
        string $workspaceId,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $processedAt
    ): void {
        if (isset($this->consumed[$eventId])) {
            throw new DuplicateEventException(
                "Duplicate event: {$eventId}"
            );
        }

        $this->consumed[$eventId] = [
            'event_id' => $eventId,
            'stream_message_id' => $streamMessageId,
            'event_type' => $eventType,
            'event_version' => $eventVersion,
            'producer' => $producer,
            'workspace_id' => $workspaceId,
            'occurred_at' => $occurredAt,
            'processed_at' => $processedAt,
        ];
    }
}

final class InMemoryTransactionManager implements TransactionManager
{
    public function transaction(\Closure $operation): mixed
    {
        return $operation();
    }
}
