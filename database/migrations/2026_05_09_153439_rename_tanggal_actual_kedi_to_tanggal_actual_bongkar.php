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
            $table->renameColumn('tanggal_actual_kedi', 'tanggal_actual_bongkar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->renameColumn('tanggal_actual_bongkar', 'tanggal_actual_kedi');
        });
    }
};
