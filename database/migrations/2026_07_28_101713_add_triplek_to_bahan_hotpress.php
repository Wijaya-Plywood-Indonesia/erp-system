<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            // Sumber ketiga: palet dari Gudang Triplek Jadi. NULL untuk baris
            // lama (veneer/platform) — backward-compatible.
            $table->foreignId('id_mutasi_keluar_triplek')
                ->nullable()
                ->after('id_mutasi_keluar_platform')
                ->constrained('triplek_jadi_mutasi_keluar_palets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_mutasi_keluar_triplek');
        });
    }
};