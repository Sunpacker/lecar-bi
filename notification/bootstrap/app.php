<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use NotificationService\Console\Commands\ConsumeNotificationsCommand;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withCommands([
        ConsumeNotificationsCommand::class,
    ])
    ->withMiddleware(fn (Middleware $middleware) => $middleware)
    ->withExceptions(fn (Exceptions $exceptions) => $exceptions)
    ->create();
