<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Http\Middleware\TraceContextMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

final class TraceContextMiddlewareTest extends TestCase
{
    private TraceContextMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TraceContextMiddleware;
        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    public function test_generates_new_request_id_when_missing(): void
    {
        $request = Request::create('/api/v1/health', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            $requestId = Context::get('request_id');
            $this->assertNotNull($requestId);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $requestId);

            return new Response('ok');
        });

        $this->assertTrue($response->headers->has('X-Request-Id'));
        $this->assertSame(Context::get('request_id'), $response->headers->get('X-Request-Id'));
    }

    public function test_accepts_and_sanitizes_valid_incoming_request_id(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->headers->set('X-Request-Id', 'client-req-12345');

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertSame('client-req-12345', Context::get('request_id'));

            return new Response('ok');
        });

        $this->assertSame('client-req-12345', $response->headers->get('X-Request-Id'));
    }

    public function test_replaces_invalid_incoming_request_id_with_uuid(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->headers->set('X-Request-Id', 'invalid<script>id!@#$%^&*()');

        $response = $this->middleware->handle($request, function ($req) {
            $requestId = Context::get('request_id');
            $this->assertNotSame('invalid<script>id!@#$%^&*()', $requestId);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $requestId);

            return new Response('ok');
        });

        $this->assertNotSame('invalid<script>id!@#$%^&*()', $response->headers->get('X-Request-Id'));
    }

    public function test_sets_operation_in_context(): void
    {
        $request = Request::create('/api/v1/health', 'GET');

        $this->middleware->handle($request, function ($req) {
            $this->assertSame('GET api/v1/health', Context::get('operation'));

            return new Response('ok');
        });
    }
}
