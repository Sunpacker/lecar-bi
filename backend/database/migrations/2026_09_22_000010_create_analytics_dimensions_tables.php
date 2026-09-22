<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_dates', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->smallInteger('year');
            $table->tinyInteger('quarter');
            $table->tinyInteger('month');
            $table->string('month_name', 20);
            $table->tinyInteger('week');
            $table->tinyInteger('day');
            $table->tinyInteger('day_of_week');
            $table->string('day_name', 20);
            $table->boolean('is_weekend');
            $table->string('season', 16);
        });

        Schema::create('dim_categories', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('slug', 128);
            $table->string('code', 64);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'slug']);
        });

        Schema::create('dim_brands', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('country', 64);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('dim_regions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_warehouses', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('region_id', 64);
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_sales_channels', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_suppliers', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->integer('lead_time_days');
            $table->decimal('reliability_score', 3, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('dim_products', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('category_id', 64);
            $table->string('brand_id', 64);
            $table->string('sku', 64);
            $table->string('name', 255);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('dim_categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('dim_brands')->cascadeOnDelete();
            $table->index(['workspace_id', 'sku']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_products');
        Schema::dropIfExists('dim_suppliers');
        Schema::dropIfExists('dim_sales_channels');
        Schema::dropIfExists('dim_warehouses');
        Schema::dropIfExists('dim_regions');
        Schema::dropIfExists('dim_brands');
        Schema::dropIfExists('dim_categories');
        Schema::dropIfExists('dim_dates');
    }
};
