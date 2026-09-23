<?php

declare(strict_types=1);

namespace NotificationService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'notification',
        ]);
    }

    public function ready(): JsonResponse
    {
        $dbStatus = 'ok';
        $redisStatus = 'ok';
        $isHealthy = true;

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $dbStatus = 'error';
            $isHealthy = false;
        }

        try {
            Redis::connection('integration_events')->ping();
        } catch (Throwable) {
            $redisStatus = 'error';
            $isHealthy = false;
        }

        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'service' => 'notification',
            'checks' => [
                'database' => $dbStatus,
                'redis' => $redisStatus,
            ],
        ], $statusCode);
    }
}
