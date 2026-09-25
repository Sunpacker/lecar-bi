<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Health;

use App\Shared\Infrastructure\Health\DefaultDependencyHealthChecker;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

final class DefaultDependencyHealthCheckerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_check_returns_ok_when_both_database_and_redis_respond(): void
    {
        $dbConn = Mockery::mock();
        $dbConn->shouldReceive('getPdo')->once()->andReturn(Mockery::mock(\PDO::class));
        DB::shouldReceive('connection')->once()->andReturn($dbConn);

        $redisConn = Mockery::mock();
        $redisConn->shouldReceive('ping')->once()->andReturn(true);
        Redis::shouldReceive('connection')->once()->andReturn($redisConn);

        $checker = new DefaultDependencyHealthChecker;
        $result = $checker->check();

        self::assertSame(['database' => 'ok', 'redis' => 'ok'], $result);
    }

    public function test_check_returns_error_for_database_when_connection_fails(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new Exception('DB connection failed'));

        $redisConn = Mockery::mock();
        $redisConn->shouldReceive('ping')->once()->andReturn(true);
        Redis::shouldReceive('connection')->once()->andReturn($redisConn);

        $checker = new DefaultDependencyHealthChecker;
        $result = $checker->check();

        self::assertSame(['database' => 'error', 'redis' => 'ok'], $result);
    }

    public function test_check_returns_error_for_redis_when_ping_fails(): void
    {
        $dbConn = Mockery::mock();
        $dbConn->shouldReceive('getPdo')->once()->andReturn(Mockery::mock(\PDO::class));
        DB::shouldReceive('connection')->once()->andReturn($dbConn);

        Redis::shouldReceive('connection')->once()->andThrow(new Exception('Redis connection failed'));

        $checker = new DefaultDependencyHealthChecker;
        $result = $checker->check();

        self::assertSame(['database' => 'ok', 'redis' => 'error'], $result);
    }
}
