<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel: serah_terima_gudang_satu (TUNGGAL, sesuai skema yang ada)
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            // Penghubung ke mutasi keluar Triplek Jadi. NULL untuk baris lama
            // (yang asalnya dari Pilih Plywood) — jadi backward-compatible.
            $table->foreignId('id_triplek_mutasi_keluar')
                ->nullable()
                ->after('id')
                ->constrained('triplek_jadi_mutasi_keluars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_triplek_mutasi_keluar');
        });
    }
};