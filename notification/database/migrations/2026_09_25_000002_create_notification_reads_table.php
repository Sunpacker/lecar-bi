<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id')->index();
            $table->uuid('notification_id')->index();
            $table->timestampTz('read_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
