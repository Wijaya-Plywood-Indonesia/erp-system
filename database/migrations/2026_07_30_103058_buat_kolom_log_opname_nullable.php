<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel summary stok mengizinkan id_jenis_kayu / id_jenis_barang / grade
 * bernilai NULL, tetapi tabel log-nya tidak. Akibatnya opname (termasuk
 * penolan stok untuk baris yang dihapus) gagal dengan:
 *
 *   SQLSTATE[23000]: Column 'id_jenis_kayu' cannot be null
 *
 * Migration ini menyelaraskan skema log dengan summary-nya.
 *
 * Tipe kolom dibaca dari information_schema, jadi tidak ada risiko
 * tipe berubah (unsignedBigInteger vs int vs varchar panjang tertentu).
 */
return new class extends Migration
{
    /** tabel => daftar kolom yang harus nullable */
    private array $target = [
        'hpp_veneer_basah_log'      => ['id_jenis_kayu', 'kw'],
        'hpp_veneer_jadi_log'       => ['id_jenis_kayu', 'kw_grade'],
        'hpp_platform_mth_log'      => ['id_jenis_kayu', 'kw_grade'],
        'hpp_triplek_mth_log'       => ['id_jenis_kayu', 'kw_grade'],
        'hpp_plywood_siap_jual_log' => ['id_jenis_kayu', 'kw_grade'],
        'hpp_platform_jadi_log'     => ['id_jenis_barang', 'kw_grade'],
        'hpp_triplek_jadi_log'      => ['id_jenis_kayu', 'kw_grade'],
        'gudang_satu_log'           => ['id_jenis_kayu', 'kw_grade'],
    ];

    public function up(): void
    {
        foreach ($this->target as $tabel => $kolomList) {
            if (!Schema::hasTable($tabel)) {
                echo "  - lewati {$tabel} (tabel tidak ada)\n";
                continue;
            }

            foreach ($kolomList as $kolom) {
                if (!Schema::hasColumn($tabel, $kolom)) {
                    echo "  - lewati {$tabel}.{$kolom} (kolom tidak ada)\n";
                    continue;
                }

                $info = $this->infoKolom($tabel, $kolom);

                if (!$info) continue;

                if (strtoupper($info->IS_NULLABLE) === 'YES') {
                    echo "  - {$tabel}.{$kolom} sudah nullable\n";
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `%s` %s NULL',
                    $tabel,
                    $kolom,
                    $info->COLUMN_TYPE
                ));

                echo "  ✓ {$tabel}.{$kolom} ({$info->COLUMN_TYPE}) -> NULL\n";
            }
        }
    }

    public function down(): void
    {
        // Tidak dibalik otomatis: mengembalikan NOT NULL akan gagal kalau
        // sudah ada baris log ber-NULL. Bersihkan datanya dulu bila perlu.
        echo "  ! down() sengaja tidak melakukan apa-apa. Lihat komentar di migration.\n";
    }

    private function infoKolom(string $tabel, string $kolom): ?object
    {
        return DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?',
            [DB::getDatabaseName(), $tabel, $kolom]
        );
    }
};