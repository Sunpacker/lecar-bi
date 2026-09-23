<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            // Immutable contract fields
            $table->string('id')->primary(); // = event_id, stable across redelivery
            $table->string('event_type', 100)->notNullable();
            $table->unsignedSmallInteger('event_version')->notNullable();
            $table->string('producer', 100)->notNullable();
            $table->string('workspace_id')->notNullable();
            $table->string('aggregate_type', 100)->notNullable();
            $table->string('aggregate_id')->notNullable();
            $table->jsonb('envelope')->notNullable(); // full canonical JSON envelope
            $table->timestampTz('occurred_at')->notNullable();

            // Delivery tracking fields
            $table->string('status', 20)->default('pending')->notNullable();
            // statuses: pending | processing | published | failed
            $table->unsignedSmallInteger('attempt_count')->default(0)->notNullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('redis_message_id', 100)->nullable(); // e.g. "1234567890123-0"
            $table->text('last_error')->nullable(); // sanitized, no sensitive values

            // No FK on workspace_id or aggregate_id intentionally:
            // deleting business data must not destroy undelivered events.
        });

        // Index for the outbox publisher: claim pending messages ordered by next_attempt_at
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->index(['status', 'next_attempt_at'], 'outbox_status_next_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
