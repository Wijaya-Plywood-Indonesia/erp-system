<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan kolom generated `tanggal_lunas` (DATE, STORED) pada tabel nota_kayu,
 * diturunkan dari string `status_pelunasan` (format: "Lunas - dd/mm/YYYY HH:ii (user)").
 *
 * Tujuan: memindahkan komputasi tanggal dari query-time (whereRaw/orderByRaw di
 * controller, tidak sargable) ke storage-time (kolom fisik + index), sehingga
 * filter rentang tanggal dan sorting bisa memakai index composite di bawah.
 *
 * AMAN untuk data eksisting:
 * - Hanya ADD COLUMN (generated, read-only) + ADD INDEX. Tidak ada UPDATE/DELETE.
 * - Baris dengan status_pelunasan yang tidak match pola tanggal akan menghasilkan
 *   NULL di tanggal_lunas — perilaku identik dengan STR_TO_DATE pada query lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<SQL
            ALTER TABLE nota_kayus
                ADD COLUMN tanggal_lunas DATE
                    GENERATED ALWAYS AS (
                        STR_TO_DATE(
                            SUBSTRING_INDEX(SUBSTRING_INDEX(status_pelunasan, ' - ', -1), ' (', 1),
                            '%d/%m/%Y %H:%i'
                        )
                    ) STORED
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE nota_kayus
                ADD INDEX idx_status_tanggal_lunas (status_pelunasan, tanggal_lunas)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE nota_kayus DROP INDEX idx_status_tanggal_lunas');
        DB::statement('ALTER TABLE nota_kayus DROP COLUMN tanggal_lunas');
    }
};
