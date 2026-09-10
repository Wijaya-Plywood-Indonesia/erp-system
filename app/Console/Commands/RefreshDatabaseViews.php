<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshDatabaseViews extends Command
{
    /**
     * Nama perintah yang dijalankan di terminal.
     */
    protected $signature = 'db:refresh-views';

    /**
     * Deskripsi singkat perintah.
     */
    protected $description = 'Membuat ulang Database View serah_terima_masuk_hp untuk antrean serah terima Hotpress';

    public function handle(): int
    {
        $this->info('Mulai memperbarui Database View: serah_terima_masuk_hp...');

        try {
            // 1. Paksa hapus baik dalam bentuk TABLE maupun VIEW agar tidak bentrok
            DB::statement("DROP TABLE IF EXISTS serah_terima_masuk_hp");
            DB::statement("DROP VIEW IF EXISTS serah_terima_masuk_hp");

            // 2. Buat ulang Database View
            //    🌟 Ditambahkan kolom ditolak_by, alasan_tolak, ditolak_at di
            //    ketiga cabang UNION ALL, plus filter agar baris yang sudah
            //    ditolak tidak lagi ikut tampil di VIEW ini sama sekali.
            DB::statement("
        CREATE VIEW serah_terima_masuk_hp AS
        
        -- 1. SUMBER: VENEER JADI
        SELECT
            CONCAT('veneer-', p.id)      AS id,
            'veneer'                    AS sumber,
            p.id                        AS id_asli,
            mk.id                       AS id_mutasi_keluar,
            mk.created_at               AS tanggal_keluar,
            jk.nama_kayu                AS jenis_nama,
            mk.panjang                  AS panjang,
            mk.lebar                    AS lebar,
            mk.tebal                    AS tebal,
            mk.kw_grade                 AS kw_grade,
            p.nomor_palet               AS nomor_palet,
            p.jumlah_lembar             AS jumlah_lembar,
            mk.tujuan                   AS tujuan,
            mk.dikeluarkan_by           AS operator_id,
            p.diterima_by               AS diterima_by,
            p.diterima_at               AS diterima_at,
            p.ditolak_by                AS ditolak_by,
            p.alasan_tolak              AS alasan_tolak,
            p.ditolak_at                AS ditolak_at,
            mk.id_produksi_hp           AS id_produksi_hp,
            mk.keterangan               AS keterangan
        FROM veneer_jadi_mutasi_keluar_palets p
        JOIN veneer_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
        LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu
        WHERE LOWER(mk.tujuan) = 'hotpress'
          AND p.ditolak_by IS NULL

        UNION ALL

        -- 2. SUMBER: PLATFORM JADI
        SELECT
            CONCAT('platform-', p.id)   AS id,
            'platform_jadi'             AS sumber,
            p.id                        AS id_asli,
            mk.id                       AS id_mutasi_keluar,
            mk.created_at               AS tanggal_keluar,
            jb.nama_jenis_barang        AS jenis_nama,
            mk.panjang                  AS panjang,
            mk.lebar                    AS lebar,
            mk.tebal                    AS tebal,
            mk.kw_grade                 AS kw_grade,
            p.nomor_palet               AS nomor_palet,
            p.jumlah_lembar             AS jumlah_lembar,
            mk.tujuan                   AS tujuan,
            mk.dikeluarkan_by           AS operator_id,
            p.diterima_by               AS diterima_by,
            p.diterima_at               AS diterima_at,
            p.ditolak_by                AS ditolak_by,
            p.alasan_tolak              AS alasan_tolak,
            p.ditolak_at                AS ditolak_at,
            mk.id_produksi_hp           AS id_produksi_hp,
            mk.keterangan               AS keterangan
        FROM platform_jadi_mutasi_keluar_palets p
        JOIN platform_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
        LEFT JOIN jenis_barang jb ON jb.id = mk.id_jenis_barang
        WHERE LOWER(mk.tujuan) = 'hotpress'
          AND p.ditolak_by IS NULL

        UNION ALL

        -- 3. SUMBER: TRIPLEK JADI
        SELECT
            CONCAT('triplek-', p.id)    AS id,
            'triplek_jadi'              AS sumber,
            p.id                        AS id_asli,
            mk.id                       AS id_mutasi_keluar,
            mk.created_at               AS tanggal_keluar,
            jk.nama_kayu                AS jenis_nama,
            mk.panjang                  AS panjang,
            mk.lebar                    AS lebar,
            mk.tebal                    AS tebal,
            mk.kw_grade                 AS kw_grade,
            p.nomor_palet               AS nomor_palet,
            p.jumlah_lembar             AS jumlah_lembar,
            mk.tujuan                   AS tujuan,
            mk.dikeluarkan_by           AS operator_id,
            p.diterima_by               AS diterima_by,
            p.diterima_at               AS diterima_at,
            p.ditolak_by                AS ditolak_by,
            p.alasan_tolak              AS alasan_tolak,
            p.ditolak_at                AS ditolak_at,
            mk.id_produksi_hp           AS id_produksi_hp,
            mk.keterangan               AS keterangan
        FROM triplek_jadi_mutasi_keluar_palets p
        JOIN triplek_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
        LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu
        WHERE LOWER(mk.tujuan) = 'hotpress'
          AND p.ditolak_by IS NULL
    ");

            $this->info('✓ Database View [serah_terima_masuk_hp] berhasil diperbarui!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Gagal memperbarui Database View: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}