<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use NotificationService\Console\Commands\ConsumeNotificationsCommand;
use NotificationService\Console\Commands\WorkerHealthCommand;
use NotificationService\Http\Middleware\TraceContextMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withCommands([
        ConsumeNotificationsCommand::class,
        WorkerHealthCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(TraceContextMiddleware::class);
    })
    ->withExceptions(fn (Exceptions $exceptions) => $exceptions)
    ->create();
