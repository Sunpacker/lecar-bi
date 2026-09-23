<?php

declare(strict_types=1);

namespace NotificationService\Integration\Infrastructure\Redis;

interface RedisStreamClientInterface
{
    /**
     * Creates consumer group idempotently (XGROUP CREATE ... 0 MKSTREAM).
     */
    public function ensureGroup(string $stream, string $group): void;

    /**
     * Claims stale pending messages from idle/crashed consumers (XAUTOCLAIM).
     *
     * @return array<string, array<string, mixed>> Map of message_id => fields
     */
    public function claimStale(string $stream, string $group, string $consumer, int $minIdleMs, int $count): array;

    /**
     * Reads new messages via consumer group (XREADGROUP).
     *
     * @return array<string, array<string, mixed>> Map of message_id => fields
     */
    public function readGroup(string $stream, string $group, string $consumer, int $count, int $blockTimeoutMs): array;

    /**
     * Acknowledges message processing (XACK).
     */
    public function ack(string $stream, string $group, string $messageId): void;

    /**
     * Publishes data to a stream via XADD.
     *
     * @param  array<string, mixed>  $data
     */
    public function publish(string $stream, array $data): string;
}
