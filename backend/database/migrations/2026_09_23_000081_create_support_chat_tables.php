<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_id');
            $table->string('owner_user_id');
            $table->string('title', 120);
            $table->unsignedBigInteger('next_position')->default(1);
            $table->timestampTz('last_activity_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->index(['workspace_id', 'owner_user_id', 'last_activity_at']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('role', 16);
            $table->text('content')->default('');
            $table->unsignedBigInteger('position');
            $table->uuid('client_message_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestampsTz();
            $table->foreign('conversation_id')->references('id')->on('support_conversations');
            $table->unique(['conversation_id', 'position']);
            $table->unique(['conversation_id', 'client_message_id']);
        });

        Schema::create('support_generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('user_message_id');
            $table->uuid('assistant_message_id');
            $table->unsignedInteger('attempt');
            $table->string('status', 16);
            $table->string('outcome', 24)->nullable();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->text('text')->default('');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version')->default('support-v1');
            $table->uuid('corpus_build_id')->nullable();
            $table->jsonb('retrieval_trace')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->string('usage_status', 16)->default('unknown');
            $table->string('error_code')->nullable();
            $table->boolean('retryable')->default(false);
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('conversation_id')->references('id')->on('support_conversations');
            $table->foreign('user_message_id')->references('id')->on('support_messages');
            $table->foreign('assistant_message_id')->references('id')->on('support_messages');
            $table->unique(['conversation_id', 'attempt', 'user_message_id']);
            $table->index(['status', 'queued_at']);
            $table->index(['status', 'lease_expires_at']);
        });

        Schema::create('support_citations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('generation_id');
            $table->uuid('message_id');
            $table->string('document_id');
            $table->string('document_revision');
            $table->uuid('chunk_id');
            $table->string('title');
            $table->string('url');
            $table->string('anchor')->nullable();
            $table->unsignedInteger('ordinal');
            $table->timestampsTz();
            $table->foreign('generation_id')->references('id')->on('support_generations');
            $table->foreign('message_id')->references('id')->on('support_messages');
            $table->unique(['generation_id', 'chunk_id']);
        });

        Schema::create('support_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('user_id');
            $table->string('rating', 20);
            $table->timestampsTz();
            $table->foreign('message_id')->references('id')->on('support_messages');
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('support_idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('workspace_id');
            $table->string('user_id');
            $table->string('operation');
            $table->string('resource_id')->default('root');
            $table->string('idempotency_key', 128);
            $table->string('payload_hash', 64);
            $table->jsonb('result');
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(['workspace_id', 'user_id', 'operation', 'resource_id', 'idempotency_key'], 'support_idempotency_scope_unique');
            $table->index('expires_at');
        });

        Schema::create('support_retry_requests', function (Blueprint $table): void {
            $table->uuid('retry_request_id')->primary();
            $table->uuid('generation_id');
            $table->string('payload_hash', 64);
            $table->uuid('result_generation_id');
            $table->timestampsTz();
            $table->unique(['generation_id', 'retry_request_id']);
        });

        Schema::create('support_budget_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('generation_id')->unique();
            $table->string('workspace_id');
            $table->date('budget_date');
            $table->unsignedInteger('reserved_tokens');
            $table->unsignedInteger('actual_tokens')->nullable();
            $table->string('status', 16)->default('reserved');
            $table->timestampsTz();
            $table->index(['workspace_id', 'budget_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_budget_reservations');
        Schema::dropIfExists('support_retry_requests');
        Schema::dropIfExists('support_idempotency_keys');
        Schema::dropIfExists('support_feedback');
        Schema::dropIfExists('support_citations');
        Schema::dropIfExists('support_generations');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
