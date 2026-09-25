<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnalyticsResultCache
{
    public function __construct(
        private readonly ?CacheRepository $store = null
    ) {}

    private function getStore(): CacheRepository
    {
        return $this->store ?? Cache::store();
    }

    /**
     * Retrieves an item from the cache with fail-open semantics.
     * Returns null if not cached or if cache infrastructure fails.
     *
     * @param  array<string, mixed>  $sanitizedContext
     */
    public function get(string $key, array $sanitizedContext = []): mixed
    {
        try {
            return $this->getStore()->get($key);
        } catch (\Throwable $e) {
            $this->logFailure('read', $sanitizedContext, $e);

            return null;
        }
    }

    /**
     * Stores an item in the cache with fail-open semantics.
     *
     * @param  array<string, mixed>  $sanitizedContext
     */
    public function put(string $key, mixed $value, int $ttlSeconds, array $sanitizedContext = []): void
    {
        try {
            $this->getStore()->put($key, $value, $ttlSeconds);
        } catch (\Throwable $e) {
            $this->logFailure('write', $sanitizedContext, $e);
        }
    }

    /**
     * Attempts to read from cache; if miss or cache error, executes the fallback callback and stores result.
     * If cache infrastructure errors occur, the callback result is returned uncached without failing the request.
     *
     * @param  array<string, mixed>  $sanitizedContext
     */
    public function remember(string $key, int $ttlSeconds, callable $callback, array $sanitizedContext = []): mixed
    {
        $cached = $this->get($key, $sanitizedContext);

        if ($cached !== null) {
            return $cached;
        }

        // Execute SQL / read-model delegate without catching delegate exceptions
        $fresh = $callback();

        $this->put($key, $fresh, $ttlSeconds, $sanitizedContext);

        return $fresh;
    }

    /**
     * Sanitized failure logging without leaking payloads, credentials, or sensitive SQL params.
     *
     * @param  array<string, mixed>  $context
     */
    private function logFailure(string $action, array $context, \Throwable $e): void
    {
        Log::warning("Analytics cache {$action} failure; fallback to PostgreSQL", [
            'action' => $action,
            'dataset' => $context['dataset'] ?? 'unknown',
            'workspace_id' => $context['workspace_id'] ?? 'unknown',
            'operation' => $context['operation'] ?? 'unknown',
            'exception' => get_class($e),
        ]);
    }
}
