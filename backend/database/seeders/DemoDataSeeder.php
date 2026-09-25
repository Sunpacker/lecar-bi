<?php

namespace Database\Seeders;

use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use Database\Seeders\Demo\DemoDatasetGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoDataSeeder extends Seeder
{
    public function run(int $seed = 42): void
    {
        $generator = new DemoDatasetGenerator($seed);

        // 1. Universal Date Dimension (2025-01-01 to 2026-12-31)
        $dates = $generator->generateDates('2025-01-01', '2026-12-31');
        foreach (array_chunk($dates, 500) as $chunk) {
            DB::table('dim_dates')->upsert($chunk, ['date']);
        }

        // 2. Multi-tenant datasets for both demo workspaces
        $workspaces = ['ws-1', 'ws-2'];

        foreach ($workspaces as $workspaceId) {
            // Clear existing demo facts and dimensions for this workspace (idempotent re-seed)
            DB::table('fact_order_items')->where('workspace_id', $workspaceId)->delete();
            DB::table('fact_orders')->where('workspace_id', $workspaceId)->delete();
            DB::table('fact_inventory_daily')->where('workspace_id', $workspaceId)->delete();
            DB::table('fact_supplier_deliveries')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_products')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_warehouses')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_regions')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_categories')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_brands')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_sales_channels')->where('workspace_id', $workspaceId)->delete();
            DB::table('dim_suppliers')->where('workspace_id', $workspaceId)->delete();

            $dims = $generator->generateWorkspaceDimensions($workspaceId);
            DB::table('dim_categories')->insert($dims['categories']);
            DB::table('dim_brands')->insert($dims['brands']);
            DB::table('dim_regions')->insert($dims['regions']);
            DB::table('dim_warehouses')->insert($dims['warehouses']);
            DB::table('dim_sales_channels')->insert($dims['sales_channels']);
            DB::table('dim_suppliers')->insert($dims['suppliers']);
            DB::table('dim_products')->insert($dims['products']);

            // Generate 1 full year of sales facts for 2025
            $sales = $generator->generateOrdersAndItems($workspaceId, '2025-01-01', '2025-12-31');
            foreach (array_chunk($sales['orders'], 500) as $chunk) {
                DB::table('fact_orders')->insert($chunk);
            }
            foreach (array_chunk($sales['items'], 500) as $chunk) {
                DB::table('fact_order_items')->insert($chunk);
            }

            // Generate daily inventory snapshots (Q4 peak period to capture seasonal extremes)
            $inventory = $generator->generateInventoryDaily($workspaceId, '2025-10-01', '2025-12-31', $sales['items']);
            foreach (array_chunk($inventory, 500) as $chunk) {
                DB::table('fact_inventory_daily')->insert($chunk);
            }

            // Generate supplier deliveries
            $deliveries = $generator->generateSupplierDeliveries($workspaceId, '2025-01-01', '2025-12-31');
            foreach (array_chunk($deliveries, 500) as $chunk) {
                DB::table('fact_supplier_deliveries')->insert($chunk);
            }

            // Bump dataset versions upon fresh seed
            $versionStore = app(AnalyticsDatasetVersionStore::class);
            $versionStore->bumpVersion($workspaceId, 'sales');
            $versionStore->bumpVersion($workspaceId, 'inventory');
            $versionStore->bumpVersion($workspaceId, 'suppliers');
        }
    }
}
