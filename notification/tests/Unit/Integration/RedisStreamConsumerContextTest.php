<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Integration;

use Illuminate\Support\Facades\Context;
use NotificationService\Integration\Application\IntegrationEventRouterInterface;
use NotificationService\Integration\Application\RouteResult;
use NotificationService\Integration\Infrastructure\Redis\RedisDeadLetterPublisher;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamClientInterface;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamConsumer;
use NotificationService\Tests\TestCase;
use Psr\Log\NullLogger;

final class RedisStreamConsumerContextTest extends TestCase
{
    public function test_sets_and_flushes_context_during_message_processing(): void
    {
        $client = $this->createMock(RedisStreamClientInterface::class);
        $client->expects($this->once())->method('ack');

        $router = $this->createMock(IntegrationEventRouterInterface::class);
        $router->expects($this->once())
            ->method('route')
            ->willReturnCallback(function ($msgId, $fields) {
                $this->assertSame('corr-test-123', Context::get('correlation_id'));
                $this->assertSame('evt-test-456', Context::get('event_id'));
                $this->assertSame('12345-0', Context::get('stream_message_id'));

                return RouteResult::processed('evt-test-456');
            });

        $deadLetter = new RedisDeadLetterPublisher($client, 'autobi.integration-events.dead-letter');

        $consumer = new RedisStreamConsumer(
            client: $client,
            router: $router,
            deadLetterPublisher: $deadLetter,
            logger: new NullLogger,
            streamName: 'autobi.integration-events',
            groupName: 'test-group',
            consumerName: 'test-consumer'
        );

        $consumer->processMessage('12345-0', [
            'event_id' => 'evt-test-456',
            'correlation_id' => 'corr-test-123',
        ]);

        // After processMessage finishes, context must be flushed
        $this->assertNull(Context::get('correlation_id'));
        $this->assertNull(Context::get('event_id'));
    }
}
