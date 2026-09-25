<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->index();
            $table->string('workspace_id')->index();
            $table->string('level', 32);
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'workspace_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
