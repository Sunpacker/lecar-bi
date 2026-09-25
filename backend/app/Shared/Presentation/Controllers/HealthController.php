<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Controllers;

use App\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;
use App\Shared\Infrastructure\Health\OutboxHealthService;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __construct(
        private readonly DependencyHealthCheckerInterface $checker
    ) {}

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'analytics',
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = $this->checker->check();
        $isHealthy = $checks['database'] === 'ok' && $checks['redis'] === 'ok';
        $statusCode = $isHealthy ? 200 : 503;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'service' => 'analytics',
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
            'service' => 'analytics',
            'version' => 'v1',
            'checks' => $checks,
        ], $statusCode);
    }

    public function outbox(OutboxHealthService $service): JsonResponse
    {
        $health = $service->checkHealth();

        return response()->json($health);
    }
}
