<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->date('tanggal_actual_kedi')->nullable()->after('rencana_bongkar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->dropColumn('tanggal_actual_kedi');
        });
    }
};
