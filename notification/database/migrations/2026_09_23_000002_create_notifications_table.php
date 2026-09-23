<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_event_id')->unique();
            $table->string('workspace_id')->index();
            $table->string('alert_id')->index();
            $table->string('rule_id')->nullable()->index();
            $table->string('severity', 32)->index();
            $table->string('title');
            $table->text('body');
            $table->json('analytical_context');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
