<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use NotificationService\Http\Middleware\TraceContextMiddleware;
use NotificationService\Tests\TestCase;

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

    public function test_assigns_and_returns_request_id(): void
    {
        $request = Request::create('/api/v1/health/live', 'GET');
        $response = $this->middleware->handle($request, function ($req) {
            $requestId = Context::get('request_id');
            $this->assertNotNull($requestId);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $requestId);

            return new Response('ok');
        });

        $this->assertTrue($response->headers->has('X-Request-Id'));
        $this->assertSame(Context::get('request_id'), $response->headers->get('X-Request-Id'));
    }

    public function test_preserves_valid_incoming_request_id(): void
    {
        $request = Request::create('/api/v1/health/live', 'GET');
        $request->headers->set('X-Request-Id', 'notif-req-777');

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertSame('notif-req-777', Context::get('request_id'));

            return new Response('ok');
        });

        $this->assertSame('notif-req-777', $response->headers->get('X-Request-Id'));
    }
}
