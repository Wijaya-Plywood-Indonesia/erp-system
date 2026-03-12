<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\HppAverageSummarie;
use App\Models\HppAverageLog;

/**
 * HppAverageSummariesSeeder
 *
 * Mengisi hpp_average_summaries + hpp_average_logs (tipe masuk / saldo awal)
 * untuk keperluan test jurnal rotary.
 *
 * SESUAIKAN id_lahan dan id_jenis_kayu dengan data di DB Anda sebelum dijalankan.
 * Cek dengan:
 *   php artisan tinker
 *   DB::table('lahans')->select('id','nama_lahan','kode_lahan')->get();
 *   DB::table('jenis_kayus')->select('id','nama_jenis_kayu')->get();
 *
 * Jalankan:
 *   php artisan db:seed --class=HppAverageSummariesSeeder
 *
 * Reset:
 *   DB::table('hpp_average_summaries')->truncate();
 *   DB::table('hpp_average_logs')->where('keterangan','like','%Saldo awal seeder%')->delete();
 */
class HppAverageSummariesSeeder extends Seeder
{
    /**
     * Ganti id_lahan dan id_jenis_kayu sesuai DB Anda.
     * grade  → nullable (tidak dipakai, isi null)
     * panjang → 130 atau 260 (cm)
     *
     * hpp_average = nilai Rp per m³
     * stok_kubikasi = m³ tersedia
     * stok_batang   = jumlah batang tersedia
     */
    const DATA = [
        // ── Lahan 130 ────────────────────────────────────────────────────────
        ['id_lahan' => 1,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 108, 'stok_kubikasi' => 3.7092, 'hpp_average' => 964018],
        ['id_lahan' => 2,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 50,  'stok_kubikasi' => 2.4500, 'hpp_average' => 964018],
        ['id_lahan' => 3,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 60,  'stok_kubikasi' => 2.9400, 'hpp_average' => 964018],
        ['id_lahan' => 4,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 35,  'stok_kubikasi' => 1.7150, 'hpp_average' => 964018],
        ['id_lahan' => 6,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 45,  'stok_kubikasi' => 2.2050, 'hpp_average' => 964018],
        ['id_lahan' => 7,  'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 25,  'stok_kubikasi' => 1.0250, 'hpp_average' => 964018],
        ['id_lahan' => 11, 'id_jenis_kayu' => 1, 'panjang' => 130, 'stok_batang' => 55,  'stok_kubikasi' => 2.6950, 'hpp_average' => 964018],

        // ── Lahan 260 ────────────────────────────────────────────────────────
        ['id_lahan' => 8,  'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 17,  'stok_kubikasi' => 2.8317, 'hpp_average' => 1294776],
        ['id_lahan' => 9,  'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 11,  'stok_kubikasi' => 1.9487, 'hpp_average' => 1294776],
        ['id_lahan' => 10, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 20,  'stok_kubikasi' => 2.6000, 'hpp_average' => 1294776],
        ['id_lahan' => 28, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 11,  'stok_kubikasi' => 1.9487, 'hpp_average' => 1294776],
        ['id_lahan' => 29, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 11,  'stok_kubikasi' => 1.9487, 'hpp_average' => 1294776],
        ['id_lahan' => 30, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 22,  'stok_kubikasi' => 2.8600, 'hpp_average' => 1294776],
        ['id_lahan' => 31, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 11,  'stok_kubikasi' => 1.4300, 'hpp_average' => 1294776],
        ['id_lahan' => 32, 'id_jenis_kayu' => 1, 'panjang' => 260, 'stok_batang' => 14,  'stok_kubikasi' => 1.8200, 'hpp_average' => 1294776],
    ];

    public function run(): void
    {
        $tanggalSaldo = '2026-03-06'; // sesuai screenshot

        foreach (self::DATA as $row) {
            // Validasi FK ada di DB
            $lahanExists      = DB::table('lahans')->where('id', $row['id_lahan'])->exists();
            $jenisKayuExists  = DB::table('jenis_kayus')->where('id', $row['id_jenis_kayu'])->exists();

            if (!$lahanExists) {
                $this->command->warn("SKIP: id_lahan={$row['id_lahan']} tidak ditemukan di tabel lahans.");
                continue;
            }
            if (!$jenisKayuExists) {
                $this->command->warn("SKIP: id_jenis_kayu={$row['id_jenis_kayu']} tidak ditemukan di tabel jenis_kayus.");
                continue;
            }

            $nilaiStok = round($row['hpp_average'] * $row['stok_kubikasi'], 2);

            // Catat log masuk (saldo awal)
            $log = HppAverageLog::create([
                'id_jenis_kayu'        => $row['id_jenis_kayu'],
                'grade'                => null,
                'panjang'              => $row['panjang'],
                'tanggal'              => $tanggalSaldo,
                'tipe_transaksi'       => 'masuk',
                'keterangan'           => "Saldo awal seeder - Lahan #{$row['id_lahan']} p{$row['panjang']}cm",
                'referensi_type'       => null,
                'referensi_id'         => null,
                'total_batang'         => $row['stok_batang'],
                'total_kubikasi'       => $row['stok_kubikasi'],
                'harga'                => $row['hpp_average'],
                'nilai_stok'           => $nilaiStok,
                'stok_batang_before'   => 0,
                'stok_kubikasi_before' => 0,
                'nilai_stok_before'    => 0,
                'stok_batang_after'    => $row['stok_batang'],
                'stok_kubikasi_after'  => $row['stok_kubikasi'],
                'nilai_stok_after'     => $nilaiStok,
                'hpp_average'          => $row['hpp_average'],
            ]);

            // Upsert summarie (grade null, panjang sesuai)
            HppAverageSummarie::updateOrCreate(
                [
                    'id_lahan'      => $row['id_lahan'],
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'grade'         => null,
                    'panjang'       => $row['panjang'],
                ],
                [
                    'stok_batang'   => $row['stok_batang'],
                    'stok_kubikasi' => $row['stok_kubikasi'],
                    'nilai_stok'    => $nilaiStok,
                    'hpp_average'   => $row['hpp_average'],
                    'id_last_log'   => $log->id,
                ]
            );

            $this->command->info("OK  lahan#{$row['id_lahan']} | p{$row['panjang']}cm | {$row['stok_batang']} btg | {$row['stok_kubikasi']} m³ | hpp " . number_format($row['hpp_average']));
        }

        $this->command->newLine();
        $this->command->info('Selesai. ' . count(self::DATA) . ' kombinasi diproses.');
        $this->command->warn('Pastikan id_lahan dan id_jenis_kayu sesuai DB Anda!');
    }
}