<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class DefaultDependencyHealthChecker implements DependencyHealthCheckerInterface
{
    public function check(): array
    {
        $dbStatus = 'ok';
        $redisStatus = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $dbStatus = 'error';
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            $redisStatus = 'error';
        }

        return [
            'database' => $dbStatus,
            'redis' => $redisStatus,
        ];
    }
}
