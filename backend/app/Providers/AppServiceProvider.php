<?php

namespace App\Providers;

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
    }

    public function boot(): void {}
}
