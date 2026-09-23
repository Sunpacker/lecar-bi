<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id', 64);
            $table->string('rule_id', 64)->nullable();
            $table->string('rule_name', 255);
            $table->string('severity', 16);
            $table->string('status', 16);
            $table->string('dedup_fingerprint', 64);
            $table->string('product_id', 64)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('product_sku', 64)->nullable();
            $table->string('warehouse_id', 64)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->decimal('current_value', 12, 2)->nullable();
            $table->decimal('threshold_value', 12, 2)->nullable();
            $table->json('context_data')->nullable();
            $table->dateTimeTz('triggered_at');
            $table->dateTimeTz('acknowledged_at')->nullable();
            $table->string('acknowledged_by', 64)->nullable();
            $table->dateTimeTz('resolved_at')->nullable();
            $table->string('resolved_by', 64)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('rule_id')->references('id')->on('alert_rules')->nullOnDelete();

            $table->index(['workspace_id', 'status', 'severity']);
            $table->index(['workspace_id', 'dedup_fingerprint']);
            $table->index(['workspace_id', 'triggered_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_alerts_active_dedup ON alerts (workspace_id, dedup_fingerprint) WHERE status IN ('open', 'acknowledged')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
