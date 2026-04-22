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
 *   - NOTA LUNAS: Mencatat LOG HPP (untuk audit trail)
 *   - Mencegah duplikasi dengan unique constraint
 *   - Stok Opname akan menggunakan mekanisme terpisah
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
    // PROSES NOTA KAYU LUNAS (DENGAN LOG HPP)
    // Dipanggil dari observer saat status_pelunasan berubah ke "Lunas"
    // =========================================================================

    public function prosesNotaKayuLunas(NotaKayu $nota): void
    {
        Log::info('[HPP] prosesNotaKayuLunas mulai (DENGAN LOG HPP)', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        // ✅ CEK APAKAH SUDAH PERNAH DIPROSES (CEGAH DUPLIKAT)
        $existingLog = HppAverageLog::where('referensi_type', NotaKayu::class)
            ->where('referensi_id', $nota->id)
            ->exists();

        if ($existingLog) {
            Log::warning('[HPP] SKIP - Nota sudah pernah diproses', ['nota_id' => $nota->id]);
            return;
        }

        $kayuMasuk = $nota->kayuMasuk;

        if (! $kayuMasuk) {
            Log::warning('[HPP] SKIP — kayuMasuk null', ['nota_id' => $nota->id]);
            return;
        }

        $kayuMasuk->loadMissing(['detailTurusanKayus']);
        $details = $kayuMasuk->detailTurusanKayus;

        if ($details->isEmpty()) {
            Log::warning('[HPP] SKIP — detail kosong', [
                'nota_id'       => $nota->id,
                'kayu_masuk_id' => $kayuMasuk->id,
            ]);
            return;
        }

        DB::transaction(function () use ($nota, $kayuMasuk, $details) {
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

                if ($totalBatang === 0 || $lahanId === 0 || $jenisKayuId === 0) {
                    Log::warning('[HPP] kombinasi SKIP — data tidak lengkap', [
                        'nota_id' => $nota->id,
                        'key'     => $key,
                    ]);
                    continue;
                }

                // ✅ Ambil snapshot BEFORE
                $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);

                if (!$summary) {
                    Log::error('[HPP] summary NULL — kombinasi tidak ditemukan', [
                        'lahan_id'      => $lahanId,
                        'jenis_kayu_id' => $jenisKayuId,
                        'panjang'       => $panjang,
                    ]);
                    continue;
                }

                $before = [
                    'btg' => $summary->stok_batang,
                    'm3'  => $summary->stok_kubikasi,
                    'val' => $summary->nilai_stok,
                ];

                // ✅ UPDATE SUMMARY
                $summary->tambahStok($totalBatang, $totalKubikasi, $totalNilai);

                $after = [
                    'btg' => $summary->stok_batang,
                    'm3'  => $summary->stok_kubikasi,
                    'val' => $summary->nilai_stok,
                ];

                // ✅ BUAT LOG HPP
                $log = HppAverageLog::create([
                    'id_lahan'             => $lahanId,
                    'id_jenis_kayu'        => $jenisKayuId,
                    'grade'                => null,
                    'panjang'              => $panjang,
                    'tanggal'              => $kayuMasuk->tgl_kayu_masuk ?? now()->format('Y-m-d'),
                    'tipe_transaksi'       => 'masuk',
                    'keterangan'           => "Nota #{$nota->no_nota}",
                    'referensi_type'       => NotaKayu::class,
                    'referensi_id'         => $nota->id,
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

                $summary->update(['id_last_log' => $log->id]);
                $lahanTerpengaruh->push($lahanId);

                Log::info('[HPP] kombinasi diupdate (DENGAN LOG)', [
                    'log_id'         => $log->id,
                    'lahan_id'       => $lahanId,
                    'jenis_kayu_id'  => $jenisKayuId,
                    'panjang'        => $panjang,
                    'total_batang'   => $totalBatang,
                    'before_batang'  => $before['btg'],
                    'after_batang'   => $after['btg'],
                ]);
            }

            // Sync TempatKayu
            $lahanTerpengaruh->unique()->each(function (int $lahanId) {
                $this->syncTempatKayuByLahan($lahanId);
            });
        });

        Log::info('[HPP] prosesNotaKayuLunas selesai (DENGAN LOG HPP)', ['nota_id' => $nota->id]);
    }

    // =========================================================================
    // SYNC TEMPAT KAYU
    // =========================================================================

    public function syncTempatKayuByLahan(int $lahanId): void
    {
        $totalBatang = HppAverageSummarie::where('id_lahan', $lahanId)
            ->whereNull('grade')
            ->sum('stok_batang');

        $kayuMasuk = \App\Models\KayuMasuk::whereHas('detailTurusanKayus', function ($q) use ($lahanId) {
            $q->where('lahan_id', $lahanId);
        })->latest()->first();

        if ($kayuMasuk) {
            \App\Models\TempatKayu::updateOrCreate(
                [
                    'id_lahan' => $lahanId,
                    'id_kayu_masuk' => $kayuMasuk->id,
                ],
                ['jumlah_batang' => $totalBatang]
            );
        }
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
        $summary = HppAverageSummarie::where('id_lahan', $lahanId)
            ->where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->first();

        if (!$summary) {
            throw new \Exception("Stok tidak ditemukan untuk lahan, jenis, dan ukuran ini.");
        }

        // ✅ Validasi stok mencukupi
        if ($summary->stok_batang < $totalBatang) {
            throw new \Exception("Stok tidak mencukupi. Tersedia: {$summary->stok_batang}, Diminta: {$totalBatang}");
        }

        $hppAverage  = (float) ($summary->hpp_average ?? 0);
        $nilaiKeluar = round($totalKubikasi * $hppAverage, 2);

        $before = [
            'btg' => (int)   $summary->stok_batang,
            'm3'  => (float) $summary->stok_kubikasi,
            'val' => (float) $summary->nilai_stok,
        ];

        $after = [
            'btg' => max(0, $before['btg'] - $totalBatang),
            'm3'  => max(0.0, round($before['m3'] - $totalKubikasi, 4)),
            'val' => max(0.0, round($before['val'] - $nilaiKeluar, 2)),
        ];

        return DB::transaction(function () use ($lahanId, $jenisKayuId, $panjang, $tanggal, $totalBatang, $totalKubikasi, $keterangan, $referensi, $hppAverage, $nilaiKeluar, $before, $after, $summary) {
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

            $summary->kurangiStok($totalBatang, $totalKubikasi, $nilaiKeluar, $log->id);
            $this->syncTempatKayuByLahan($lahanId);

            return $log;
        });
    }

    // =========================================================================
    // RECALCULATE (BERDASARKAN LOG YANG ADA)
    // =========================================================================

    public function recalculateAll(): void
    {
        DB::transaction(function () {
            // Reset semua summary
            HppAverageSummarie::whereNull('grade')->update([
                'stok_batang' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0,
                'id_last_log' => null,
            ]);

            // Proses ulang semua log yang ADA
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

                $summary = HppAverageSummarie::updateOrCreate(
                    [
                        'id_lahan'      => $log->id_lahan,
                        'id_jenis_kayu' => $log->id_jenis_kayu,
                        'panjang'       => $log->panjang,
                        'grade'         => null,
                    ],
                    [
                        'stok_batang'   => $current['btg'],
                        'stok_kubikasi' => $current['m3'],
                        'nilai_stok'    => $current['val'],
                        'hpp_average'   => $current['hpp'],
                        'id_last_log'   => $log->id,
                    ]
                );
            }

            // Sync semua TempatKayu
            $allLahan = HppAverageSummarie::where('stok_batang', '>', 0)
                ->distinct()
                ->pluck('id_lahan');

            foreach ($allLahan as $lahanId) {
                $this->syncTempatKayuByLahan($lahanId);
            }
        });
    }
}
