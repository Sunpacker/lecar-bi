<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Cache;

use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsResultCacheTest extends TestCase
{
    #[Test]
    public function it_retrieves_item_on_cache_hit(): void
    {
        $mockStore = Mockery::mock(CacheRepository::class);
        $mockStore->shouldReceive('get')
            ->once()
            ->with('test:key')
            ->andReturn(['cached_data' => 123]);

        $cache = new AnalyticsResultCache($mockStore);

        $result = $cache->get('test:key');

        self::assertSame(['cached_data' => 123], $result);
    }

    #[Test]
    public function it_returns_null_and_logs_sanitized_warning_when_store_throws_on_get(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Analytics cache read failure')
                    && $context['dataset'] === 'sales'
                    && $context['workspace_id'] === 'ws-1'
                    && $context['operation'] === 'sales_overview'
                    && $context['exception'] === \RuntimeException::class;
            });

        $mockStore = Mockery::mock(CacheRepository::class);
        $mockStore->shouldReceive('get')
            ->once()
            ->with('test:key')
            ->andThrow(new \RuntimeException('Redis connection lost'));

        $cache = new AnalyticsResultCache($mockStore);

        $result = $cache->get('test:key', [
            'dataset' => 'sales',
            'workspace_id' => 'ws-1',
            'operation' => 'sales_overview',
        ]);

        self::assertNull($result);
    }

    #[Test]
    public function remember_executes_callback_and_caches_on_cache_miss(): void
    {
        $mockStore = Mockery::mock(CacheRepository::class);
        $mockStore->shouldReceive('get')
            ->once()
            ->with('test:key')
            ->andReturnNull();

        $mockStore->shouldReceive('put')
            ->once()
            ->with('test:key', 'fresh_value', 120);

        $cache = new AnalyticsResultCache($mockStore);

        $invoked = false;
        $result = $cache->remember('test:key', 120, function () use (&$invoked) {
            $invoked = true;

            return 'fresh_value';
        });

        self::assertTrue($invoked);
        self::assertSame('fresh_value', $result);
    }

    #[Test]
    public function remember_fails_open_when_cache_fails_and_returns_fresh_result(): void
    {
        Log::shouldReceive('warning')->twice();

        $mockStore = Mockery::mock(CacheRepository::class);
        $mockStore->shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('Redis connection timeout'));

        $mockStore->shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('Redis write error'));

        $cache = new AnalyticsResultCache($mockStore);

        $result = $cache->remember('test:key', 120, function () {
            return 'fresh_db_value';
        }, [
            'dataset' => 'inventory',
            'workspace_id' => 'ws-99',
            'operation' => 'inventory_summary',
        ]);

        self::assertSame('fresh_db_value', $result);
    }
}
