<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumed_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('stream_message_id')->unique();
            $table->string('event_type');
            $table->integer('event_version');
            $table->string('producer');
            $table->string('workspace_id')->index();
            $table->timestampTz('occurred_at');
            $table->timestampTz('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumed_events');
    }
};
