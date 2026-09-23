<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence\Queries;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait InteractsWithInventorySnapshot
{
    private function resolveLatestSnapshotDate(string $workspaceId): ?string
    {
        /** @var string|null $date */
        $date = DB::table('fact_inventory_daily')
            ->where('workspace_id', $workspaceId)
            ->max('snapshot_date');

        return $date;
    }

    /**
     * Builds base subquery calculating velocity and health classifications for the snapshot.
     */
    private function baseSnapshotQuery(string $workspaceId, string $asOfDate, ?string $warehouseId = null): Builder
    {
        $thirtyDaysAgo = Carbon::parse($asOfDate)->subDays(30)->toDateString();

        // Subquery calculating sales velocity per product per warehouse over 30 days
        $velocitySub = DB::table('fact_order_items')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('order_date', [$thirtyDaysAgo, $asOfDate])
            ->select('product_id', 'warehouse_id')
            ->selectRaw('ROUND(COALESCE(SUM(quantity), 0) / 30.0, 2) as sales_velocity')
            ->groupBy('product_id', 'warehouse_id');

        $query = DB::table('fact_inventory_daily as f')
            ->leftJoinSub($velocitySub, 'v', function ($join) {
                $join->on('v.product_id', '=', 'f.product_id')
                    ->on('v.warehouse_id', '=', 'f.warehouse_id');
            })
            ->join('dim_products as p', function ($join) {
                $join->on('p.id', '=', 'f.product_id')
                    ->on('p.workspace_id', '=', 'f.workspace_id');
            })
            ->join('dim_categories as c', function ($join) {
                $join->on('c.id', '=', 'p.category_id')
                    ->on('c.workspace_id', '=', 'f.workspace_id');
            })
            ->join('dim_warehouses as w', function ($join) {
                $join->on('w.id', '=', 'f.warehouse_id')
                    ->on('w.workspace_id', '=', 'f.workspace_id');
            })
            ->where('f.workspace_id', $workspaceId)
            ->where('f.snapshot_date', $asOfDate);

        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('f.warehouse_id', $warehouseId);
        }

        // Calculate derived days of stock and health status dynamically
        $query->selectRaw('
            f.id,
            f.workspace_id,
            f.product_id,
            f.warehouse_id,
            f.quantity_on_hand,
            f.quantity_reserved,
            f.quantity_available,
            f.unit_cost,
            f.inventory_value,
            f.safety_stock,
            f.reorder_point,
            COALESCE(v.sales_velocity, 0.0) as sales_velocity,
            CASE
                WHEN f.quantity_available <= 0 THEN 0.0
                WHEN COALESCE(v.sales_velocity, 0.0) <= 0 THEN NULL
                ELSE ROUND(f.quantity_available / v.sales_velocity, 1)
            END as days_of_stock,
            CASE
                WHEN f.quantity_available <= 0 THEN \'out_of_stock\'
                WHEN (COALESCE(v.sales_velocity, 0.0) > 0 AND (f.quantity_available / v.sales_velocity) <= 7.0) OR f.quantity_available <= f.safety_stock THEN \'critical\'
                WHEN (COALESCE(v.sales_velocity, 0.0) > 0 AND (f.quantity_available / v.sales_velocity) > 60.0) OR (COALESCE(v.sales_velocity, 0.0) <= 0 AND f.quantity_available > f.safety_stock * 3) THEN \'overstock\'
                ELSE \'optimal\'
            END as health_status
        ');

        return DB::query()->fromSub($query, 'inv')
            ->join('dim_products as p', 'p.id', '=', 'inv.product_id')
            ->join('dim_categories as c', 'c.id', '=', 'p.category_id')
            ->join('dim_warehouses as w', 'w.id', '=', 'inv.warehouse_id');
    }
}
