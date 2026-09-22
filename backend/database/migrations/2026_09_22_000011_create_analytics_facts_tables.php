<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_orders', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('order_number', 64);
            $table->string('channel_id', 64);
            $table->string('region_id', 64);
            $table->string('status', 32);
            $table->dateTimeTz('ordered_at');
            $table->date('order_date');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('dim_sales_channels')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'channel_id']);
            $table->index(['workspace_id', 'region_id']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('fact_order_items', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('order_id', 64);
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->string('category_id', 64);
            $table->string('brand_id', 64);
            $table->string('region_id', 64);
            $table->string('channel_id', 64);
            $table->date('order_date');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('gross_profit', 12, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('fact_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('dim_categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('dim_brands')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('dim_sales_channels')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'product_id']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'warehouse_id']);
        });

        Schema::create('fact_inventory_daily', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->date('snapshot_date');
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->integer('quantity_on_hand');
            $table->integer('quantity_reserved');
            $table->integer('quantity_available');
            $table->integer('safety_stock');
            $table->integer('reorder_point');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('inventory_value', 14, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->index(['workspace_id', 'snapshot_date']);
            $table->unique(['workspace_id', 'snapshot_date', 'product_id', 'warehouse_id'], 'uq_inv_ws_date_prod_wh');
        });

        Schema::create('fact_supplier_deliveries', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('supplier_id', 64);
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->date('order_date');
            $table->date('expected_delivery_date');
            $table->date('actual_delivery_date')->nullable();
            $table->integer('ordered_quantity');
            $table->integer('received_quantity');
            $table->decimal('unit_purchase_cost', 12, 2);
            $table->decimal('total_purchase_cost', 12, 2);
            $table->string('delivery_status', 32);
            $table->integer('lead_time_days');
            $table->integer('delay_days')->default(0);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('dim_suppliers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'supplier_id']);
            $table->index(['workspace_id', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_supplier_deliveries');
        Schema::dropIfExists('fact_inventory_daily');
        Schema::dropIfExists('fact_order_items');
        Schema::dropIfExists('fact_orders');
    }
};
