<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Memperbaiki definisi VIEW `kayu_compare_temp` yang rusak
     * (ada token `grade` dobel sebelum semicolon penutup, kemungkinan
     * bug dari mysqldump saat mengekspor VIEW berbasis CTE).
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS `kayu_compare_temp`');

        DB::statement("
            CREATE VIEW `kayu_compare_temp` AS
            WITH detail AS (
                SELECT
                    `detail_kayu_masuks`.`id_kayu_masuk`   AS `id_kayu_masuk`,
                    `detail_kayu_masuks`.`id_jenis_kayu`   AS `id_jenis_kayu`,
                    `detail_kayu_masuks`.`id_lahan`        AS `id_lahan`,
                    `detail_kayu_masuks`.`diameter`        AS `diameter`,
                    `detail_kayu_masuks`.`panjang`         AS `panjang`,
                    `detail_kayu_masuks`.`grade`           AS `grade`,
                    SUM(`detail_kayu_masuks`.`jumlah_batang`) AS `detail_jumlah`
                FROM `detail_kayu_masuks`
                GROUP BY
                    `detail_kayu_masuks`.`id_kayu_masuk`,
                    `detail_kayu_masuks`.`id_jenis_kayu`,
                    `detail_kayu_masuks`.`id_lahan`,
                    `detail_kayu_masuks`.`diameter`,
                    `detail_kayu_masuks`.`panjang`,
                    `detail_kayu_masuks`.`grade`
            ),
            turusan AS (
                SELECT
                    `detail_turusan_kayus`.`id_kayu_masuk`  AS `id_kayu_masuk`,
                    `detail_turusan_kayus`.`jenis_kayu_id`  AS `jenis_kayu_id`,
                    `detail_turusan_kayus`.`lahan_id`       AS `lahan_id`,
                    `detail_turusan_kayus`.`diameter`       AS `diameter`,
                    `detail_turusan_kayus`.`panjang`        AS `panjang`,
                    `detail_turusan_kayus`.`grade`          AS `grade`,
                    SUM(`detail_turusan_kayus`.`kuantitas`) AS `turusan_jumlah`
                FROM `detail_turusan_kayus`
                GROUP BY
                    `detail_turusan_kayus`.`id_kayu_masuk`,
                    `detail_turusan_kayus`.`jenis_kayu_id`,
                    `detail_turusan_kayus`.`lahan_id`,
                    `detail_turusan_kayus`.`diameter`,
                    `detail_turusan_kayus`.`panjang`,
                    `detail_turusan_kayus`.`grade`
            ),
            left_join AS (
                SELECT
                    `d`.`id_kayu_masuk`  AS `id_kayu_masuk`,
                    `d`.`id_jenis_kayu`  AS `id_jenis_kayu`,
                    `d`.`id_lahan`       AS `id_lahan`,
                    `d`.`diameter`       AS `diameter`,
                    `d`.`panjang`        AS `panjang`,
                    `d`.`grade`          AS `grade`,
                    `d`.`detail_jumlah`  AS `detail_jumlah`,
                    COALESCE(`t`.`turusan_jumlah`, 0) AS `turusan_jumlah`
                FROM `detail` `d`
                LEFT JOIN `turusan` `t`
                    ON `d`.`id_kayu_masuk` = `t`.`id_kayu_masuk`
                   AND `d`.`id_jenis_kayu` = `t`.`jenis_kayu_id`
                   AND `d`.`id_lahan`      = `t`.`lahan_id`
                   AND `d`.`diameter`      = `t`.`diameter`
                   AND `d`.`panjang`       = `t`.`panjang`
                   AND `d`.`grade`         = `t`.`grade`
            ),
            right_join AS (
                SELECT
                    `t`.`id_kayu_masuk`  AS `id_kayu_masuk`,
                    `t`.`jenis_kayu_id`  AS `id_jenis_kayu`,
                    `t`.`lahan_id`       AS `id_lahan`,
                    `t`.`diameter`       AS `diameter`,
                    `t`.`panjang`        AS `panjang`,
                    `t`.`grade`          AS `grade`,
                    0 AS `detail_jumlah`,
                    `t`.`turusan_jumlah` AS `turusan_jumlah`
                FROM `turusan` `t`
                LEFT JOIN `detail` `d`
                    ON `d`.`id_kayu_masuk` = `t`.`id_kayu_masuk`
                   AND `d`.`id_jenis_kayu` = `t`.`jenis_kayu_id`
                   AND `d`.`id_lahan`      = `t`.`lahan_id`
                   AND `d`.`diameter`      = `t`.`diameter`
                   AND `d`.`panjang`       = `t`.`panjang`
                   AND `d`.`grade`         = `t`.`grade`
                WHERE `d`.`id_jenis_kayu` IS NULL
            )
            SELECT
                ROW_NUMBER() OVER () AS `id`,
                `x`.`id_kayu_masuk`  AS `id_kayu_masuk`,
                `x`.`id_jenis_kayu`  AS `id_jenis_kayu`,
                `x`.`id_lahan`       AS `id_lahan`,
                `x`.`diameter`       AS `diameter`,
                `x`.`panjang`        AS `panjang`,
                `x`.`grade`          AS `grade`,
                SUM(`x`.`detail_jumlah`)  AS `detail_jumlah`,
                SUM(`x`.`turusan_jumlah`) AS `turusan_jumlah`,
                SUM(`x`.`detail_jumlah` - `x`.`turusan_jumlah`) AS `selisih`
            FROM (
                SELECT
                    `left_join`.`id_kayu_masuk`,
                    `left_join`.`id_jenis_kayu`,
                    `left_join`.`id_lahan`,
                    `left_join`.`diameter`,
                    `left_join`.`panjang`,
                    `left_join`.`grade`,
                    `left_join`.`detail_jumlah`,
                    `left_join`.`turusan_jumlah`
                FROM `left_join`
                UNION ALL
                SELECT
                    `right_join`.`id_kayu_masuk`,
                    `right_join`.`id_jenis_kayu`,
                    `right_join`.`id_lahan`,
                    `right_join`.`diameter`,
                    `right_join`.`panjang`,
                    `right_join`.`grade`,
                    `right_join`.`detail_jumlah`,
                    `right_join`.`turusan_jumlah`
                FROM `right_join`
            ) AS `x`
            GROUP BY
                `x`.`id_kayu_masuk`,
                `x`.`id_jenis_kayu`,
                `x`.`id_lahan`,
                `x`.`diameter`,
                `x`.`panjang`,
                `x`.`grade`
            ORDER BY
                `x`.`id_kayu_masuk` ASC,
                `x`.`id_jenis_kayu` ASC,
                `x`.`id_lahan` ASC,
                `x`.`diameter` ASC,
                `x`.`panjang` ASC,
                `x`.`grade` ASC
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `kayu_compare_temp`');
    }
};