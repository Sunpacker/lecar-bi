<?php

declare(strict_types=1);

namespace NotificationService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use NotificationService\Integration\Infrastructure\Services\WorkerHeartbeatService;
use NotificationService\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;

final class HealthController
{
    public function __construct(
        private readonly DependencyHealthCheckerInterface $checker
    ) {}

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'notification',
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = $this->checker->check();
        $isHealthy = $checks['database'] === 'ok' && $checks['redis'] === 'ok';
        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'service' => 'notification',
            'checks' => $checks,
        ], $statusCode);
    }

    public function health(): JsonResponse
    {
        $checks = $this->checker->check();
        $isHealthy = $checks['database'] === 'ok' && $checks['redis'] === 'ok';
        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'service' => 'notification',
            'version' => 'v1',
            'checks' => $checks,
        ], $statusCode);
    }

    public function worker(WorkerHeartbeatService $service): JsonResponse
    {
        $status = $service->getWorkerStatus();
        $statusCode = $status['status'] === 'ok' ? 200 : 503;

        return response()->json($status, $statusCode);
    }
}
