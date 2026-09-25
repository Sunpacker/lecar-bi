<?php

declare(strict_types=1);

namespace NotificationService\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use NotificationService\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsMiddleware
{
    public function __construct(
        private readonly PrometheusMetricsRegistry $metrics
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('metrics') || $request->is('api/v1/metrics')) {
            return $next($request);
        }

        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $duration = max(0.0, microtime(true) - $startTime);
        $route = $request->route();
        $pattern = $route instanceof Route ? $route->uri() : 'unmatched';

        $pattern = trim($pattern, '/');
        if ($pattern === '') {
            $pattern = 'root';
        }

        $method = $request->method();
        $statusCode = (string) $response->getStatusCode();

        $this->metrics->incrementCounter('http_requests_total', [
            'method' => $method,
            'route' => $pattern,
            'status_code' => $statusCode,
        ]);

        $this->metrics->observeHistogram('http_request_duration_seconds', $duration, [
            'method' => $method,
            'route' => $pattern,
        ]);

        return $response;
    }
}
