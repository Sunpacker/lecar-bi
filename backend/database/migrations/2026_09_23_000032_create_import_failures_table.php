<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_failures', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('batch_id', 64);
            $table->string('workspace_id', 36);
            $table->unsignedInteger('row_number');
            $table->string('field', 64)->nullable();
            $table->string('value', 255)->nullable();
            $table->text('error_message');
            $table->dateTimeTz('created_at');

            $table->foreign('batch_id')->references('id')->on('import_batches')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['batch_id', 'row_number']);
            $table->index(['workspace_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_failures');
    }
};
