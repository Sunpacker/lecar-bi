<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('dashboard_id');
            $table->string('title', 100);
            $table->string('type', 32);
            $table->jsonb('query_config');
            $table->integer('grid_x');
            $table->integer('grid_y');
            $table->integer('grid_w');
            $table->integer('grid_h');
            $table->jsonb('options')->nullable();
            $table->timestamps();

            $table->foreign('dashboard_id')->references('id')->on('dashboards')->cascadeOnDelete();
            $table->index('dashboard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
