<?php

namespace App\Providers;

use App\Shared\Infrastructure\Modules\BoundedContextRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BoundedContextRegistry::class, function (Application $app): BoundedContextRegistry {
            /** @var list<string> $contextNames */
            $contextNames = $app->make('config')->get('modules.contexts', []);

            return new BoundedContextRegistry($contextNames);
        });
    }
}
