<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Integration;

use DateTimeImmutable;
use NotificationService\Integration\Application\IntegrationEventRouter;
use NotificationService\Notification\Application\AlertTriggeredV1Decoder;
use NotificationService\Notification\Application\ConsumeAlertTriggered;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Domain\Notification;
use PHPUnit\Framework\TestCase;

final class IntegrationEventRouterTest extends TestCase
{
    private IntegrationEventRouter $router;

    private InMemoryNotificationRepository $notificationRepo;

    private InMemoryConsumedEventRepository $consumedEventRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationRepo = new InMemoryNotificationRepository;
        $this->consumedEventRepo = new InMemoryConsumedEventRepository;
        $txManager = new class implements TransactionManager
        {
            public function transaction(\Closure $operation): mixed
            {
                return $operation();
            }
        };

        $consumer = new ConsumeAlertTriggered(
            $this->notificationRepo,
            $this->consumedEventRepo,
            $txManager
        );

        $this->router = new IntegrationEventRouter(
            new AlertTriggeredV1Decoder,
            $consumer
        );
    }

    public function test_routes_valid_alert_triggered_event_and_returns_processed(): void
    {
        $fields = $this->createValidFields('evt-router-1');

        $result = $this->router->route('100-0', $fields);

        $this->assertTrue($result->shouldAck());
        $this->assertSame('processed', $result->status);
        $this->assertSame('evt-router-1', $result->eventId);
        $this->assertNotNull($this->notificationRepo->findBySourceEventId('evt-router-1'));
    }

    public function test_routes_duplicate_event_and_returns_duplicate_with_ack(): void
    {
        $fields = $this->createValidFields('evt-dup-2');

        $first = $this->router->route('200-0', $fields);
        $this->assertSame('processed', $first->status);

        $second = $this->router->route('200-1', $fields);
        $this->assertTrue($second->shouldAck());
        $this->assertSame('duplicate', $second->status);
        $this->assertSame('evt-dup-2', $second->eventId);
    }

    public function test_routes_unhandled_event_type_and_returns_ignored_with_ack(): void
    {
        $fields = [
            'event_id' => 'evt-other-1',
            'event_type' => 'customer.registered',
            'event_version' => '1',
            'payload' => json_encode(['foo' => 'bar']),
        ];

        $result = $this->router->route('300-0', $fields);

        $this->assertTrue($result->shouldAck());
        $this->assertSame('ignored', $result->status);
        $this->assertStringContainsString('Unhandled event_type', (string) $result->reason);
    }

    public function test_routes_malformed_payload_and_returns_dead_letter(): void
    {
        $fields = [
            'event_id' => 'evt-bad-1',
            'event_type' => 'alert.triggered',
            'event_version' => '1',
            'payload' => '{invalid-json',
        ];

        $result = $this->router->route('400-0', $fields);

        $this->assertFalse($result->shouldAck());
        $this->assertTrue($result->isDeadLetter());
        $this->assertStringContainsString('Malformed JSON', (string) $result->reason);
    }

    public function test_routes_unsupported_version_and_returns_dead_letter(): void
    {
        $envelope = $this->validEnvelope('evt-unsupported');
        $envelope['event_version'] = 99;

        $fields = [
            'event_id' => 'evt-unsupported',
            'event_type' => 'alert.triggered',
            'event_version' => '99',
            'payload' => json_encode($envelope),
        ];

        $result = $this->router->route('500-0', $fields);

        $this->assertFalse($result->shouldAck());
        $this->assertTrue($result->isDeadLetter());
        $this->assertStringContainsString('Unsupported event_version', (string) $result->reason);
    }

    /**
     * @return array<string, mixed>
     */
    private function createValidFields(string $eventId): array
    {
        $envelope = $this->validEnvelope($eventId);

        return [
            'event_id' => $eventId,
            'event_type' => 'alert.triggered',
            'event_version' => '1',
            'payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validEnvelope(string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => 'alert.triggered',
            'event_version' => 1,
            'occurred_at' => '2026-09-23T10:00:00+00:00',
            'producer' => 'analytics',
            'workspace_id' => 'ws-1',
            'aggregate' => [
                'type' => 'alert',
                'id' => 'alt-1',
            ],
            'payload' => [
                'rule_id' => 'rule-1',
                'rule_name' => 'Low Stock Alert',
                'severity' => 'critical',
                'metric' => 'quantity_available',
                'comparator' => 'lte',
                'current_value' => 2,
                'threshold_value' => 10,
                'analytical_context' => [
                    'target' => 'inventory',
                ],
            ],
        ];
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
