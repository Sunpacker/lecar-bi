<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_embedding_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('dimensions');
            $table->string('distance_metric')->default('cosine');
            $table->boolean('normalized')->default(true);
            $table->string('chunking_version');
            $table->unsignedInteger('chunk_tokens')->default(500);
            $table->unsignedInteger('overlap_tokens')->default(75);
            $table->timestampsTz();
            $table->unique(['provider', 'model', 'dimensions', 'chunking_version']);
        });

        Schema::create('knowledge_index_builds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('manifest_revision');
            $table->uuid('embedding_profile_id');
            $table->string('status');
            $table->text('error_code')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->foreign('embedding_profile_id')->references('id')->on('knowledge_embedding_profiles');
            $table->unique(['manifest_revision', 'embedding_profile_id']);
        });

        Schema::create('knowledge_publications', function (Blueprint $table): void {
            $table->string('scope')->primary();
            $table->uuid('active_build_id')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestampsTz();
            $table->foreign('active_build_id')->references('id')->on('knowledge_index_builds');
        });

        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('source_type');
            $table->string('manifest_revision');
            $table->string('allowed_path');
            $table->string('canonical_url');
            $table->string('scope');
            $table->string('workspace_id')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('knowledge_document_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('document_id');
            $table->string('source_id');
            $table->string('revision');
            $table->string('content_hash', 64);
            $table->string('title');
            $table->string('language', 12);
            $table->string('canonical_url');
            $table->string('status');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->foreign('source_id')->references('id')->on('knowledge_sources');
            $table->unique(['document_id', 'revision']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('build_id');
            $table->uuid('document_version_id');
            $table->unsignedInteger('chunk_index');
            $table->string('heading')->nullable();
            $table->string('anchor')->nullable();
            $table->text('content');
            $table->string('content_hash', 64);
            $table->string('scope');
            $table->string('workspace_id')->nullable();
            $table->string('fts_configuration', 32)->default('russian');
            $table->timestampsTz();
            $table->foreign('build_id')->references('id')->on('knowledge_index_builds')->cascadeOnDelete();
            $table->foreign('document_version_id')->references('id')->on('knowledge_document_versions');
            $table->unique(['build_id', 'document_version_id', 'chunk_index']);
        });

        Schema::create('knowledge_ingestion_usage', function (Blueprint $table): void {
            $table->date('usage_date')->primary();
            $table->unsignedBigInteger('embedded_tokens')->default(0);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE knowledge_sources ADD CONSTRAINT knowledge_sources_scope_workspace_check CHECK ((scope = 'global' AND workspace_id IS NULL) OR (scope = 'workspace' AND workspace_id IS NOT NULL))");
            DB::statement("ALTER TABLE knowledge_chunks ADD CONSTRAINT knowledge_chunks_scope_workspace_check CHECK ((scope = 'global' AND workspace_id IS NULL) OR (scope = 'workspace' AND workspace_id IS NOT NULL))");
            DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector');
            DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('russian'::regconfig, content)) STORED");
            DB::statement('CREATE INDEX knowledge_chunks_search_vector_gin ON knowledge_chunks USING GIN (search_vector)');
        } else {
            Schema::table('knowledge_chunks', function (Blueprint $table): void {
                $table->text('embedding')->nullable();
                $table->text('search_vector')->nullable();
            });
        }

        DB::table('knowledge_publications')->insert([
            'scope' => 'global',
            'active_build_id' => null,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_ingestion_usage');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_document_versions');
        Schema::dropIfExists('knowledge_sources');
        Schema::dropIfExists('knowledge_publications');
        Schema::dropIfExists('knowledge_index_builds');
        Schema::dropIfExists('knowledge_embedding_profiles');
    }
};
