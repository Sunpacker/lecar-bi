<?php

namespace Tests\Unit\DemoData;

use Database\Seeders\DemoDataSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemoDataSeederTest extends TestCase
{
    #[Test]
    public function database_seeder_calls_demo_data_seeder(): void
    {
        $seederFile = dirname(__DIR__, 3).'/database/seeders/DatabaseSeeder.php';
        $content = (string) file_get_contents($seederFile);

        self::assertStringContainsString('DemoDataSeeder::class', $content);
        self::assertTrue(class_exists(DemoDataSeeder::class));
    }

    #[Test]
    public function console_routes_register_demo_seed_command(): void
    {
        $consoleFile = dirname(__DIR__, 3).'/routes/console.php';
        $content = (string) file_get_contents($consoleFile);

        self::assertStringContainsString('demo:seed', $content);
    }
}
