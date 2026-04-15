<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use Illuminate\Support\Facades\DB;

class RecalculateHppLahan extends Command
{
    /**
     * Signature: Cara memanggil command di terminal.
     * Contoh: php artisan hpp:recalculate 26
     */
    protected $signature = 'hpp:recalculate {id_lahan : ID Lahan yang akan dihitung ulang}';

    /**
     * Deskripsi yang muncul saat menjalankan php artisan list.
     */
    protected $description = 'Menghitung ulang urutan Moving Average HPP pada lahan tertentu secara kronologis';

    /**
     * Eksekusi Command.
     */
    public function handle()
    {
        // 1. Ambil ID Lahan dari argumen terminal
        $idLahan = $this->argument('id_lahan');

        // 2. Cek apakah ada log untuk lahan tersebut
        $logs = HppAverageLog::where('id_lahan', $idLahan)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($logs->isEmpty()) {
            $this->error("Tidak ditemukan data log untuk Lahan ID: {$idLahan}");
            return;
        }

        $this->info("Memulai perhitungan ulang untuk Lahan ID: {$idLahan}");
        $this->info("Ditemukan " . $logs->count() . " baris transaksi.");

        // 3. Jalankan dalam Database Transaction agar aman
        DB::transaction(function () use ($idLahan, $logs) {
            $runningBatang = 0;
            $runningKubikasi = 0;
            $runningNilai = 0;
            $runningHpp = 0;

            // Inisialisasi Progress Bar di terminal
            $bar = $this->output->createProgressBar($logs->count());
            $bar->start();

            foreach ($logs as $log) {
                // Set saldo BEFORE
                $log->stok_batang_before = $runningBatang;
                $log->stok_kubikasi_before = round($runningKubikasi, 4);
                $log->nilai_stok_before = round($runningNilai, 2);

                if ($log->tipe_transaksi === 'masuk') {
                    // Jika MASUK: Tambah stok dan nilai
                    $runningBatang += $log->total_batang;
                    $runningKubikasi += $log->total_kubikasi;
                    $runningNilai += $log->nilai_stok;

                    // Hitung HPP Average Baru (Moving Average)
                    $runningHpp = $runningKubikasi > 0 ? round($runningNilai / $runningKubikasi, 2) : 0;
                    $log->hpp_average = $runningHpp;
                } else {
                    // Jika KELUAR: Kurangi stok menggunakan HPP berjalan saat ini
                    // Nilai keluar dihitung ulang agar sesuai HPP rata-rata terbaru
                    $log->nilai_stok = round($runningHpp * $log->total_kubikasi, 2);
                    $log->hpp_average = $runningHpp;

                    $runningBatang -= $log->total_batang;
                    $runningKubikasi -= $log->total_kubikasi;
                    $runningNilai -= $log->nilai_stok;
                }

                // Set saldo AFTER
                $log->stok_batang_after = $runningBatang;
                $log->stok_kubikasi_after = round($runningKubikasi, 4);
                $log->nilai_stok_after = round($runningNilai, 2);

                $log->save();
                $bar->advance();
            }

            // 4. Update Tabel Summary agar sinkron dengan baris terakhir log
            HppAverageSummarie::where('id_lahan', $idLahan)->update([
                'stok_batang'   => $runningBatang,
                'stok_kubikasi' => round($runningKubikasi, 4),
                'nilai_stok'    => round($runningNilai, 2),
                'hpp_average'   => $runningHpp,
                'id_last_log'   => $logs->last()?->id,
            ]);

            $bar->finish();
            $this->newLine();
            $this->info("Berhasil! Lahan ID {$idLahan} telah disinkronkan.");
            $this->table(
                ['Kategori', 'Nilai Akhir'],
                [
                    ['Total Batang', $runningBatang],
                    ['Total Kubikasi', round($runningKubikasi, 4) . ' m3'],
                    ['HPP Average', 'Rp ' . number_format($runningHpp, 2)],
                    ['Total Nilai Stok', 'Rp ' . number_format($runningNilai, 2)],
                ]
            );
        });
    }
}
