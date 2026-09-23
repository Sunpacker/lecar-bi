<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Performance\PerformanceDatasetProfile;
use Database\Seeders\Performance\PerformanceDatasetSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SeedPerformanceDatasetCommand extends Command
{
    protected $signature = 'performance:seed
        {--profile=large : Dataset profile to generate (small or large)}
        {--workspace=perf-ws-1 : Isolated performance workspace ID (must start with perf-)}
        {--seed=42 : Deterministic PRNG integer seed}';

    protected $description = 'Seed reproducible isolated large dataset for analytics performance benchmarking';

    public function handle(PerformanceDatasetSeeder $seeder): int
    {
        // 1. Safety check: strictly restrict to local and testing environments
        if (! app()->environment(['local', 'testing'])) {
            $this->error('CRITICAL SAFETY ERROR: performance:seed is strictly restricted to local and testing environments.');

            return self::FAILURE;
        }

        // 2. Safety check: workspace must start with perf-
        /** @var string $workspaceId */
        $workspaceId = (string) $this->option('workspace');
        if (! str_starts_with($workspaceId, 'perf-')) {
            $this->error("SAFETY ERROR: Target workspace ID must start with 'perf-'. Refusing to touch workspace '{$workspaceId}'.");

            return self::FAILURE;
        }

        /** @var string $profileName */
        $profileName = (string) $this->option('profile');
        try {
            $profile = PerformanceDatasetProfile::fromName($profileName);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var int $seed */
        $seed = (int) $this->option('seed');

        $this->info('=== Starting Performance Dataset Seeding ===');
        $this->line("Workspace : <comment>{$workspaceId}</comment>");
        $this->line("Profile   : <comment>{$profile->value}</comment>");
        $this->line("Seed      : <comment>{$seed}</comment>");
        $this->line("Targets   : orders={$profile->ordersCount()}, items={$profile->itemsCount()}, inventory={$profile->inventorySnapshotsCount()}, deliveries={$profile->deliveriesCount()}, products={$profile->productsCount()}");

        $startTime = hrtime(true);

        try {
            $seeder->run(
                profile: $profile,
                workspaceId: $workspaceId,
                seed: $seed,
                progressCallback: function (string $phase, int $current, int $total): void {
                    if ($current === $total || $current % 10000 === 0 || $current === 1) {
                        $this->line(sprintf('  [%s] %d / %d', $phase, $current, $total));
                    }
                }
            );
        } catch (\Throwable $e) {
            $this->error("Seeding failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $elapsedSeconds = round((hrtime(true) - $startTime) / 1_000_000_000.0, 2);

        // Verification query
        $ordersCount = DB::table('fact_orders')->where('workspace_id', $workspaceId)->count();
        $itemsCount = DB::table('fact_order_items')->where('workspace_id', $workspaceId)->count();
        $inventoryCount = DB::table('fact_inventory_daily')->where('workspace_id', $workspaceId)->count();
        $deliveriesCount = DB::table('fact_supplier_deliveries')->where('workspace_id', $workspaceId)->count();
        $productsCount = DB::table('dim_products')->where('workspace_id', $workspaceId)->count();

        $this->newLine();
        $this->info("=== Performance Dataset Seeded Successfully in {$elapsedSeconds}s ===");
        $this->table(
            ['Entity / Table', 'Actual Row Count', 'Target Row Count', 'Status'],
            [
                ['fact_orders', number_format($ordersCount), number_format($profile->ordersCount()), $ordersCount === $profile->ordersCount() ? 'OK' : 'MISMATCH'],
                ['fact_order_items', number_format($itemsCount), number_format($profile->itemsCount()), $itemsCount === $profile->itemsCount() ? 'OK' : 'MISMATCH'],
                ['fact_inventory_daily', number_format($inventoryCount), number_format($profile->inventorySnapshotsCount()), $inventoryCount === $profile->inventorySnapshotsCount() ? 'OK' : 'MISMATCH'],
                ['fact_supplier_deliveries', number_format($deliveriesCount), number_format($profile->deliveriesCount()), $deliveriesCount === $profile->deliveriesCount() ? 'OK' : 'MISMATCH'],
                ['dim_products', number_format($productsCount), number_format($profile->productsCount()), $productsCount === $profile->productsCount() ? 'OK' : 'MISMATCH'],
            ]
        );

        return self::SUCCESS;
    }
}
