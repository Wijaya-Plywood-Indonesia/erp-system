<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membuat ulang VIEW serah_terima_masuk_hp dengan tambahan sumber ketiga:
 * Gudang Triplek Jadi. Meniru pola veneer & platform persis — join palet ke
 * mutasi, ambil status terima dari palet (p.diterima_by / p.diterima_at),
 * filter tujuan 'hotpress'.
 *
 * Barang triplek jadi ber-PK 'triplek-{id_palet}' di view.
 *
 * PRASYARAT: migrasi 2026_07_22_000001 (align kolom) harus jalan lebih dulu,
 * karena view ini mereferensikan mk.id_produksi_hp, mk.keterangan,
 * p.diterima_by, p.diterima_at pada tabel triplek jadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Objek bernama serah_terima_masuk_hp bisa berupa VIEW atau (keliru)
        // TABLE — di sebagian environment ia terlanjur jadi tabel. Hapus dua-duanya
        // secara aman: DROP VIEW menolak kalau ia tabel, dan sebaliknya, jadi
        // panggil keduanya dengan IF EXISTS (yang salah-tipe akan no-op/diabaikan).
        try { DB::statement("DROP VIEW IF EXISTS serah_terima_masuk_hp"); } catch (\Throwable $e) {}
        try { DB::statement("DROP TABLE IF EXISTS serah_terima_masuk_hp"); } catch (\Throwable $e) {}

        DB::statement("
            CREATE VIEW serah_terima_masuk_hp AS
            SELECT
                CONCAT('veneer-', p.id)      AS id,
                'veneer'                     AS sumber,
                p.id                         AS id_asli,
                mk.id                        AS id_mutasi_keluar,
                mk.created_at                AS tanggal_keluar,
                jk.nama_kayu                 AS jenis_nama,
                mk.panjang                   AS panjang,
                mk.lebar                     AS lebar,
                mk.tebal                     AS tebal,
                mk.kw_grade                  AS kw_grade,
                p.nomor_palet                AS nomor_palet,
                p.jumlah_lembar              AS jumlah_lembar,
                mk.tujuan                    AS tujuan,
                mk.dikeluarkan_by            AS dikeluarkan_by,
                p.diterima_by                AS diterima_by,
                p.diterima_at                AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM veneer_jadi_mutasi_keluar_palets p
            JOIN veneer_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu
            WHERE LOWER(mk.tujuan) = 'hotpress'

            UNION ALL

            SELECT
                CONCAT('platform-', p.id)    AS id,
                'platform_jadi'              AS sumber,
                p.id                         AS id_asli,
                mk.id                        AS id_mutasi_keluar,
                mk.created_at                AS tanggal_keluar,
                jb.nama_jenis_barang         AS jenis_nama,
                mk.panjang                   AS panjang,
                mk.lebar                     AS lebar,
                mk.tebal                     AS tebal,
                mk.kw_grade                  AS kw_grade,
                p.nomor_palet                AS nomor_palet,
                p.jumlah_lembar              AS jumlah_lembar,
                mk.tujuan                    AS tujuan,
                mk.dikeluarkan_by            AS dikeluarkan_by,
                p.diterima_by                AS diterima_by,
                p.diterima_at                AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM platform_jadi_mutasi_keluar_palets p
            JOIN platform_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_barang jb ON jb.id = mk.id_jenis_barang
            WHERE LOWER(mk.tujuan) = 'hotpress'

            UNION ALL

            SELECT
                CONCAT('triplek-', p.id)     AS id,
                'triplek_jadi'               AS sumber,
                p.id                         AS id_asli,
                mk.id                        AS id_mutasi_keluar,
                mk.created_at                AS tanggal_keluar,
                jk.nama_kayu                 AS jenis_nama,
                mk.panjang                   AS panjang,
                mk.lebar                     AS lebar,
                mk.tebal                     AS tebal,
                mk.kw_grade                  AS kw_grade,
                p.nomor_palet                AS nomor_palet,
                p.jumlah_lembar              AS jumlah_lembar,
                mk.tujuan                    AS tujuan,
                mk.dikeluarkan_by            AS dikeluarkan_by,
                p.diterima_by                AS diterima_by,
                p.diterima_at                AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM triplek_jadi_mutasi_keluar_palets p
            JOIN triplek_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu
            WHERE LOWER(mk.tujuan) = 'hotpress'
        ");
    }

    public function down(): void
    {
        // Kembalikan ke versi TANPA triplek jadi (dua sumber saja).
        // Objek bernama serah_terima_masuk_hp bisa berupa VIEW atau (keliru)
        // TABLE — di sebagian environment ia terlanjur jadi tabel. Hapus dua-duanya
        // secara aman: DROP VIEW menolak kalau ia tabel, dan sebaliknya, jadi
        // panggil keduanya dengan IF EXISTS (yang salah-tipe akan no-op/diabaikan).
        try { DB::statement("DROP VIEW IF EXISTS serah_terima_masuk_hp"); } catch (\Throwable $e) {}
        try { DB::statement("DROP TABLE IF EXISTS serah_terima_masuk_hp"); } catch (\Throwable $e) {}

        DB::statement("
            CREATE VIEW serah_terima_masuk_hp AS
            SELECT
                CONCAT('veneer-', p.id)      AS id,
                'veneer'                     AS sumber,
                p.id                         AS id_asli,
                mk.id                        AS id_mutasi_keluar,
                mk.created_at                AS tanggal_keluar,
                jk.nama_kayu                 AS jenis_nama,
                mk.panjang                   AS panjang,
                mk.lebar                     AS lebar,
                mk.tebal                     AS tebal,
                mk.kw_grade                  AS kw_grade,
                p.nomor_palet                AS nomor_palet,
                p.jumlah_lembar              AS jumlah_lembar,
                mk.tujuan                    AS tujuan,
                mk.dikeluarkan_by            AS dikeluarkan_by,
                p.diterima_by                AS diterima_by,
                p.diterima_at                AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM veneer_jadi_mutasi_keluar_palets p
            JOIN veneer_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu
            WHERE LOWER(mk.tujuan) = 'hotpress'

            UNION ALL

            SELECT
                CONCAT('platform-', p.id)    AS id,
                'platform_jadi'              AS sumber,
                p.id                         AS id_asli,
                mk.id                        AS id_mutasi_keluar,
                mk.created_at                AS tanggal_keluar,
                jb.nama_jenis_barang         AS jenis_nama,
                mk.panjang                   AS panjang,
                mk.lebar                     AS lebar,
                mk.tebal                     AS tebal,
                mk.kw_grade                  AS kw_grade,
                p.nomor_palet                AS nomor_palet,
                p.jumlah_lembar              AS jumlah_lembar,
                mk.tujuan                    AS tujuan,
                mk.dikeluarkan_by            AS dikeluarkan_by,
                p.diterima_by                AS diterima_by,
                p.diterima_at                AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM platform_jadi_mutasi_keluar_palets p
            JOIN platform_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_barang jb ON jb.id = mk.id_jenis_barang
            WHERE LOWER(mk.tujuan) = 'hotpress'
        ");
    }
};