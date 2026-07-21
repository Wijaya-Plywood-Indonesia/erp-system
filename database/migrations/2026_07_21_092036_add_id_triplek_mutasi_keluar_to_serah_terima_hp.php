<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel: serah_terima_hp (TUNGGAL, cocok dengan $table di model
        // SerahTerimaHp dan dengan error "Unknown column ... in serah_terima_hp").
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            // Penghubung ke mutasi keluar Gudang Triplek Jadi. NULL untuk baris
            // lama (hotpress / graji / sanding) — jadi backward-compatible.
            $table->foreignId('id_triplek_mutasi_keluar')
                ->nullable()
                ->after('id_hasil_sanding')
                ->constrained('triplek_jadi_mutasi_keluars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_triplek_mutasi_keluar');
        });
    }
};