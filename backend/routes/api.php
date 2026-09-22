<?php

use App\Modules\SalesAnalytics\Presentation\Controllers\SalesAnalyticsController;
use App\Modules\Workspace\Presentation\Controllers\CurrentWorkspaceController;
use App\Modules\Workspace\Presentation\Controllers\ProfileController;
use App\Modules\Workspace\Presentation\Controllers\WorkspaceController;
use App\Modules\Workspace\Presentation\Middleware\AuthenticateUserIdMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'analytics', 'version' => 'v1']));

    Route::middleware(AuthenticateUserIdMiddleware::class)->group(function () {
        Route::get('/me', [ProfileController::class, 'me']);
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::get('/workspaces/current', [CurrentWorkspaceController::class, 'show']);
        Route::get('/workspaces/{id}', [WorkspaceController::class, 'show']);

        Route::get('/analytics/sales/overview', [SalesAnalyticsController::class, 'overview']);
        Route::get('/analytics/sales/filters', [SalesAnalyticsController::class, 'filters']);
    });
});
