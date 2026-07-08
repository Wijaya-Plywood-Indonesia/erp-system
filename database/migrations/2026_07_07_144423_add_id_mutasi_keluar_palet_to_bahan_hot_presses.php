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
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->foreignId('id_mutasi_keluar_palet')
                ->nullable()
                ->after('id_barang_setengah_jadi')
                ->constrained('veneer_jadi_mutasi_keluar_palets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_mutasi_keluar_palet');
        });
    }
};
