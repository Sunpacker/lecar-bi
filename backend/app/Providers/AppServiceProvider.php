<?php

namespace App\Providers;

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
    }

    public function boot(): void {}
}
