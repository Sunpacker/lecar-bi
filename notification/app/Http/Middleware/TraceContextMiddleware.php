<?php

declare(strict_types=1);

namespace NotificationService\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TraceContextMiddleware
{
    private const REQUEST_ID_REGEX = '/^[a-zA-Z0-9_\-\.]{1,64}$/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawRequestId = $request->header('X-Request-Id');
        if (is_string($rawRequestId) && preg_match(self::REQUEST_ID_REGEX, trim($rawRequestId)) === 1) {
            $requestId = trim($rawRequestId);
        } else {
            $requestId = Str::uuid()->toString();
        }

        $rawCorrelationId = $request->header('X-Correlation-Id');
        if (is_string($rawCorrelationId) && preg_match(self::REQUEST_ID_REGEX, trim($rawCorrelationId)) === 1) {
            $correlationId = trim($rawCorrelationId);
        } else {
            $correlationId = $requestId;
        }

        $operation = $request->method().' '.($request->route()?->uri() ?? ltrim($request->path(), '/'));

        Context::add([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'operation' => $operation,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
