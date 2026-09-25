<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:127.0.0.1');
        RateLimiter::clear('imports:ws-1');
    }

    public function test_login_rate_limiting_returns_429_when_exceeded(): void
    {
        // 5 requests allowed
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'wrong@autobi.internal',
                'password' => 'wrong-pass',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th request triggers rate limit
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@autobi.internal',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Too many login attempts. Please try again later.',
            'code' => 'TOO_MANY_REQUESTS',
        ]);
        $response->assertHeader('Retry-After');
    }

    public function test_imports_rate_limiting_returns_429_when_exceeded(): void
    {
        // 10 requests allowed for ws-1
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/imports/fake-id/retry', [], [
                'X-User-Id' => 'user-1',
                'X-Workspace-Id' => 'ws-1',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 11th request triggers rate limit
        $response = $this->postJson('/api/v1/imports/fake-id/retry', [], [
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Too many import requests for this workspace. Please try again later.',
            'code' => 'TOO_MANY_REQUESTS',
        ]);
    }
}
