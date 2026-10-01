<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pegawai pindahan ke Hotpress tidak lagi memilih mesin, jadi id_mesin
     * di detail_pegawai_hp harus boleh kosong. Foreign key tetap dipertahankan.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE detail_pegawai_hp MODIFY id_mesin BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Gagal kalau sudah ada baris dengan id_mesin kosong (pegawai pindahan) — isi dulu manual.
        DB::statement('ALTER TABLE detail_pegawai_hp MODIFY id_mesin BIGINT UNSIGNED NOT NULL');
    }
};