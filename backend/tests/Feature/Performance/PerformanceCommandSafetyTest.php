<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PerformanceCommandSafetyTest extends TestCase
{
    #[Test]
    public function seed_command_rejects_non_perf_workspace(): void
    {
        $this->artisan('performance:seed', [
            '--workspace' => 'ws-1',
            '--profile' => 'small',
        ])
            ->assertFailed()
            ->expectsOutputToContain("SAFETY ERROR: Target workspace ID must start with 'perf-'.");

        $this->artisan('performance:seed', [
            '--workspace' => 'ws-2',
            '--profile' => 'small',
        ])
            ->assertFailed()
            ->expectsOutputToContain("SAFETY ERROR: Target workspace ID must start with 'perf-'.");
    }

    #[Test]
    public function benchmark_command_rejects_non_perf_workspace(): void
    {
        $this->artisan('performance:benchmark', [
            '--workspace' => 'ws-1',
            '--profile' => 'small',
        ])
            ->assertFailed()
            ->expectsOutputToContain("SAFETY ERROR: Target workspace ID must start with 'perf-'.");

        $this->artisan('performance:benchmark', [
            '--workspace' => 'production-ws',
            '--profile' => 'small',
        ])
            ->assertFailed()
            ->expectsOutputToContain("SAFETY ERROR: Target workspace ID must start with 'perf-'.");
    }

    #[Test]
    public function seed_command_rejects_non_local_or_testing_environment(): void
    {
        $originalEnv = $this->app->environment();

        foreach (['production', 'staging'] as $env) {
            $this->app['env'] = $env;

            try {
                $this->artisan('performance:seed', [
                    '--workspace' => 'perf-ws-test',
                    '--profile' => 'small',
                ])
                    ->assertFailed()
                    ->expectsOutputToContain('CRITICAL SAFETY ERROR: performance:seed is strictly restricted to local and testing environments.');
            } finally {
                $this->app['env'] = $originalEnv;
            }
        }
    }

    #[Test]
    public function benchmark_command_rejects_non_local_or_testing_environment(): void
    {
        $originalEnv = $this->app->environment();

        foreach (['production', 'staging'] as $env) {
            $this->app['env'] = $env;

            try {
                $this->artisan('performance:benchmark', [
                    '--workspace' => 'perf-ws-test',
                    '--profile' => 'small',
                ])
                    ->assertFailed()
                    ->expectsOutputToContain('CRITICAL SAFETY ERROR: performance:benchmark is strictly restricted to local and testing environments.');
            } finally {
                $this->app['env'] = $originalEnv;
            }
        }
    }

    #[Test]
    public function commands_reject_unknown_profile_name(): void
    {
        $this->artisan('performance:seed', [
            '--workspace' => 'perf-ws-test',
            '--profile' => 'nonexistent',
        ])
            ->assertFailed()
            ->expectsOutputToContain("Unknown performance dataset profile 'nonexistent'.");

        $this->artisan('performance:benchmark', [
            '--workspace' => 'perf-ws-test',
            '--profile' => 'nonexistent',
        ])
            ->assertFailed()
            ->expectsOutputToContain("Unknown performance dataset profile 'nonexistent'.");
    }

    #[Test]
    public function benchmark_command_rejects_invalid_cache_state(): void
    {
        $this->artisan('performance:benchmark', [
            '--workspace' => 'perf-ws-test',
            '--profile' => 'small',
            '--cache-state' => 'invalid_state',
        ])
            ->assertFailed()
            ->expectsOutputToContain("Invalid cache-state 'invalid_state'. Must be disabled, cold, or warm.");
    }

    #[Test]
    public function benchmark_command_rejects_unknown_scenario_id(): void
    {
        $this->artisan('performance:benchmark', [
            '--workspace' => 'perf-ws-test',
            '--profile' => 'small',
            '--scenario' => 'NON-EXISTENT-99',
        ])
            ->assertFailed()
            ->expectsOutputToContain("Unknown scenario ID 'NON-EXISTENT-99'.");
    }
}
