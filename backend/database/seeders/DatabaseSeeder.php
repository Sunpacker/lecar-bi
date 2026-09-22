<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WorkspaceDatabaseSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
