<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom nama_bahan ke tabel bahan_penolong_rotary.
     */
    public function up(): void
    {
        Schema::table('bahan_penolong_rotary', function (Blueprint $table) {
            // Kita tambahkan kolom nama_bahan setelah kolom id_produksi
            // Gunakan ->nullable() jika kolom ini boleh kosong di awal
            if (!Schema::hasColumn('bahan_penolong_rotary', 'nama_bahan')) {
                $table->string('nama_bahan')->after('id_produksi')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     * Menghapus kolom jika migrasi di-rollback.
     */
    public function down(): void
    {
        Schema::table('bahan_penolong_rotary', function (Blueprint $table) {
            if (Schema::hasColumn('bahan_penolong_rotary', 'nama_bahan')) {
                $table->dropColumn('nama_bahan');
            }
        });
    }
};
