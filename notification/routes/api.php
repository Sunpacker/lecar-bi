<?php

use Illuminate\Support\Facades\Route;
use NotificationService\Http\Controllers\HealthController;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);
    Route::get('/health/worker', [HealthController::class, 'worker']);
});
