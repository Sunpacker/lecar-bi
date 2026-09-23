<?php

declare(strict_types=1);

namespace Database\Seeders\Performance;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class PerformanceDatasetSeeder extends Seeder
{
    /**
     * @param  (callable(string $phase, int $current, int $total): void)|null  $progressCallback
     */
    public function run(
        PerformanceDatasetProfile $profile = PerformanceDatasetProfile::Large,
        string $workspaceId = 'perf-ws-1',
        int $seed = 42,
        ?callable $progressCallback = null
    ): void {
        // 1. Safety guards: strictly restricted to local and testing environments
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Performance seeding is strictly restricted to local and testing environments.');
        }

        if (! str_starts_with($workspaceId, 'perf-')) {
            throw new InvalidArgumentException(
                "Workspace ID must start with 'perf-'. Refusing to operate on workspace '{$workspaceId}'."
            );
        }

        // 2. Ensure workspace exists in workspaces table so foreign keys pass
        DB::table('workspaces')->updateOrInsert(
            ['id' => $workspaceId],
            [
                'name' => "Performance Workspace {$workspaceId}",
                'slug' => "performance-{$workspaceId}",
                'created_at' => '2026-09-23 00:00:00',
                'updated_at' => '2026-09-23 00:00:00',
            ]
        );

        // 3. Isolated cleanup: only rows belonging to this perf workspace, child tables first
        $this->notify($progressCallback, 'cleanup', 0, 1);
        DB::table('fact_order_items')->where('workspace_id', $workspaceId)->delete();
        DB::table('fact_orders')->where('workspace_id', $workspaceId)->delete();
        DB::table('fact_inventory_daily')->where('workspace_id', $workspaceId)->delete();
        DB::table('fact_supplier_deliveries')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_products')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_warehouses')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_suppliers')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_sales_channels')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_regions')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_brands')->where('workspace_id', $workspaceId)->delete();
        DB::table('dim_categories')->where('workspace_id', $workspaceId)->delete();
        $this->notify($progressCallback, 'cleanup', 1, 1);

        $generator = new PerformanceDatasetGenerator($seed);

        // 4. Universal date dimension (2025-01-01 to 2026-12-31)
        $dates = $generator->generateDates('2025-01-01', '2026-12-31');
        foreach (array_chunk($dates, 500) as $dateChunk) {
            DB::table('dim_dates')->upsert($dateChunk, ['date']);
        }

        // 5. Seed Dimensions
        $this->notify($progressCallback, 'dimensions', 0, 6);
        $dims = $generator->generateWorkspaceDimensions($workspaceId);
        DB::table('dim_categories')->insert($dims['categories']);
        DB::table('dim_brands')->insert($dims['brands']);
        DB::table('dim_regions')->insert($dims['regions']);
        DB::table('dim_warehouses')->insert($dims['warehouses']);
        DB::table('dim_sales_channels')->insert($dims['sales_channels']);
        DB::table('dim_suppliers')->insert($dims['suppliers']);
        $this->notify($progressCallback, 'dimensions', 6, 6);

        // 6. Seed Products in streaming chunks
        $productCount = $profile->productsCount();
        $productsInserted = 0;
        foreach ($generator->generateProductChunks($workspaceId, $productCount, 500) as $prodChunk) {
            DB::table('dim_products')->insert($prodChunk);
            $productsInserted += count($prodChunk);
            $this->notify($progressCallback, 'products', $productsInserted, $productCount);
        }

        // 7. Seed Orders and Items in streaming chunks
        $targetOrders = $profile->ordersCount();
        $targetItems = $profile->itemsCount();
        $ordersInserted = 0;
        foreach ($generator->generateOrderChunks($workspaceId, $targetOrders, $targetItems, '2026-01-01', '2026-12-31', 500) as $orderChunk) {
            DB::table('fact_orders')->insert($orderChunk['orders']);
            DB::table('fact_order_items')->insert($orderChunk['items']);
            $ordersInserted += count($orderChunk['orders']);
            $this->notify($progressCallback, 'orders', $ordersInserted, $targetOrders);
        }

        // 8. Seed Inventory Snapshots in streaming chunks
        $targetSnapshots = $profile->inventorySnapshotsCount();
        $snapshotsInserted = 0;
        foreach ($generator->generateInventoryDailyChunks($workspaceId, $targetSnapshots, $productCount, 500) as $snapChunk) {
            DB::table('fact_inventory_daily')->insert($snapChunk);
            $snapshotsInserted += count($snapChunk);
            $this->notify($progressCallback, 'inventory', $snapshotsInserted, $targetSnapshots);
        }

        // 9. Seed Supplier Deliveries in streaming chunks
        $targetDeliveries = $profile->deliveriesCount();
        $deliveriesInserted = 0;
        foreach ($generator->generateSupplierDeliveryChunks($workspaceId, $targetDeliveries, $productCount, '2026-01-01', '2026-12-31', 500) as $delChunk) {
            DB::table('fact_supplier_deliveries')->insert($delChunk);
            $deliveriesInserted += count($delChunk);
            $this->notify($progressCallback, 'deliveries', $deliveriesInserted, $targetDeliveries);
        }
    }

    /**
     * @param  (callable(string $phase, int $current, int $total): void)|null  $callback
     */
    private function notify(?callable $callback, string $phase, int $current, int $total): void
    {
        if ($callback !== null) {
            $callback($phase, $current, $total);
        }
    }
}
