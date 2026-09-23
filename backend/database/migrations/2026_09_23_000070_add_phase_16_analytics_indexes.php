<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // 1. Covering index for fact_order_items: workspace summary & order count (SALES-01, DASH-01, DASH-02)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_foi_ws_order_covering
                ON fact_order_items (workspace_id, order_id)
                INCLUDE (total_price, gross_profit)
            ');

            // 2. Covering index for fact_order_items: date range & trend aggregation (SALES-01, SALES-02, DASH-01)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_foi_ws_date_order_covering
                ON fact_order_items (workspace_id, order_date, order_id)
                INCLUDE (total_price, gross_profit, category_id, region_id)
            ');

            // 3. Covering index for category breakdown aggregation (SALES-01, DASH-01)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_foi_ws_cat_order_covering
                ON fact_order_items (workspace_id, category_id, order_id)
                INCLUDE (total_price)
            ');

            // 4. Covering index for regional breakdown aggregation (SALES-01, DASH-01)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_foi_ws_reg_order_covering
                ON fact_order_items (workspace_id, region_id, order_id)
                INCLUDE (total_price)
            ');

            // 5. Sorted index for inventory daily items pagination by available stock (INV-04)
            DB::statement('
                CREATE INDEX IF NOT EXISTS idx_inv_ws_date_avail
                ON fact_inventory_daily (workspace_id, snapshot_date, quantity_available)
            ');
        } else {
            // Fallback for sqlite / testing environments
            Schema::table('fact_order_items', function ($table) {
                $table->index(['workspace_id', 'order_id'], 'idx_foi_ws_order_fallback');
                $table->index(['workspace_id', 'order_date', 'order_id'], 'idx_foi_ws_date_fallback');
                $table->index(['workspace_id', 'category_id', 'order_id'], 'idx_foi_ws_cat_fallback');
                $table->index(['workspace_id', 'region_id', 'order_id'], 'idx_foi_ws_reg_fallback');
            });

            Schema::table('fact_inventory_daily', function ($table) {
                $table->index(['workspace_id', 'snapshot_date', 'quantity_available'], 'idx_inv_ws_date_avail_fallback');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_foi_ws_order_covering');
            DB::statement('DROP INDEX IF EXISTS idx_foi_ws_date_order_covering');
            DB::statement('DROP INDEX IF EXISTS idx_foi_ws_cat_order_covering');
            DB::statement('DROP INDEX IF EXISTS idx_foi_ws_reg_order_covering');
            DB::statement('DROP INDEX IF EXISTS idx_inv_ws_date_avail');
        } else {
            Schema::table('fact_order_items', function ($table) {
                $table->dropIndex('idx_foi_ws_order_fallback');
                $table->dropIndex('idx_foi_ws_date_fallback');
                $table->dropIndex('idx_foi_ws_cat_fallback');
                $table->dropIndex('idx_foi_ws_reg_fallback');
            });

            Schema::table('fact_inventory_daily', function ($table) {
                $table->dropIndex('idx_inv_ws_date_avail_fallback');
            });
        }
    }
};
