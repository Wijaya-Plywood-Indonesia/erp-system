<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kolom `sumber` di `bahan_hotpress` didefinisikan sebagai
     * ENUM('veneer', 'platform') sejak migrasi
     * 2026_07_08_093713_add_sumber_bahan_to_bahan_hotpress_table. Saat
     * dukungan Triplek ditambahkan (2026_07_28_101713), ENUM ini tidak
     * ikut diperluas — akibatnya MySQL menolak nilai 'triplek' dan diam-
     * diam menyimpannya sebagai string kosong '', meski
     * `id_mutasi_keluar_triplek` tersimpan benar. Ini migrasi perbaikannya:
     * 1) perluas ENUM supaya 'triplek' jadi nilai yang sah,
     * 2) backfill baris yang sudah kadung tersimpan sumber-nya kosong.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE bahan_hotpress MODIFY COLUMN sumber ENUM('veneer', 'platform', 'triplek') NULL");

        DB::table('bahan_hotpress')
            ->whereNotNull('id_mutasi_keluar_triplek')
            ->where(function ($q) {
                $q->whereNull('sumber')->orWhere('sumber', '');
            })
            ->update(['sumber' => 'triplek']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE bahan_hotpress MODIFY COLUMN sumber ENUM('veneer', 'platform') NULL");
    }
};
