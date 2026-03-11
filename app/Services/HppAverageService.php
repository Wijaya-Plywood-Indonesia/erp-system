<?php

namespace App\Services;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\JenisKayu;
use App\Models\NotaKayu;
use App\Models\HargaKayu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HppAverageService
{
    // =========================================================================
    // HELPER: Map grade integer → string
    // =========================================================================

    private function mapGrade(int $grade): string
    {
        return match ($grade) {
            1       => 'A',
            2       => 'B',
            default => 'C',
        };
    }

    // =========================================================================
    // HELPER: Ambil harga beli dari tabel harga_kayus
    // =========================================================================

    private function getHargaBeli(
        int   $jenisKayuId,
        int   $gradeInt,
        int   $panjang,
        float $diameter
    ): float {
        $harga = HargaKayu::where('id_jenis_kayu',     $jenisKayuId)
            ->where('grade',             $gradeInt)
            ->where('panjang',           $panjang)
            ->where('diameter_terkecil', '<=', $diameter)
            ->where('diameter_terbesar', '>=', $diameter)
            ->value('harga_beli');

        return (float) ($harga ?? 0);
    }

    // =========================================================================
    // PUBLIC — PROSES NOTA KAYU MASUK
    // =========================================================================

    public function prosesNotaKayuMasuk(NotaKayu $nota): void
    {
        $kayuMasuk = $nota->kayuMasuk;
        if (! $kayuMasuk) return;

        $tanggal    = $kayuMasuk->tgl_kayu_masuk->format('Y-m-d');
        $lahanId    = $kayuMasuk->lahan_id ?? $nota->lahan_id;
        $keterangan = "Nota #{$nota->no_nota} · KayuMasuk #{$kayuMasuk->id}";

        DB::transaction(function () use ($nota, $kayuMasuk, $tanggal, $lahanId, $keterangan) {

            // Kelompokkan per jenis_kayu + panjang (grade diabaikan — digabung)
            $grouped = $kayuMasuk->detailTurusanKayus
                ->groupBy(fn($d) => $d->jenis_kayu_id . '_' . $d->panjang);

            foreach ($grouped as $details) {
                $jenisKayuId   = $details->first()->jenis_kayu_id;
                $panjang       = (int) $details->first()->panjang;

                // Hitung total gabungan semua grade dalam satu nota
                $totalBatang   = (int) $details->sum('kuantitas');
                $totalKubikasi = (float) round($details->sum('kubikasi'), 4);
                $totalNilai    = (float) round(
                    $details->sum(
                        fn($d) =>
                        $d->kubikasi * $this->getHargaBeli(
                            $jenisKayuId,
                            (int) $d->grade,
                            $panjang,
                            (float) $d->diameter
                        ) * 1000
                    ),
                    2
                );

                if ($totalBatang === 0) continue;

                $this->catatTransaksiMasuk(
                    lahanId: $lahanId,
                    jenisKayuId: $jenisKayuId,
                    panjang: $panjang,
                    tanggal: $tanggal,
                    totalBatang: $totalBatang,
                    totalKubikasi: $totalKubikasi,
                    totalNilai: $totalNilai,
                    keterangan: $keterangan,
                    referensi: $nota,
                );
            }
        });
    }

    // =========================================================================
    // PRIVATE — CATAT TRANSAKSI MASUK (grade = null)
    // =========================================================================

    private function catatTransaksiMasuk(
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

        // Snapshot dari log terakhir kombinasi ini (lintas semua lahan)
        $lastLog = HppAverageLog::where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->whereNull('grade')          // hanya log gabungan
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();

        $before = [
            'btg' => (int)   ($lastLog?->stok_batang_after  ?? 0),
            'm3'  => (float) ($lastLog?->stok_kubikasi_after ?? 0),
            'val' => (float) ($lastLog?->nilai_stok_after    ?? 0),
        ];

        $after = [
            'btg' => $before['btg'] + $totalBatang,
            'm3'  => round($before['m3'] + $totalKubikasi, 4),
            'val' => round($before['val'] + $totalNilai,   2),
        ];

        // HPP Average = total nilai stok / total kubikasi (A+B gabungan)
        $hppAverage = $after['m3'] > 0
            ? round($after['val'] / $after['m3'], 2)
            : 0;

        $log = HppAverageLog::create([
            'id_jenis_kayu'        => $jenisKayuId,
            'grade'                => null,           // null = log gabungan A+B
            'panjang'              => $panjang,
            'tanggal'              => $tanggal,
            'tipe_transaksi'       => 'masuk',
            'keterangan'           => $keterangan,
            'referensi_id'         => $referensi?->id,
            'referensi_type'       => $referensi ? get_class($referensi) : null,
            'total_batang'         => $totalBatang,
            'total_kubikasi'       => $totalKubikasi,
            'harga'                => $after['m3'] > 0 ? round($totalNilai / $totalKubikasi, 2) : 0,
            'nilai_stok'           => $totalNilai,
            'stok_batang_before'   => $before['btg'],
            'stok_kubikasi_before' => $before['m3'],
            'nilai_stok_before'    => $before['val'],
            'stok_batang_after'    => $after['btg'],
            'stok_kubikasi_after'  => $after['m3'],
            'nilai_stok_after'     => $after['val'],
            'hpp_average'          => $hppAverage,
        ]);

        // Update summary per lahan (grade = null)
        $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);
        if (! $summary) {
            Log::warning("catatTransaksiMasuk — skip, jenis_kayu_id={$jenisKayuId} tidak ada");
            return $log;
        }

        $summary->update([
            'stok_batang'   => $summary->stok_batang   + $totalBatang,
            'stok_kubikasi' => round($summary->stok_kubikasi + $totalKubikasi, 4),
            'nilai_stok'    => round($summary->nilai_stok    + $totalNilai,    2),
            'hpp_average'   => $hppAverage,   // HPP global, sama untuk semua lahan
            'id_last_log'   => $log->id,
        ]);

        return $log;
    }

    // =========================================================================
    // PUBLIC — CATAT TRANSAKSI KELUAR
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

        $lastLog = HppAverageLog::where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->whereNull('grade')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();

        $hppAverage  = (float) ($lastLog?->hpp_average ?? 0);
        $nilaiKeluar = round($totalKubikasi * $hppAverage, 2);

        $before = [
            'btg' => (int)   ($lastLog?->stok_batang_after  ?? 0),
            'm3'  => (float) ($lastLog?->stok_kubikasi_after ?? 0),
            'val' => (float) ($lastLog?->nilai_stok_after    ?? 0),
        ];

        $after = [
            'btg' => max(0, $before['btg'] - $totalBatang),
            'm3'  => max(0.0, round($before['m3'] - $totalKubikasi, 4)),
            'val' => max(0.0, round($before['val'] - $nilaiKeluar,  2)),
        ];

        $log = HppAverageLog::create([
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

        $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);
        if ($summary) {
            $summary->update([
                'stok_batang'   => max(0, $summary->stok_batang   - $totalBatang),
                'stok_kubikasi' => max(0, round($summary->stok_kubikasi - $totalKubikasi, 4)),
                'nilai_stok'    => max(0, round($summary->nilai_stok    - $nilaiKeluar,   2)),
                'hpp_average'   => $hppAverage,
                'id_last_log'   => $log->id,
            ]);
        }

        return $log;
    }

    // =========================================================================
    // PUBLIC — SEED DATA HISTORIS
    // =========================================================================

    public function seedFromNotaKayu(): void
    {
        Log::info('HppAverageService::seedFromNotaKayu — mulai seed');

        // Hapus hanya log gabungan (grade null), bukan log per-grade lama jika ada
        HppAverageLog::whereNull('grade')->delete();
        HppAverageSummarie::query()->update([
            'stok_batang'   => 0,
            'stok_kubikasi' => 0,
            'nilai_stok'    => 0,
            'hpp_average'   => 0,
            'id_last_log'   => null,
        ]);

        $processed = 0;
        $skipped   = 0;

        NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->whereHas('kayuMasuk')
            ->join('kayu_masuks', 'nota_kayus.id_kayu_masuk', '=', 'kayu_masuks.id')
            ->orderBy('kayu_masuks.tgl_kayu_masuk')
            ->orderBy('nota_kayus.id')
            ->select('nota_kayus.*')
            ->each(function (NotaKayu $nota) use (&$processed, &$skipped) {
                try {
                    $this->prosesNotaKayuMasuk($nota);
                    $processed++;
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning("seedFromNotaKayu — nota #{$nota->id} diskip: {$e->getMessage()}");
                }
            });

        Log::info("seedFromNotaKayu — selesai. Diproses: {$processed}, Diskip: {$skipped}");
    }

    // =========================================================================
    // PUBLIC — HITUNG ULANG SEMUA SNAPSHOT
    // =========================================================================

    public function recalculateAll(): void
    {
        Log::info('HppAverageService::recalculateAll — mulai');

        $logs = HppAverageLog::whereNull('grade')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        // Running state per kombinasi jenis+panjang
        $state = [];

        foreach ($logs as $log) {
            $key = $log->id_jenis_kayu . '_' . $log->panjang;

            if (! isset($state[$key])) {
                $state[$key] = ['btg' => 0, 'm3' => 0.0, 'val' => 0.0];
            }

            $before  = $state[$key];
            $isMasuk = $log->tipe_transaksi === 'masuk';

            if ($isMasuk) {
                $after = [
                    'btg' => $before['btg'] + $log->total_batang,
                    'm3'  => round($before['m3'] + $log->total_kubikasi, 4),
                    'val' => round($before['val'] + $log->nilai_stok,    2),
                ];
            } else {
                $hppSaatItu  = $before['m3'] > 0 ? $before['val'] / $before['m3'] : 0;
                $nilaiKeluar = round($log->total_kubikasi * $hppSaatItu, 2);
                $after = [
                    'btg' => max(0, $before['btg'] - $log->total_batang),
                    'm3'  => max(0.0, round($before['m3'] - $log->total_kubikasi, 4)),
                    'val' => max(0.0, round($before['val'] - $nilaiKeluar,        2)),
                ];
            }

            $hppAverage = $after['m3'] > 0
                ? round($after['val'] / $after['m3'], 2)
                : 0;

            $log->update([
                'stok_batang_before'   => $before['btg'],
                'stok_kubikasi_before' => $before['m3'],
                'nilai_stok_before'    => $before['val'],
                'stok_batang_after'    => $after['btg'],
                'stok_kubikasi_after'  => $after['m3'],
                'nilai_stok_after'     => $after['val'],
                'hpp_average'          => $hppAverage,
            ]);

            $state[$key] = $after;
        }

        // Sync hpp_average ke semua summary dari log terakhir globalnya
        HppAverageSummarie::each(function (HppAverageSummarie $summary) {
            $lastLog = HppAverageLog::where('id_jenis_kayu', $summary->id_jenis_kayu)
                ->where('panjang', $summary->panjang)
                ->whereNull('grade')
                ->orderByDesc('tanggal')
                ->orderByDesc('id')
                ->first();

            if ($lastLog) {
                $summary->update([
                    'hpp_average' => $lastLog->hpp_average,
                    'id_last_log' => $lastLog->id,
                ]);
            }
        });

        Log::info('HppAverageService::recalculateAll — selesai');
    }
}
