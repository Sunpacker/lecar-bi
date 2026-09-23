<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fact_supplier_deliveries', function (Blueprint $table) {
            $table->integer('defect_quantity')->default(0)->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('fact_supplier_deliveries', function (Blueprint $table) {
            $table->dropColumn('defect_quantity');
        });
    }
};
