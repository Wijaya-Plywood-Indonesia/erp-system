<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HppTriplekJadiLogBenchmarkSeeder extends Seeder
{
    public const MARKER = 'BENCHMARK-SEED';

    private const TABLE = 'hpp_triplek_jadi_log';

    /** [panjang cm, lebar cm] */
    private const SIZES = [[244, 122], [244, 122], [183, 122], [213, 91]];

    /** tebal mm */
    private const THICKNESSES = [2.7, 3.0, 3.7, 5.2, 9.0, 12.0, 15.0, 18.0];

    private const GRADES = ['KW1', 'KW2', 'KW3', 'BC', 'CC'];

    public function run(): void
    {
        $this->seedRows((int) env('BENCHMARK_ROWS', 100_000));
    }

    public function seedRows(int $totalRows, int $chunkSize = 2000, int $years = 3): int
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seeder benchmark tidak boleh dijalankan di production.');
        }

        $woodTypeIds = DB::table('jenis_kayus')->pluck('id')->all();

        if ($woodTypeIds === []) {
            throw new RuntimeException('Tabel jenis_kayus kosong. Buat minimal 1 jenis kayu terlebih dahulu.');
        }

        DB::disableQueryLog();
        mt_srand(20260101); // data reproducible antar-run

        $now = time();
        $timestamp = date('Y-m-d H:i:s', $now);
        $progress = $this->command?->getOutput()->createProgressBar($totalRows);
        $inserted = 0;

        while ($inserted < $totalRows) {
            $batchSize = min($chunkSize, $totalRows - $inserted);
            $rows = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = $this->makeRow($woodTypeIds, $now, $timestamp, $years);
            }

            DB::table(self::TABLE)->insert($rows);

            $inserted += $batchSize;
            $progress?->advance($batchSize);
            unset($rows);
        }

        $progress?->finish();
        $this->command?->newLine();

        return $inserted;
    }

    public function purge(int $chunkSize = 10_000): int
    {
        $deleted = 0;

        do {
            $affected = DB::table(self::TABLE)
                ->where('keterangan', self::MARKER)
                ->limit($chunkSize)
                ->delete();
            $deleted += $affected;
        } while ($affected > 0);

        return $deleted;
    }

    /** @return array<string, mixed> */
    private function makeRow(array $woodTypeIds, int $now, string $timestamp, int $years): array
    {
        [$length, $width] = $this->pick(self::SIZES);
        $thickness = $this->pick(self::THICKNESSES);

        $isIncoming = mt_rand(0, 1) === 1;
        $sheets = mt_rand(10, 500);
        $unitVolume = ($length / 100) * ($width / 100) * ($thickness / 1000);

        $labourCost = mt_rand(150_000, 400_000);
        $auxiliaryCost = mt_rand(50_000, 200_000);
        $averageCost = $labourCost + $auxiliaryCost;

        $stockBefore = mt_rand(500, 20_000);
        $stockAfter = $isIncoming ? $stockBefore + $sheets : max(0, $stockBefore - $sheets);
        $volume = round($unitVolume * $sheets, 6);

        return [
            'id_jenis_kayu'        => $this->pick($woodTypeIds),
            'panjang'              => $length,
            'lebar'                => $width,
            'tebal'                => $thickness,
            'kw_grade'             => $this->pick(self::GRADES),
            'tanggal'              => date('Y-m-d', $now - mt_rand(0, $years * 365) * 86400),
            'tipe_transaksi'       => $isIncoming ? 'masuk' : 'keluar',
            'keterangan'           => self::MARKER,
            'referensi_type'       => null,
            'referensi_id'         => null,
            'total_lembar'         => $sheets,
            'total_kubikasi'       => $volume,
            'hpp_pekerja'          => $labourCost,
            'hpp_bahan_penolong'   => $auxiliaryCost,
            'hpp_average'          => $averageCost,
            'nilai_stok'           => round($volume * $averageCost, 2),
            'stok_lembar_before'   => $stockBefore,
            'stok_kubikasi_before' => round($stockBefore * $unitVolume, 6),
            'nilai_stok_before'    => round($stockBefore * $unitVolume * $averageCost, 2),
            'stok_lembar_after'    => $stockAfter,
            'stok_kubikasi_after'  => round($stockAfter * $unitVolume, 6),
            'nilai_stok_after'     => round($stockAfter * $unitVolume * $averageCost, 2),
            'created_at'           => $timestamp,
            'updated_at'           => $timestamp,
        ];
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
