<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class OutboxHealthService
{
    public const REDIS_HEARTBEAT_KEY = 'autobi:outbox:publisher:heartbeat';

    /**
     * @return array{
     *     status: 'ok'|'warning'|'degraded',
     *     service: 'analytics',
     *     outbox: array{
     *         pending_count: int,
     *         oldest_pending_age_seconds: int|null,
     *         failed_count: int
     *     },
     *     publisher: array{
     *         last_run_at: string|null,
     *         last_run_age_seconds: int|null
     *     }
     * }
     */
    public function checkHealth(): array
    {
        $pendingCount = 0;
        $failedCount = 0;
        $oldestPendingAgeSeconds = null;
        $dbError = false;

        try {
            $pendingCount = (int) DB::table('outbox_messages')->where('status', 'pending')->count();
            $failedCount = (int) DB::table('outbox_messages')->where('status', 'failed')->count();

            if ($pendingCount > 0) {
                $oldestPending = DB::table('outbox_messages')
                    ->where('status', 'pending')
                    ->min('occurred_at');

                if ($oldestPending !== null) {
                    $oldestTime = new DateTimeImmutable((string) $oldestPending);
                    $oldestPendingAgeSeconds = max(0, time() - $oldestTime->getTimestamp());
                }
            }
        } catch (Throwable) {
            $dbError = true;
        }

        $lastRunAt = null;
        $lastRunAgeSeconds = null;

        try {
            $raw = Redis::connection()->get(self::REDIS_HEARTBEAT_KEY);
            if (is_string($raw)) {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    if (isset($data['iso']) && is_string($data['iso'])) {
                        $lastRunAt = $data['iso'];
                    }
                    if (isset($data['timestamp']) && is_numeric($data['timestamp'])) {
                        $lastRunAgeSeconds = max(0, time() - (int) $data['timestamp']);
                    }
                }
            }
        } catch (Throwable) {
            // Redis error handled gracefully
        }

        $status = 'ok';
        if ($dbError || $failedCount > 0 || ($oldestPendingAgeSeconds !== null && $oldestPendingAgeSeconds > 300)) {
            $status = 'degraded';
        } elseif (($oldestPendingAgeSeconds !== null && $oldestPendingAgeSeconds > 60) || ($lastRunAgeSeconds !== null && $lastRunAgeSeconds > 120)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'service' => 'analytics',
            'outbox' => [
                'pending_count' => $pendingCount,
                'oldest_pending_age_seconds' => $oldestPendingAgeSeconds,
                'failed_count' => $failedCount,
            ],
            'publisher' => [
                'last_run_at' => $lastRunAt,
                'last_run_age_seconds' => $lastRunAgeSeconds,
            ],
        ];
    }

    public static function recordPublisherHeartbeat(): void
    {
        try {
            $payload = json_encode([
                'timestamp' => time(),
                'iso' => (new DateTimeImmutable)->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR);

            Redis::connection()->setex(self::REDIS_HEARTBEAT_KEY, 86400, $payload);
        } catch (Throwable) {
            // Heartbeat write failure is non-fatal for worker processing
        }
    }
}
