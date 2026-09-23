<?php

namespace App\Providers;

use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories\EloquentDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories\EloquentSavedViewRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresInventoryAnalyticsReadModel;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\PostgresSalesAnalyticsReadModel;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories\EloquentWorkspaceRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryUserRepository;
            }

            return new EloquentUserRepository;
        });

        $this->app->singleton(WorkspaceRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryWorkspaceRepository;
            }

            return new EloquentWorkspaceRepository;
        });

        $this->app->singleton(SalesAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySalesAnalyticsReadModel;
            }

            return new PostgresSalesAnalyticsReadModel;
        });

        $this->app->singleton(InventoryAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryInventoryAnalyticsReadModel;
            }

            return new PostgresInventoryAnalyticsReadModel;
        });

        $this->app->singleton(DashboardRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryDashboardRepository;
            }

            return new EloquentDashboardRepository;
        });

        $this->app->singleton(SavedViewRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySavedViewRepository;
            }

            return new EloquentSavedViewRepository;
        });
    }

    public function boot(): void {}
}
