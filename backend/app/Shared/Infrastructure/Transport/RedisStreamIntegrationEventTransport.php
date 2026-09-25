<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Transport;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Application\Ports\IntegrationEventTransportInterface;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * Publishes integration events to a Redis Stream via XADD.
 *
 * Uses a dedicated Redis connection (outbox) and database to isolate
 * integration events from Laravel queues, cache, and locks.
 *
 * At-least-once semantics: if the process crashes after XADD but before
 * markPublished(), the same event_id will be re-published on the next retry.
 * Consumers MUST deduplicate by event_id.
 */
final class RedisStreamIntegrationEventTransport implements IntegrationEventTransportInterface
{
    private readonly string $streamName;

    private readonly string $connectionName;

    public function __construct()
    {
        $this->streamName = (string) config('outbox.stream_name', 'autobi.integration-events');
        $this->connectionName = 'outbox';
    }

    /**
     * Publishes the event envelope to the Redis Stream via XADD.
     *
     * @return string Redis stream entry ID (e.g. "1234567890123-0")
     *
     * @throws RuntimeException on Redis failure
     */
    public function publish(IntegrationEvent $event): string
    {
        try {
            $envelope = $event->toEnvelope();

            $connection = Redis::connection($this->connectionName);
            $client = $connection->client();

            if (is_object($client) && method_exists($client, 'executeRaw')) {
                $result = $client->executeRaw([
                    'XADD',
                    $this->streamName,
                    '*',
                    'event_id',
                    $envelope['event_id'],
                    'event_type',
                    $envelope['event_type'],
                    'event_version',
                    (string) $envelope['event_version'],
                    'payload',
                    json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            } else {
                $result = $connection->xadd(
                    $this->streamName,
                    '*',
                    [
                        'event_id' => $envelope['event_id'],
                        'event_type' => $envelope['event_type'],
                        'event_version' => (string) $envelope['event_version'],
                        'payload' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]
                );
            }

            if ($result === false || (string) $result === '') {
                throw new RuntimeException('Redis XADD returned empty/false for stream: '.$this->streamName);
            }

            return (string) $result;
        } catch (\RedisException $e) {
            throw new RuntimeException('Failed to publish to Redis Stream: '.$this->streamName, 0, $e);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to publish to Redis Stream: '.$this->streamName.': '.$e->getMessage(), 0, $e);
        }
    }
}
