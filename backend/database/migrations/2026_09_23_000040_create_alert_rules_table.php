<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('rule_type', 32);
            $table->string('severity', 16);
            $table->string('metric', 32);
            $table->string('comparator', 16);
            $table->decimal('threshold_value', 12, 2);
            $table->string('warehouse_id', 64)->nullable();
            $table->string('category_id', 64)->nullable();
            $table->string('product_id', 64)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'is_enabled']);
            $table->index(['workspace_id', 'rule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
