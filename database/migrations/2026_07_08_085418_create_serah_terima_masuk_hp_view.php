<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Hapus view lama jika ada
        DB::statement("DROP VIEW IF EXISTS serah_terima_masuk_hp");

        // 2. Langsung buat VIEW tanpa dibungkus Schema::create
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
                mk.diterima_by               AS diterima_by,
                mk.diterima_at               AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM veneer_jadi_mutasi_keluar_palets p
            JOIN veneer_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_kayus jk ON jk.id = mk.id_jenis_kayu

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
                mk.diterima_by               AS diterima_by,
                mk.diterima_at               AS diterima_at,
                mk.id_produksi_hp            AS id_produksi_hp,
                mk.keterangan                AS keterangan
            FROM platform_jadi_mutasi_keluar_palets p
            JOIN platform_jadi_mutasi_keluars mk ON mk.id = p.id_mutasi_keluar
            LEFT JOIN jenis_barang jb ON jb.id = mk.id_jenis_barang
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS serah_terima_masuk_hp");
    }
};
