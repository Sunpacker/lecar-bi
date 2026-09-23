<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Integration;

use Mockery;
use NotificationService\Integration\Application\IntegrationEventRouterInterface;
use NotificationService\Integration\Application\RouteResult;
use NotificationService\Integration\Infrastructure\Redis\RedisDeadLetterPublisher;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamConsumer;
use NotificationService\Tests\Fakes\FakeRedisStreamClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class RedisStreamConsumerTest extends TestCase
{
    private FakeRedisStreamClient $client;

    private RedisDeadLetterPublisher $deadLetterPublisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new FakeRedisStreamClient;
        $this->deadLetterPublisher = new RedisDeadLetterPublisher(
            $this->client,
            'autobi.integration-events.dead-letter'
        );
    }

    public function test_processed_event_is_acked(): void
    {
        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('100-0', ['event_id' => 'evt-1'])
            ->once()
            ->andReturn(RouteResult::processed('evt-1'));

        $consumer = $this->createConsumer($router);
        $consumer->processMessage('100-0', ['event_id' => 'evt-1']);

        $this->assertTrue($this->client->isAcked('100-0'));
    }

    public function test_duplicate_event_is_acked(): void
    {
        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('200-0', ['event_id' => 'evt-dup'])
            ->once()
            ->andReturn(RouteResult::duplicate('evt-dup'));

        $consumer = $this->createConsumer($router);
        $consumer->processMessage('200-0', ['event_id' => 'evt-dup']);

        $this->assertTrue($this->client->isAcked('200-0'));
    }

    public function test_ignored_event_is_acked(): void
    {
        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('300-0', ['event_type' => 'other'])
            ->once()
            ->andReturn(RouteResult::ignored('Unhandled'));

        $consumer = $this->createConsumer($router);
        $consumer->processMessage('300-0', ['event_type' => 'other']);

        $this->assertTrue($this->client->isAcked('300-0'));
    }

    public function test_dead_letter_is_published_then_acked(): void
    {
        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('400-0', ['malformed' => true])
            ->once()
            ->andReturn(RouteResult::deadLetter('Invalid schema', ['malformed' => true], 'evt-bad'));

        $consumer = $this->createConsumer($router);
        $consumer->processMessage('400-0', ['malformed' => true]);

        $this->assertTrue($this->client->isAcked('400-0'));
        $this->assertCount(1, $this->client->publishedTo('autobi.integration-events.dead-letter'));
        $dlqEntry = $this->client->publishedTo('autobi.integration-events.dead-letter')[0];
        $this->assertSame('400-0', $dlqEntry['source_stream_id']);
        $this->assertSame('Invalid schema', $dlqEntry['reason']);
    }

    public function test_transient_failure_does_not_ack_message(): void
    {
        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('500-0', ['event_id' => 'evt-fail'])
            ->once()
            ->andThrow(new RuntimeException('Transient database failure'));

        $consumer = $this->createConsumer($router);
        $consumer->processMessage('500-0', ['event_id' => 'evt-fail']);

        // Assert message was NOT acked, so it remains in PEL for next retry / claim
        $this->assertFalse($this->client->isAcked('500-0'));
    }

    public function test_consume_cycle_processes_stale_then_new_messages(): void
    {
        $this->client->setStaleMessages([
            'stale-1' => ['event_id' => 'evt-stale'],
        ]);
        $this->client->setNewMessages([
            'new-1' => ['event_id' => 'evt-new'],
        ]);

        $router = Mockery::mock(IntegrationEventRouterInterface::class);
        $router->shouldReceive('route')
            ->with('stale-1', ['event_id' => 'evt-stale'])
            ->once()
            ->andReturn(RouteResult::processed('evt-stale'));
        $router->shouldReceive('route')
            ->with('new-1', ['event_id' => 'evt-new'])
            ->once()
            ->andReturn(RouteResult::processed('evt-new'));

        $consumer = $this->createConsumer($router);
        $processed = $consumer->consumeCycle();

        $this->assertSame(2, $processed);
        $this->assertTrue($this->client->isAcked('stale-1'));
        $this->assertTrue($this->client->isAcked('new-1'));
    }

    private function createConsumer(IntegrationEventRouterInterface $router): RedisStreamConsumer
    {
        return new RedisStreamConsumer(
            client: $this->client,
            router: $router,
            deadLetterPublisher: $this->deadLetterPublisher,
            logger: new NullLogger,
            streamName: 'autobi.integration-events',
            groupName: 'notification-service-v1',
            consumerName: 'worker-test'
        );
    }
}
