<?php

declare(strict_types=1);

namespace NotificationService\Integration\Infrastructure\Redis;

use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

final class PredisStreamClient implements RedisStreamClientInterface
{
    public function __construct(
        private readonly string $connectionName = 'integration_events'
    ) {}

    public function ensureGroup(string $stream, string $group): void
    {
        try {
            Redis::connection($this->connectionName)->command('xgroup', [
                'CREATE',
                $stream,
                $group,
                '0',
                'MKSTREAM',
            ]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'BUSYGROUP') || str_contains($msg, 'already exists')) {
                return;
            }

            throw new RuntimeException("Failed to ensure consumer group '{$group}' on stream '{$stream}': {$msg}", 0, $e);
        }
    }

    public function claimStale(string $stream, string $group, string $consumer, int $minIdleMs, int $count): array
    {
        try {
            $response = Redis::connection($this->connectionName)->command('xautoclaim', [
                $stream,
                $group,
                $consumer,
                (string) $minIdleMs,
                '0-0',
                'COUNT',
                (string) $count,
            ]);

            // XAUTOCLAIM returns: [0 => next_start_id, 1 => [[id, [fields]], ...], 2 => [deleted_ids]]
            if (is_array($response) && isset($response[1]) && is_array($response[1])) {
                return $this->parseMessageEntries($response[1]);
            }

            return [];
        } catch (Throwable) {
            return [];
        }
    }

    public function readGroup(string $stream, string $group, string $consumer, int $count, int $blockTimeoutMs): array
    {
        try {
            $response = Redis::connection($this->connectionName)->command('xreadgroup', [
                'GROUP',
                $group,
                $consumer,
                'COUNT',
                (string) $count,
                'BLOCK',
                (string) $blockTimeoutMs,
                'STREAMS',
                $stream,
                '>',
            ]);

            if (! is_array($response) || empty($response)) {
                return [];
            }

            $result = [];
            // Format of XREADGROUP: [ [0 => stream_name, 1 => [ [0 => id, 1 => [fields]], ... ] ] ]
            foreach ($response as $streamData) {
                if (is_array($streamData) && isset($streamData[1]) && is_array($streamData[1])) {
                    $result += $this->parseMessageEntries($streamData[1]);
                }
            }

            return $result;
        } catch (Throwable $e) {
            throw new RuntimeException("Redis XREADGROUP error on stream '{$stream}': {$e->getMessage()}", 0, $e);
        }
    }

    public function ack(string $stream, string $group, string $messageId): void
    {
        try {
            Redis::connection($this->connectionName)->command('xack', [
                $stream,
                $group,
                [$messageId],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to XACK message '{$messageId}' on stream '{$stream}': {$e->getMessage()}", 0, $e);
        }
    }

    public function publish(string $stream, array $data): string
    {
        try {
            $flat = [];
            foreach ($data as $k => $v) {
                $flat[] = (string) $k;
                $flat[] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }

            $result = Redis::connection($this->connectionName)->command('xadd', array_merge([$stream, '*'], $flat));

            return (string) $result;
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to XADD to stream '{$stream}': {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @param  array<mixed>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function parseMessageEntries(array $entries): array
    {
        $result = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry[0]) || ! isset($entry[1])) {
                continue;
            }

            $id = (string) $entry[0];
            $rawFields = $entry[1];

            $fields = [];
            if (is_array($rawFields)) {
                $count = count($rawFields);
                // Check if associative
                if (array_keys($rawFields) !== range(0, $count - 1)) {
                    $fields = $rawFields;
                } else {
                    for ($i = 0; $i < $count; $i += 2) {
                        if (isset($rawFields[$i]) && isset($rawFields[$i + 1])) {
                            $fields[(string) $rawFields[$i]] = $rawFields[$i + 1];
                        }
                    }
                }
            }

            $result[$id] = $fields;
        }

        return $result;
    }
}
