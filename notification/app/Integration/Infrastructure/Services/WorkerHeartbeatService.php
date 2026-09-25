<?php

declare(strict_types=1);

namespace NotificationService\Integration\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Redis;
use Throwable;

class WorkerHeartbeatService
{
    public const REDIS_HEARTBEAT_KEY = 'autobi:worker:notification-worker:heartbeat';

    public function __construct(
        private readonly string $connectionName = 'integration_events'
    ) {}

    public function recordHeartbeat(
        string $workerId = 'notification-worker-1',
        ?string $lastProcessedAt = null,
        int $processedCount = 0,
        int $failedCount = 0,
        string $status = 'running'
    ): void {
        try {
            $now = new DateTimeImmutable;
            $data = [
                'worker_id' => $workerId,
                'heartbeat_ts' => $now->getTimestamp(),
                'heartbeat_at' => $now->format(DATE_ATOM),
                'last_processed_at' => $lastProcessedAt,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'status' => $status,
                'pid' => getmypid() ?: null,
            ];

            Redis::connection($this->connectionName)->setex(
                self::REDIS_HEARTBEAT_KEY,
                86400,
                json_encode($data, JSON_THROW_ON_ERROR)
            );
        } catch (Throwable) {
            // Heartbeat failure shouldn't crash worker
        }
    }

    /**
     * @return array{
     *     status: 'ok'|'stale'|'down',
     *     service: 'notification',
     *     worker: array{
     *         worker_id: string|null,
     *         heartbeat_age_seconds: int|null,
     *         last_processed_at: string|null,
     *         last_processed_age_seconds: int|null,
     *         processed_count: int,
     *         failed_count: int,
     *         status: string|null
     *     },
     *     stream: array{
     *         pending_messages: int,
     *         dead_letter_count: int
     *     }
     * }
     */
    public function getWorkerStatus(int $staleThresholdSeconds = 30): array
    {
        $workerId = null;
        $heartbeatAgeSeconds = null;
        $lastProcessedAt = null;
        $lastProcessedAgeSeconds = null;
        $processedCount = 0;
        $failedCount = 0;
        $workerStatus = null;
        $healthStatus = 'down';

        try {
            $raw = Redis::connection($this->connectionName)->get(self::REDIS_HEARTBEAT_KEY);
            if (is_string($raw)) {
                /** @var array<string, mixed>|null $data */
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $workerId = isset($data['worker_id']) && is_string($data['worker_id']) ? $data['worker_id'] : null;
                    $workerStatus = isset($data['status']) && is_string($data['status']) ? $data['status'] : null;
                    $processedCount = (int) ($data['processed_count'] ?? 0);
                    $failedCount = (int) ($data['failed_count'] ?? 0);
                    $lastProcessedAt = isset($data['last_processed_at']) && is_string($data['last_processed_at']) ? $data['last_processed_at'] : null;

                    if (isset($data['heartbeat_ts']) && is_numeric($data['heartbeat_ts'])) {
                        $heartbeatAgeSeconds = max(0, time() - (int) $data['heartbeat_ts']);
                        $healthStatus = $heartbeatAgeSeconds <= $staleThresholdSeconds ? 'ok' : 'stale';
                    }

                    if ($lastProcessedAt !== null) {
                        try {
                            $lastProcTime = new DateTimeImmutable($lastProcessedAt);
                            $lastProcessedAgeSeconds = max(0, time() - $lastProcTime->getTimestamp());
                        } catch (Throwable) {
                            // ignore parse error
                        }
                    }
                }
            }
        } catch (Throwable) {
            $healthStatus = 'down';
        }

        $pendingMessages = 0;
        $deadLetterCount = 0;

        try {
            $streamName = (string) config('notification.stream_name', 'autobi.integration-events');
            $groupName = (string) config('notification.group_name', 'notification-service-v1');
            $deadLetterStream = (string) config('notification.dead_letter_stream', 'autobi.integration-events.dead-letter');

            $xpending = Redis::connection($this->connectionName)->command('xpending', [$streamName, $groupName]);
            if (is_array($xpending) && isset($xpending[0]) && is_numeric($xpending[0])) {
                $pendingMessages = (int) $xpending[0];
            }

            $xlen = Redis::connection($this->connectionName)->command('xlen', [$deadLetterStream]);
            if (is_numeric($xlen)) {
                $deadLetterCount = (int) $xlen;
            }
        } catch (Throwable) {
            // Stream check failure
        }

        return [
            'status' => $healthStatus,
            'service' => 'notification',
            'worker' => [
                'worker_id' => $workerId,
                'heartbeat_age_seconds' => $heartbeatAgeSeconds,
                'last_processed_at' => $lastProcessedAt,
                'last_processed_age_seconds' => $lastProcessedAgeSeconds,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'status' => $workerStatus,
            ],
            'stream' => [
                'pending_messages' => $pendingMessages,
                'dead_letter_count' => $deadLetterCount,
            ],
        ];
    }
}
