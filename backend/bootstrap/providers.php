<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    SanctumServiceProvider::class,
];
