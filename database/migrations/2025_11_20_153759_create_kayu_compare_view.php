<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS kayu_compare_temp");

        DB::statement("
            CREATE OR REPLACE VIEW kayu_compare_temp AS
            WITH 
            detail AS (
                SELECT
                    id_kayu_masuk,
                    id_jenis_kayu,
                    id_lahan,
                    diameter,
                    panjang,
                    grade,
                    SUM(jumlah_batang) AS detail_jumlah
                FROM detail_kayu_masuks
                GROUP BY id_kayu_masuk, id_jenis_kayu, id_lahan, diameter, panjang, grade
            ),
            turusan AS (
                SELECT
                    id_kayu_masuk,
                    jenis_kayu_id,
                    lahan_id,
                    diameter,
                    panjang,
                    grade,
                    SUM(kuantitas) AS turusan_jumlah
                FROM detail_turusan_kayus
                GROUP BY id_kayu_masuk, jenis_kayu_id, lahan_id, diameter, panjang, grade
            ),
            left_join AS (
                SELECT
                    d.id_kayu_masuk,
                    d.id_jenis_kayu,
                    d.id_lahan,
                    d.diameter,
                    d.panjang,
                    d.grade,
                    d.detail_jumlah,
                    COALESCE(t.turusan_jumlah, 0) AS turusan_jumlah
                FROM detail d
                LEFT JOIN turusan t
                    ON d.id_kayu_masuk = t.id_kayu_masuk
                    AND d.id_jenis_kayu = t.jenis_kayu_id
                    AND d.id_lahan = t.lahan_id
                    AND d.diameter = t.diameter
                    AND d.panjang = t.panjang
                    AND d.grade = t.grade
            ),
            right_join AS (
                SELECT
                    t.id_kayu_masuk,
                    t.jenis_kayu_id AS id_jenis_kayu,
                    t.lahan_id AS id_lahan,
                    t.diameter,
                    t.panjang,
                    t.grade,
                    0 AS detail_jumlah,
                    t.turusan_jumlah
                FROM turusan t
                LEFT JOIN detail d
                    ON d.id_kayu_masuk = t.id_kayu_masuk
                    AND d.id_jenis_kayu = t.jenis_kayu_id
                    AND d.id_lahan = t.lahan_id
                    AND d.diameter = t.diameter
                    AND d.panjang = t.panjang
                    AND d.grade = t.grade
                WHERE d.id_jenis_kayu IS NULL
            )

            SELECT
                ROW_NUMBER() OVER () AS id,
                id_kayu_masuk,
                id_jenis_kayu,
                id_lahan,
                diameter,
                panjang,
                grade,
                SUM(detail_jumlah) AS detail_jumlah,
                SUM(turusan_jumlah) AS turusan_jumlah,
                SUM(detail_jumlah - turusan_jumlah) AS selisih
            FROM (
                SELECT * FROM left_join
                UNION ALL
                SELECT * FROM right_join
            ) AS x
            GROUP BY
                id_kayu_masuk,
                id_jenis_kayu,
                id_lahan,
                diameter,
                panjang,
                grade
            ORDER BY
                id_kayu_masuk,
                id_jenis_kayu,
                id_lahan,
                diameter,
                panjang,
                grade;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS kayu_compare_temp");
    }
};
