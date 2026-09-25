<?php

use App\Console\Commands\BenchmarkAnalyticsCommand;
use App\Console\Commands\SeedPerformanceDatasetCommand;
use App\Modules\Alerting\Infrastructure\Commands\EvaluateAlertsConsoleCommand;
use App\Modules\Workspace\Presentation\Middleware\RequireWorkspaceCapabilityMiddleware;
use App\Shared\Infrastructure\Commands\OutboxHealthCommand;
use App\Shared\Infrastructure\Commands\OutboxPublishCommand;
use App\Shared\Infrastructure\Commands\OutboxRetryCommand;
use App\Shared\Infrastructure\Http\Middleware\PrometheusMetricsMiddleware;
use App\Shared\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use App\Shared\Infrastructure\Http\Middleware\TraceContextMiddleware;
use App\Shared\Infrastructure\Jobs\PublishOutboxMessagesJob;
use App\Shared\Presentation\Controllers\MetricsController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->get('/metrics', MetricsController::class);
        }
    )
    ->withCommands([
        EvaluateAlertsConsoleCommand::class,
        OutboxPublishCommand::class,
        OutboxRetryCommand::class,
        OutboxHealthCommand::class,
        SeedPerformanceDatasetCommand::class,
        BenchmarkAnalyticsCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        // Evaluate alert rules every 5 minutes
        $schedule->command('alerts:evaluate')->everyFiveMinutes()->withoutOverlapping();

        // Dispatch outbox publisher job every minute
        $schedule->job(PublishOutboxMessagesJob::class, 'outbox')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(TraceContextMiddleware::class);
        $middleware->append(PrometheusMetricsMiddleware::class);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->alias([
            'workspace.can' => RequireWorkspaceCapabilityMiddleware::class,
        ]);
    })
    ->withExceptions(fn (Exceptions $exceptions) => $exceptions)
    ->create();
