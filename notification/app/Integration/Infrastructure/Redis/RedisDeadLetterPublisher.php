<?php

declare(strict_types=1);

namespace NotificationService\Integration\Infrastructure\Redis;

use DateTimeImmutable;

final class RedisDeadLetterPublisher
{
    public function __construct(
        private readonly RedisStreamClientInterface $client,
        private readonly string $deadLetterStream
    ) {}

    /**
     * @param  array<string, mixed>  $rawFields
     */
    public function publish(
        string $sourceStream,
        string $sourceStreamId,
        string $reason,
        array $rawFields,
        ?string $sourceEventId = null
    ): string {
        $encoded = json_encode($rawFields, JSON_UNESCAPED_UNICODE);
        $payloadHash = hash('sha256', $encoded !== false ? $encoded : '');

        $data = [
            'source_stream' => $sourceStream,
            'source_stream_id' => $sourceStreamId,
            'source_event_id' => $sourceEventId ?? 'unknown',
            'reason' => $reason,
            'payload_hash' => $payloadHash,
            'occurred_at' => (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),
            'raw_fields' => $encoded !== false ? $encoded : '{}',
        ];

        return $this->client->publish($this->deadLetterStream, $data);
    }
}
