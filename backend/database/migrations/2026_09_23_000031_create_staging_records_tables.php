<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_sales_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id', 64);
            $table->string('workspace_id', 64);
            $table->integer('row_number');
            $table->string('order_number', 64);
            $table->date('order_date');
            $table->string('channel_code', 32);
            $table->string('region_code', 32);
            $table->string('warehouse_code', 32);
            $table->string('sku', 64);
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->string('order_status', 32);
            $table->string('status', 32)->default('staged'); // 'staged', 'projected', 'failed'
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('batch_id')->references('id')->on('import_batches')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');

            $table->index(['batch_id', 'status'], 'idx_staging_sales_batch');
            $table->index(['workspace_id', 'order_number'], 'idx_staging_sales_order');
        });

        Schema::create('staging_inventory_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id', 64);
            $table->string('workspace_id', 64);
            $table->integer('row_number');
            $table->date('snapshot_date');
            $table->string('warehouse_code', 32);
            $table->string('sku', 64);
            $table->integer('quantity_on_hand');
            $table->integer('quantity_reserved');
            $table->integer('safety_stock')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('status', 32)->default('staged'); // 'staged', 'projected', 'failed'
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('batch_id')->references('id')->on('import_batches')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');

            $table->index(['batch_id', 'status'], 'idx_staging_inv_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_inventory_records');
        Schema::dropIfExists('staging_sales_records');
    }
};
