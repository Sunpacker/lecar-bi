<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_saved_views', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('dashboard_id');
            $table->string('name', 100);
            $table->jsonb('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('dashboard_id')->references('id')->on('dashboards')->cascadeOnDelete();
            $table->index(['dashboard_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_saved_views');
    }
};
