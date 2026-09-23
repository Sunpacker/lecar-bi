<?php

use App\Modules\Alerting\Presentation\Controllers\AlertController;
use App\Modules\Alerting\Presentation\Controllers\AlertRuleController;
use App\Modules\Dashboard\Presentation\Controllers\DashboardController;
use App\Modules\Dashboard\Presentation\Controllers\DashboardSavedViewController;
use App\Modules\DataIngestion\Presentation\Controllers\ImportBatchController;
use App\Modules\InventoryAnalytics\Presentation\Controllers\InventoryAnalyticsController;
use App\Modules\SalesAnalytics\Presentation\Controllers\SalesAnalyticsController;
use App\Modules\SupplierAnalytics\Presentation\Controllers\SupplierAnalyticsController;
use App\Modules\Workspace\Presentation\Controllers\AuthController;
use App\Modules\Workspace\Presentation\Controllers\CurrentWorkspaceController;
use App\Modules\Workspace\Presentation\Controllers\ProfileController;
use App\Modules\Workspace\Presentation\Controllers\WorkspaceController;
use App\Modules\Workspace\Presentation\Controllers\WorkspaceMemberController;
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

        // Workspace Members
        Route::middleware('workspace.can:workspace.members.manage')->group(function () {
            Route::get('/workspaces/{workspaceId}/members', [WorkspaceMemberController::class, 'index']);
            Route::patch('/workspaces/{workspaceId}/members/{userId}/role', [WorkspaceMemberController::class, 'updateRole']);
        });

        // Analytics (view)
        Route::middleware('workspace.can:analytics.view')->group(function () {
            Route::get('/analytics/sales/overview', [SalesAnalyticsController::class, 'overview']);
            Route::get('/analytics/sales/filters', [SalesAnalyticsController::class, 'filters']);
            Route::get('/analytics/sales/records', [SalesAnalyticsController::class, 'records']);

            Route::get('/analytics/inventory/summary', [InventoryAnalyticsController::class, 'summary']);
            Route::get('/analytics/inventory/items', [InventoryAnalyticsController::class, 'items']);
            Route::get('/analytics/inventory/filters', [InventoryAnalyticsController::class, 'filters']);
            Route::get('/analytics/inventory/abc-xyz/summary', [InventoryAnalyticsController::class, 'abcXyzSummary']);
            Route::get('/analytics/inventory/abc-xyz/items', [InventoryAnalyticsController::class, 'abcXyzItems']);

            Route::get('/analytics/suppliers/overview', [SupplierAnalyticsController::class, 'overview']);
            Route::get('/analytics/suppliers/filters', [SupplierAnalyticsController::class, 'filters']);
            Route::get('/analytics/suppliers/performance', [SupplierAnalyticsController::class, 'performance']);
            Route::get('/analytics/suppliers/deliveries', [SupplierAnalyticsController::class, 'deliveries']);
        });

        // Dashboards & Views (view)
        Route::middleware('workspace.can:dashboards.view')->group(function () {
            Route::get('/dashboards', [DashboardController::class, 'index']);
            Route::get('/dashboards/{id}', [DashboardController::class, 'show']);
            Route::get('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'index']);
            Route::get('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'show']);
        });

        // Dashboards & Views (manage)
        Route::middleware('workspace.can:dashboards.manage')->group(function () {
            Route::post('/dashboards', [DashboardController::class, 'store']);
            Route::put('/dashboards/{id}', [DashboardController::class, 'update']);
            Route::delete('/dashboards/{id}', [DashboardController::class, 'destroy']);
            Route::post('/dashboards/{dashboardId}/views', [DashboardSavedViewController::class, 'store']);
            Route::put('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'update']);
            Route::delete('/dashboards/{dashboardId}/views/{viewId}', [DashboardSavedViewController::class, 'destroy']);
        });

        // Data Ingestion (view)
        Route::middleware('workspace.can:imports.view')->group(function () {
            Route::get('/imports', [ImportBatchController::class, 'index']);
            Route::get('/imports/{id}', [ImportBatchController::class, 'show']);
            Route::get('/imports/{id}/failures', [ImportBatchController::class, 'failures']);
        });

        // Data Ingestion (manage)
        Route::middleware('workspace.can:imports.manage')->group(function () {
            Route::post('/imports', [ImportBatchController::class, 'store']);
            Route::post('/imports/{id}/retry', [ImportBatchController::class, 'retry']);
        });

        // Alerting (view)
        Route::middleware('workspace.can:alerts.view')->group(function () {
            Route::get('/alert-rules', [AlertRuleController::class, 'index']);
            Route::get('/alert-rules/{id}', [AlertRuleController::class, 'show']);
            Route::get('/alerts', [AlertController::class, 'index']);
            Route::get('/alerts/summary', [AlertController::class, 'summary']);
            Route::get('/alerts/{id}', [AlertController::class, 'show']);
        });

        // Alerting (manage)
        Route::middleware('workspace.can:alerts.manage')->group(function () {
            Route::post('/alert-rules', [AlertRuleController::class, 'store']);
            Route::put('/alert-rules/{id}', [AlertRuleController::class, 'update']);
            Route::delete('/alert-rules/{id}', [AlertRuleController::class, 'destroy']);
            Route::post('/alert-rules/{id}/toggle', [AlertRuleController::class, 'toggle']);
            Route::post('/alert-rules/evaluate', [AlertRuleController::class, 'evaluate']);
            Route::post('/alerts/{id}/acknowledge', [AlertController::class, 'acknowledge']);
            Route::post('/alerts/{id}/resolve', [AlertController::class, 'resolve']);
        });
    });
});
