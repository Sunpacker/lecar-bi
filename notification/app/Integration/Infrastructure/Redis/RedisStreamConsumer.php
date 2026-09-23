<?php

declare(strict_types=1);

namespace NotificationService\Integration\Infrastructure\Redis;

use NotificationService\Integration\Application\IntegrationEventRouterInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class RedisStreamConsumer
{
    public function __construct(
        private readonly RedisStreamClientInterface $client,
        private readonly IntegrationEventRouterInterface $router,
        private readonly RedisDeadLetterPublisher $deadLetterPublisher,
        private readonly LoggerInterface $logger,
        private readonly string $streamName,
        private readonly string $groupName,
        private readonly string $consumerName,
        private readonly int $batchSize = 10,
        private readonly int $blockTimeoutMs = 2000,
        private readonly int $staleIdleMs = 60000
    ) {}

    public function init(): void
    {
        $this->client->ensureGroup($this->streamName, $this->groupName);
    }

    /**
     * Runs a single consumption cycle: first claims stale messages, then reads new batch.
     *
     * @return int Number of messages processed
     */
    public function consumeCycle(): int
    {
        $processedCount = 0;

        // 1. Recover stale pending messages
        $staleMessages = $this->client->claimStale(
            $this->streamName,
            $this->groupName,
            $this->consumerName,
            $this->staleIdleMs,
            $this->batchSize
        );

        foreach ($staleMessages as $messageId => $fields) {
            $this->processMessage($messageId, $fields);
            $processedCount++;
        }

        // 2. Read new messages
        $newMessages = $this->client->readGroup(
            $this->streamName,
            $this->groupName,
            $this->consumerName,
            $this->batchSize,
            $this->blockTimeoutMs
        );

        foreach ($newMessages as $messageId => $fields) {
            $this->processMessage($messageId, $fields);
            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function processMessage(string $messageId, array $fields): void
    {
        $startTime = microtime(true);
        $eventId = isset($fields['event_id']) && is_string($fields['event_id']) ? $fields['event_id'] : null;

        try {
            $result = $this->router->route($messageId, $fields);

            if ($result->shouldAck()) {
                $this->client->ack($this->streamName, $this->groupName, $messageId);

                $this->logger->info('Integration event processed successfully', [
                    'stream_message_id' => $messageId,
                    'event_id' => $result->eventId ?? $eventId,
                    'outcome' => $result->status,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return;
            }

            if ($result->isDeadLetter()) {
                $this->deadLetterPublisher->publish(
                    sourceStream: $this->streamName,
                    sourceStreamId: $messageId,
                    reason: $result->reason ?? 'Dead letter',
                    rawFields: $fields,
                    sourceEventId: $result->eventId ?? $eventId
                );

                $this->client->ack($this->streamName, $this->groupName, $messageId);

                $this->logger->warning('Integration event rejected and moved to dead-letter stream', [
                    'stream_message_id' => $messageId,
                    'event_id' => $result->eventId ?? $eventId,
                    'reason' => $result->reason,
                    'outcome' => 'dead_letter',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return;
            }
        } catch (Throwable $e) {
            // Transient failure: log sanitized error and do NOT ack, so message remains in PEL for retry
            $this->logger->error('Transient error while processing integration event, message retained in PEL', [
                'stream_message_id' => $messageId,
                'event_id' => $eventId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        }
    }
}
