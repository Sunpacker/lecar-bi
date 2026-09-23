<?php

declare(strict_types=1);

namespace NotificationService\Tests\Fakes;

use NotificationService\Integration\Infrastructure\Redis\RedisStreamClientInterface;

final class FakeRedisStreamClient implements RedisStreamClientInterface
{
    /** @var array<string, bool> */
    private array $acked = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $published = [];

    /** @var array<string, array<string, mixed>> */
    private array $staleMessages = [];

    /** @var array<string, array<string, mixed>> */
    private array $newMessages = [];

    public function ensureGroup(string $stream, string $group): void {}

    /**
     * @param  array<string, array<string, mixed>>  $messages
     */
    public function setStaleMessages(array $messages): void
    {
        $this->staleMessages = $messages;
    }

    /**
     * @param  array<string, array<string, mixed>>  $messages
     */
    public function setNewMessages(array $messages): void
    {
        $this->newMessages = $messages;
    }

    public function claimStale(string $stream, string $group, string $consumer, int $minIdleMs, int $count): array
    {
        $messages = $this->staleMessages;
        $this->staleMessages = [];

        return $messages;
    }

    public function readGroup(string $stream, string $group, string $consumer, int $count, int $blockTimeoutMs): array
    {
        $messages = $this->newMessages;
        $this->newMessages = [];

        return $messages;
    }

    public function ack(string $stream, string $group, string $messageId): void
    {
        $this->acked[$messageId] = true;
    }

    public function publish(string $stream, array $data): string
    {
        $this->published[$stream][] = $data;

        return (string) count($this->published[$stream]);
    }

    public function isAcked(string $messageId): bool
    {
        return $this->acked[$messageId] ?? false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function publishedTo(string $stream): array
    {
        return $this->published[$stream] ?? [];
    }
}
