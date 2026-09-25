<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_dataset_versions', function (Blueprint $table) {
            $table->string('workspace_id', 64);
            $table->string('dataset', 32);
            $table->bigInteger('version')->default(1);
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['workspace_id', 'dataset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_dataset_versions');
    }
};
