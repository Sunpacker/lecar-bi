<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsMiddleware
{
    public function __construct(
        private readonly PrometheusMetricsRegistry $metrics
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Avoid measuring the metrics endpoint itself
        if ($request->is('metrics') || $request->is('api/v1/metrics')) {
            return $next($request);
        }

        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $duration = max(0.0, microtime(true) - $startTime);
        $route = $request->route();
        $pattern = $route instanceof Route ? $route->uri() : 'unmatched';

        // Keep label cardinality low: remove query strings or irregular trailing slashes
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
