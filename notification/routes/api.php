<?php

use Illuminate\Support\Facades\Route;
use NotificationService\Http\Controllers\HealthController;
use NotificationService\Http\Controllers\MetricsController;
use NotificationService\Http\Controllers\NotificationController;
use NotificationService\Http\Middleware\TrustedServiceMiddleware;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);
    Route::get('/health/worker', [HealthController::class, 'worker']);
    Route::get('/metrics', MetricsController::class);

    Route::middleware(TrustedServiceMiddleware::class)->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'list']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/notification-preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
    });
});
