<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id', 64);
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->date('as_of_date');
            $table->integer('horizon_days');
            $table->string('model_method', 64);
            $table->string('model_version', 32);
            $table->string('status', 32);
            $table->bigInteger('sales_dataset_version');
            $table->bigInteger('inventory_dataset_version');
            $table->bigInteger('supplier_dataset_version');
            $table->timestampTz('generated_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('dim_products')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->onDelete('cascade');

            $table->unique([
                'workspace_id', 'product_id', 'warehouse_id', 'as_of_date',
                'horizon_days', 'model_method', 'model_version',
                'sales_dataset_version', 'inventory_dataset_version', 'supplier_dataset_version',
            ], 'uq_forecast_dedup');

            $table->index(['workspace_id', 'product_id', 'warehouse_id', 'as_of_date'], 'idx_forecasts_wpwd');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('forecast_points', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('forecast_id', 64);
            $table->date('forecast_date');
            $table->decimal('point_estimate', 14, 4);
            $table->decimal('lower_bound', 14, 4)->nullable();
            $table->decimal('upper_bound', 14, 4)->nullable();
            $table->decimal('interval_level', 3, 2)->nullable();
            $table->decimal('actual_value', 14, 4)->nullable();
            $table->boolean('is_stockout_day')->default(false);

            $table->foreign('forecast_id')->references('id')->on('forecasts')->onDelete('cascade');
            $table->index(['forecast_id', 'forecast_date']);
        });

        Schema::create('forecast_quality_metrics', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('forecast_id', 64);
            $table->string('metric_name', 64);
            $table->decimal('metric_value', 14, 6);
            $table->integer('horizon_days');
            $table->string('segment', 64)->nullable();
            $table->integer('evaluation_windows');

            $table->foreign('forecast_id')->references('id')->on('forecasts')->onDelete('cascade');
            $table->index('forecast_id');
        });

        Schema::create('forecast_stock_risk', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('forecast_id', 64);
            $table->integer('current_quantity_available');
            $table->integer('current_safety_stock');
            $table->integer('current_reorder_point');
            $table->date('estimated_depletion_date')->nullable();
            $table->date('estimated_reorder_threshold_date')->nullable();
            $table->date('estimated_order_placement_date')->nullable();
            $table->integer('median_lead_time_days')->nullable();
            $table->string('lead_time_source', 64)->nullable();
            $table->text('assumptions');

            $table->foreign('forecast_id')->references('id')->on('forecasts')->onDelete('cascade');
            $table->unique('forecast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_stock_risk');
        Schema::dropIfExists('forecast_quality_metrics');
        Schema::dropIfExists('forecast_points');
        Schema::dropIfExists('forecasts');
    }
};
