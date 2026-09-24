<?php

use App\Console\Commands\BenchmarkAnalyticsCommand;
use App\Console\Commands\SeedPerformanceDatasetCommand;
use App\Modules\Alerting\Infrastructure\Commands\EvaluateAlertsConsoleCommand;
use App\Modules\KnowledgeBase\Infrastructure\Commands\IngestKnowledgeCommand;
use App\Modules\Support\Domain\SupportOperationException;
use App\Modules\Support\Infrastructure\Commands\DispatchQueuedGenerationsCommand;
use App\Modules\Support\Infrastructure\Commands\EvaluateSupportCommand;
use App\Modules\Support\Infrastructure\Commands\MaintainSupportGenerationsCommand;
use App\Modules\Support\Presentation\Middleware\AuthenticateSupportTransportMiddleware;
use App\Modules\Workspace\Domain\Exceptions\InsufficientWorkspaceCapabilityException;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Presentation\Middleware\RequireWorkspaceCapabilityMiddleware;
use App\Shared\Infrastructure\Commands\OutboxPublishCommand;
use App\Shared\Infrastructure\Commands\OutboxRetryCommand;
use App\Shared\Infrastructure\Jobs\PublishOutboxMessagesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withCommands([
        EvaluateAlertsConsoleCommand::class,
        OutboxPublishCommand::class,
        OutboxRetryCommand::class,
        SeedPerformanceDatasetCommand::class,
        BenchmarkAnalyticsCommand::class,
        IngestKnowledgeCommand::class,
        DispatchQueuedGenerationsCommand::class,
        EvaluateSupportCommand::class,
        MaintainSupportGenerationsCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        // Evaluate alert rules every 5 minutes
        $schedule->command('alerts:evaluate')->everyFiveMinutes()->withoutOverlapping();

        // Dispatch outbox publisher job every minute
        $schedule->job(PublishOutboxMessagesJob::class, 'outbox')->everyMinute()->withoutOverlapping();
        $schedule->command('support:dispatch-queued')->everyMinute()->withoutOverlapping();
        $schedule->command('support:maintain')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'support.transport' => AuthenticateSupportTransportMiddleware::class,
            'workspace.can' => RequireWorkspaceCapabilityMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (SupportOperationException $exception) {
            $response = response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode()], $exception->httpStatus());
            if ($exception->retryAfter() !== null) {
                $response->headers->set('Retry-After', (string) $exception->retryAfter());
            }

            return $response;
        });
        $exceptions->render(fn (InsufficientWorkspaceCapabilityException $exception) => response()->json(['message' => $exception->getMessage(), 'code' => 'INSUFFICIENT_CAPABILITY'], 403));
        $exceptions->render(fn (UnauthorizedWorkspaceAccessException $exception) => response()->json(['message' => $exception->getMessage(), 'code' => 'FORBIDDEN'], 403));
        $exceptions->render(fn (WorkspaceNotFoundException $exception) => response()->json(['message' => $exception->getMessage(), 'code' => 'NOT_FOUND'], 404));
    })
    ->create();
