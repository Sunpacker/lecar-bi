<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id', 64);
            $table->string('dataset_type', 32); // 'sales', 'inventory'
            $table->string('source_format', 16); // 'csv', 'json'
            $table->string('original_filename', 255);
            $table->string('stored_file_path', 255);
            $table->string('status', 32)->default('pending'); // 'pending', 'validating', 'processing', 'completed', 'completed_with_errors', 'failed'
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');

            $table->index(['workspace_id', 'status'], 'idx_import_batches_ws_status');
            $table->index(['workspace_id', 'created_at'], 'idx_import_batches_ws_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
