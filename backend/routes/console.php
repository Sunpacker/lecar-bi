<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;

Artisan::command('demo:seed {--seed=42 : Fixed integer seed for deterministic generation}', function () {
    /** @var int $seed */
    $seed = (int) $this->option('seed');
    $this->info("Seeding deterministic automotive demo dataset (seed={$seed})...");

    $seeder = new DemoDataSeeder;
    $seeder->run($seed);

    $this->info('Demo dataset seeded successfully.');
})->purpose('Seed reproducible automotive e-commerce analytics demo dataset');
