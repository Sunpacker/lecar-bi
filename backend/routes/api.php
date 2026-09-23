<?php

use App\Modules\Dashboard\Presentation\Controllers\DashboardController;
use App\Modules\Dashboard\Presentation\Controllers\DashboardSavedViewController;
use App\Modules\DataIngestion\Presentation\Controllers\ImportBatchController;
use App\Modules\InventoryAnalytics\Presentation\Controllers\InventoryAnalyticsController;
use App\Modules\SalesAnalytics\Presentation\Controllers\SalesAnalyticsController;
use App\Modules\Workspace\Presentation\Controllers\AuthController;
use App\Modules\Workspace\Presentation\Controllers\CurrentWorkspaceController;
use App\Modules\Workspace\Presentation\Controllers\ProfileController;
use App\Modules\Workspace\Presentation\Controllers\WorkspaceController;
use App\Modules\Workspace\Presentation\Middleware\AuthenticateUserIdMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'analytics', 'version' => 'v1']));
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(AuthenticateUserIdMiddleware::class)->group(function () {
        Route::get('/me', [ProfileController::class, 'me']);
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::get('/workspaces/current', [CurrentWorkspaceController::class, 'show']);
        Route::get('/workspaces/{id}', [WorkspaceController::class, 'show']);

        Route::get('/imports', [ImportBatchController::class, 'index']);
        Route::post('/imports', [ImportBatchController::class, 'store']);
        Route::get('/imports/{id}', [ImportBatchController::class, 'show']);
        Route::get('/imports/{id}/failures', [ImportBatchController::class, 'failures']);
        Route::post('/imports/{id}/retry', [ImportBatchController::class, 'retry']);

        Route::get('/dashboards', [DashboardController::class, 'index']);
        Route::post('/dashboards', [DashboardController::class, 'store']);
        Route::get('/dashboards/{id}', [DashboardController::class, 'show']);
        Route::put('/dashboards/{id}', [DashboardController::class, 'update']);
        Route::delete('/dashboards/{id}', [DashboardController::class, 'destroy']);

        Route::get('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'index']);
        Route::post('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'store']);
        Route::get('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'show']);
        Route::put('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'update']);
        Route::delete('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'destroy']);

        Route::get('/analytics/sales/overview', [SalesAnalyticsController::class, 'overview']);
        Route::get('/analytics/sales/filters', [SalesAnalyticsController::class, 'filters']);
        Route::get('/analytics/sales/records', [SalesAnalyticsController::class, 'records']);

        Route::get('/analytics/inventory/summary', [InventoryAnalyticsController::class, 'summary']);
        Route::get('/analytics/inventory/items', [InventoryAnalyticsController::class, 'items']);
        Route::get('/analytics/inventory/filters', [InventoryAnalyticsController::class, 'filters']);
        Route::get('/analytics/inventory/abc-xyz/summary', [InventoryAnalyticsController::class, 'abcXyzSummary']);
        Route::get('/analytics/inventory/abc-xyz/items', [InventoryAnalyticsController::class, 'abcXyzItems']);
    });
});
