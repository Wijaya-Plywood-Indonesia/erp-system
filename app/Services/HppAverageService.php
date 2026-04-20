<?php

namespace App\Services;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\NotaKayu;
use App\Models\HargaKayu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HppAverageService
 *
 * DESAIN:
 *   - HPP Average dihitung GLOBAL per (jenis_kayu + panjang) — lintas lahan
 *   - Stok summary dihitung PER LAHAN per (lahan + jenis_kayu + panjang)
 *   - Grade diabaikan di level HPP (grade = null di log & summary)
 *
 * PERUBAHAN:
 *   - NOTA LUNAS: Langsung update ke HppAverageSummarie & TempatKayu (TANPA LOG)
 *   - Log HPP hanya untuk keperluan histori MANUAL (bukan dari nota otomatis)
 */
class HppAverageService
{
    // =========================================================================
    // HELPERS PRIVATE
    // =========================================================================

    /**
     * Hitung kubikasi per baris — dibulatkan 4 desimal per baris
     * agar konsisten dengan nota cetak.
     *   round((panjang × diameter² × kuantitas × 0.785) / 1_000_000, 4)
     */
    private function hitungKubikasi(float $panjang, float $diameter, float $kuantitas): float
    {
        return round(
            ($panjang * $diameter * $diameter * $kuantitas * 0.785) / 1_000_000,
            4
        );
    }

    /**
     * Ambil harga beli dari tabel harga_kayus.
     * Grade disimpan sebagai integer di harga_kayus (1=A, 2=B, 3=C).
     */
    private function getHargaBeli(int $jenisKayuId, int $gradeInt, int $panjang, float $diameter): float
    {
        $harga = HargaKayu::where('id_jenis_kayu', $jenisKayuId)
            ->where('grade',             $gradeInt)
            ->where('panjang',           $panjang)
            ->where('diameter_terkecil', '<=', $diameter)
            ->where('diameter_terbesar', '>=', $diameter)
            ->value('harga_beli');

        if (! $harga) {
            Log::warning('[HPP] getHargaBeli TIDAK DITEMUKAN', [
                'id_jenis_kayu' => $jenisKayuId,
                'grade_int'     => $gradeInt,
                'panjang'       => $panjang,
                'diameter'      => $diameter,
            ]);
        }

        return (float) ($harga ?? 0);
    }

    // =========================================================================
    // PROSES NOTA KAYU LUNAS (TANPA LOG HPP)
    // Dipanggil dari observer saat status_pelunasan berubah ke "Lunas"
    // =========================================================================

    public function prosesNotaKayuLunas(NotaKayu $nota): void
    {
        Log::info('[STOK] prosesNotaKayuLunas mulai (TANPA LOG HPP)', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        $kayuMasuk = $nota->kayuMasuk;

        if (! $kayuMasuk) {
            Log::warning('[STOK] SKIP — kayuMasuk null', ['nota_id' => $nota->id]);
            return;
        }

        $kayuMasuk->loadMissing(['detailTurusanKayus']);
        $details = $kayuMasuk->detailTurusanKayus;

        if ($details->isEmpty()) {
            Log::warning('[STOK] SKIP — detail kosong', [
                'nota_id'       => $nota->id,
                'kayu_masuk_id' => $kayuMasuk->id,
            ]);
            return;
        }

        DB::transaction(function () use ($nota, $details) {
            // Grouping: per lahan + jenis_kayu + panjang
            $grouped = $details->groupBy(
                fn($d) => "{$d->lahan_id}_{$d->jenis_kayu_id}_{$d->panjang}"
            );

            $lahanTerpengaruh = collect();

            foreach ($grouped as $key => $rows) {
                $lahanId     = (int) $rows->first()->lahan_id;
                $jenisKayuId = (int) $rows->first()->jenis_kayu_id;
                $panjang     = (int) $rows->first()->panjang;

                $totalBatang = (int) $rows->sum('kuantitas');

                // Hitung kubikasi
                $totalKubikasi = (float) $rows->sum(fn($d) => $this->hitungKubikasi(
                    (float) $d->panjang,
                    (float) $d->diameter,
                    (float) $d->kuantitas
                ));

                // Hitung nilai stok
                $totalNilai = (float) round(
                    $rows->sum(function ($d) use ($jenisKayuId, $panjang) {
                        $kub   = $this->hitungKubikasi(
                            (float) $d->panjang,
                            (float) $d->diameter,
                            (float) $d->kuantitas
                        );
                        $harga = $this->getHargaBeli(
                            $jenisKayuId,
                            (int) $d->grade,
                            $panjang,
                            (float) $d->diameter
                        );
                        return $kub * $harga * 1000;
                    }),
                    2
                );

                if ($totalBatang === 0 || $lahanId === 0 || $jenisKayuId === 0) {
                    Log::warning('[STOK] kombinasi SKIP — data tidak lengkap', [
                        'nota_id' => $nota->id,
                        'key'     => $key,
                    ]);
                    continue;
                }

                // UPDATE LANGSUNG KE SUMMARY (TANPA LOG)
                $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);

                if (!$summary) {
                    Log::error('[STOK] summary NULL — kombinasi tidak ditemukan', [
                        'lahan_id'      => $lahanId,
                        'jenis_kayu_id' => $jenisKayuId,
                        'panjang'       => $panjang,
                    ]);
                    continue;
                }

                // Gunakan method tambahStok dari model
                $summary->tambahStok($totalBatang, $totalKubikasi, $totalNilai);

                $lahanTerpengaruh->push($lahanId);

                Log::info('[STOK] kombinasi diupdate (TANPA LOG)', [
                    'lahan_id'       => $lahanId,
                    'jenis_kayu_id'  => $jenisKayuId,
                    'panjang'        => $panjang,
                    'total_batang'   => $totalBatang,
                    'total_kubikasi' => $totalKubikasi,
                    'total_nilai'    => $totalNilai,
                ]);
            }

            // Sync TempatKayu (method syncTempatKayu sudah otomatis dipanggil di model)
            // Tapi kita panggil sekali lagi untuk memastikan
            $lahanTerpengaruh->unique()->each(function (int $lahanId) {
                $summary = HppAverageSummarie::where('id_lahan', $lahanId)->first();
                if ($summary) {
                    $summary->syncTempatKayu();
                }
            });
        });

        Log::info('[STOK] prosesNotaKayuLunas selesai (TANPA LOG HPP)', ['nota_id' => $nota->id]);
    }

    // =========================================================================
    // PROSES TRANSAKSI KELUAR (MANUAL) - DENGAN LOG
    // =========================================================================

    public function catatTransaksiKeluar(
        int    $lahanId,
        int    $jenisKayuId,
        int    $panjang,
        string $tanggal,
        int    $totalBatang,
        float  $totalKubikasi,
        string $keterangan = '',
        mixed  $referensi  = null,
    ): HppAverageLog {
        // 1. Ambil Summary KHUSUS Lahan tersebut
        $summary = HppAverageSummarie::where('id_lahan', $lahanId)
            ->where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->first();

        if (!$summary) {
            throw new \Exception("Stok tidak ditemukan untuk lahan, jenis, dan ukuran ini.");
        }

        // 2. Ambil HPP dan Saldo KHUSUS Lahan tersebut
        $hppAverage  = (float) ($summary->hpp_average ?? 0);
        $nilaiKeluar = round($totalKubikasi * $hppAverage, 2);

        $before = [
            'btg' => (int)   $summary->stok_batang,
            'm3'  => (float) $summary->stok_kubikasi,
            'val' => (float) $summary->nilai_stok,
        ];

        // 3. Hitung Saldo Sesudah
        $after = [
            'btg' => max(0, $before['btg'] - $totalBatang),
            'm3'  => max(0.0, round($before['m3'] - $totalKubikasi, 4)),
            'val' => max(0.0, round($before['val'] - $nilaiKeluar, 2)),
        ];

        return DB::transaction(function () use ($lahanId, $jenisKayuId, $panjang, $tanggal, $totalBatang, $totalKubikasi, $keterangan, $referensi, $hppAverage, $nilaiKeluar, $before, $after, $summary) {
            // 4. Catat Log dengan snapshot
            $log = HppAverageLog::create([
                'id_lahan'             => $lahanId,
                'id_jenis_kayu'        => $jenisKayuId,
                'grade'                => null,
                'panjang'              => $panjang,
                'tanggal'              => $tanggal,
                'tipe_transaksi'       => 'keluar',
                'keterangan'           => $keterangan,
                'referensi_id'         => $referensi?->id,
                'referensi_type'       => $referensi ? get_class($referensi) : null,
                'total_batang'         => $totalBatang,
                'total_kubikasi'       => $totalKubikasi,
                'harga'                => $hppAverage,
                'nilai_stok'           => $nilaiKeluar,
                'stok_batang_before'   => $before['btg'],
                'stok_kubikasi_before' => $before['m3'],
                'nilai_stok_before'    => $before['val'],
                'stok_batang_after'    => $after['btg'],
                'stok_kubikasi_after'  => $after['m3'],
                'nilai_stok_after'     => $after['val'],
                'hpp_average'          => $hppAverage,
            ]);

            // 5. Update Summary
            $summary->kurangiStok($totalBatang, $totalKubikasi, $nilaiKeluar, $log->id);

            return $log;
        });
    }

    // =========================================================================
    // ROLLBACK NOTA KAYU (jika nota dihapus setelah Lunas)
    // =========================================================================

    public function rollbackNotaKayuLunas(NotaKayu $nota): void
    {
        Log::info('[STOK] rollbackNotaKayuLunas mulai', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        // NOTE: Karena nota Lunas TIDAK membuat log HPP,
        // kita perlu rollback berdasarkan data dari nota itu sendiri

        $kayuMasuk = $nota->kayuMasuk;

        if (!$kayuMasuk) {
            Log::warning('[STOK] rollbackNotaKayuLunas SKIP — kayuMasuk null', ['nota_id' => $nota->id]);
            return;
        }

        $kayuMasuk->loadMissing(['detailTurusanKayus']);
        $details = $kayuMasuk->detailTurusanKayus;

        if ($details->isEmpty()) {
            Log::warning('[STOK] rollbackNotaKayuLunas SKIP — detail kosong', ['nota_id' => $nota->id]);
            return;
        }

        DB::transaction(function () use ($details) {
            $grouped = $details->groupBy(
                fn($d) => "{$d->lahan_id}_{$d->jenis_kayu_id}_{$d->panjang}"
            );

            $lahanTerpengaruh = collect();

            foreach ($grouped as $rows) {
                $lahanId     = (int) $rows->first()->lahan_id;
                $jenisKayuId = (int) $rows->first()->jenis_kayu_id;
                $panjang     = (int) $rows->first()->panjang;
                $totalBatang = (int) $rows->sum('kuantitas');

                $totalKubikasi = (float) $rows->sum(fn($d) => $this->hitungKubikasi(
                    (float) $d->panjang,
                    (float) $d->diameter,
                    (float) $d->kuantitas
                ));

                $totalNilai = (float) round(
                    $rows->sum(function ($d) use ($jenisKayuId, $panjang) {
                        $kub   = $this->hitungKubikasi(
                            (float) $d->panjang,
                            (float) $d->diameter,
                            (float) $d->kuantitas
                        );
                        $harga = $this->getHargaBeli(
                            $jenisKayuId,
                            (int) $d->grade,
                            $panjang,
                            (float) $d->diameter
                        );
                        return $kub * $harga * 1000;
                    }),
                    2
                );

                $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);

                if ($summary) {
                    $summary->kurangiStok($totalBatang, $totalKubikasi, $totalNilai);
                    $lahanTerpengaruh->push($lahanId);
                }
            }

            // Sync TempatKayu
            $lahanTerpengaruh->unique()->each(function (int $lahanId) {
                $summary = HppAverageSummarie::where('id_lahan', $lahanId)->first();
                if ($summary) {
                    $summary->syncTempatKayu();
                }
            });
        });

        Log::info('[STOK] rollbackNotaKayuLunas selesai', ['nota_id' => $nota->id]);
    }

    // =========================================================================
    // MANUAL LOG HPP (UNTUK KEPERLUAN KHUSUS)
    // Method ini bisa digunakan jika ingin mencatat log secara manual
    // =========================================================================

    public function createManualLogMasuk(
        int    $lahanId,
        int    $jenisKayuId,
        int    $panjang,
        string $tanggal,
        int    $totalBatang,
        float  $totalKubikasi,
        float  $totalNilai,
        string $keterangan = '',
        mixed  $referensi  = null,
    ): HppAverageLog {
        $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);

        if (!$summary) {
            throw new \Exception("Kombinasi tidak ditemukan");
        }

        $before = [
            'btg' => $summary->stok_batang,
            'm3'  => $summary->stok_kubikasi,
            'val' => $summary->nilai_stok,
        ];

        $summary->tambahStok($totalBatang, $totalKubikasi, $totalNilai);

        $after = [
            'btg' => $summary->stok_batang,
            'm3'  => $summary->stok_kubikasi,
            'val' => $summary->nilai_stok,
        ];

        return HppAverageLog::create([
            'id_lahan'             => $lahanId,
            'id_jenis_kayu'        => $jenisKayuId,
            'grade'                => null,
            'panjang'              => $panjang,
            'tanggal'              => $tanggal,
            'tipe_transaksi'       => 'masuk',
            'keterangan'           => $keterangan,
            'referensi_id'         => $referensi?->id,
            'referensi_type'       => $referensi ? get_class($referensi) : null,
            'total_batang'         => $totalBatang,
            'total_kubikasi'       => $totalKubikasi,
            'harga'                => $totalKubikasi > 0 ? round($totalNilai / $totalKubikasi, 2) : 0,
            'nilai_stok'           => $totalNilai,
            'stok_batang_before'   => $before['btg'],
            'stok_kubikasi_before' => $before['m3'],
            'nilai_stok_before'    => $before['val'],
            'stok_batang_after'    => $after['btg'],
            'stok_kubikasi_after'  => $after['m3'],
            'nilai_stok_after'     => $after['val'],
            'hpp_average'          => $summary->hpp_average,
        ]);
    }

    // =========================================================================
    // SEED & RECALCULATE (BERDASARKAN LOG YANG ADA)
    // =========================================================================

    public function seedFromNotaKayu(): void
    {
        Log::info('[HPP] seedFromNotaKayu MULAI');

        // Reset semua data
        HppAverageLog::whereNull('grade')->delete();
        HppAverageSummarie::whereNull('grade')->update([
            'stok_batang'   => 0,
            'stok_kubikasi' => 0,
            'nilai_stok'    => 0,
            'hpp_average'   => 0,
            'id_last_log'   => null,
        ]);

        $processed = 0;
        $skipped   = 0;

        // Proses ulang semua nota yang sudah Lunas (TANPA membuat log baru)
        NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->whereHas('kayuMasuk')
            ->where('status_pelunasan', 'like', '%Lunas%')
            ->join('kayu_masuks', 'nota_kayus.id_kayu_masuk', '=', 'kayu_masuks.id')
            ->orderBy('kayu_masuks.tgl_kayu_masuk')
            ->orderBy('nota_kayus.id')
            ->select('nota_kayus.*')
            ->each(function (NotaKayu $nota) use (&$processed, &$skipped) {
                try {
                    $this->prosesNotaKayuLunas($nota);
                    $processed++;
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::error('[HPP] EXCEPTION pada nota', [
                        'nota_id' => $nota->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            });

        Log::info('[HPP] seedFromNotaKayu SELESAI', [
            'diproses' => $processed,
            'diskip'   => $skipped,
        ]);
    }

    public function recalculateAll(): void
    {
        DB::transaction(function () {
            // Reset semua summary
            HppAverageSummarie::query()->update([
                'stok_batang' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0
            ]);

            // Proses ulang semua log yang ADA (bukan dari nota)
            $logs = HppAverageLog::whereNull('grade')
                ->orderBy('tanggal')
                ->orderBy('id')
                ->get();

            $state = [];

            foreach ($logs as $log) {
                $key = "L{$log->id_lahan}_J{$log->id_jenis_kayu}_P{$log->panjang}";

                if (!isset($state[$key])) {
                    $state[$key] = ['btg' => 0, 'm3' => 0.0, 'val' => 0.0, 'hpp' => 0.0];
                }

                $current = &$state[$key];

                if ($log->tipe_transaksi === 'masuk') {
                    $current['btg'] += $log->total_batang;
                    $current['m3']  = round($current['m3'] + $log->total_kubikasi, 4);
                    $current['val'] = round($current['val'] + $log->nilai_stok, 2);
                    $current['hpp'] = $current['m3'] > 0 ? round($current['val'] / $current['m3'], 2) : 0;
                } else {
                    $current['btg'] = max(0, $current['btg'] - $log->total_batang);
                    $current['m3']  = max(0, round($current['m3'] - $log->total_kubikasi, 4));
                    $current['val'] = max(0, round($current['val'] - $log->nilai_stok, 2));
                }

                // Update summary
                $this->syncToSummary($log, $current);
            }

            // Sync semua TempatKayu setelah recalculate
            $allLahan = HppAverageSummarie::where('stok_batang', '>', 0)
                ->distinct()
                ->pluck('id_lahan');

            foreach ($allLahan as $lahanId) {
                $summary = HppAverageSummarie::where('id_lahan', $lahanId)->first();
                if ($summary) {
                    $summary->syncTempatKayu();
                }
            }
        });
    }

    private function syncToSummary($log, $currentState): void
    {
        HppAverageSummarie::updateOrCreate(
            [
                'id_lahan'      => $log->id_lahan,
                'id_jenis_kayu' => $log->id_jenis_kayu,
                'panjang'       => $log->panjang,
                'grade'         => null
            ],
            [
                'stok_batang'   => $currentState['btg'],
                'stok_kubikasi' => $currentState['m3'],
                'nilai_stok'    => $currentState['val'],
                'hpp_average'   => $currentState['hpp'],
                'id_last_log'   => $log->id
            ]
        );
    }
}
