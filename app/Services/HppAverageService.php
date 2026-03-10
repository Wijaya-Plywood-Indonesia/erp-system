<?php

namespace App\Services;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\NotaKayu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HppAverageService
{
    // =========================================================================
    // HELPERS
    // =========================================================================

    private function mapGrade(mixed $grade): string
    {
        return match ((int) $grade) {
            1       => 'A',
            2       => 'B',
            3       => 'C',
            default => strtoupper((string) $grade),
        };
    }

    /**
     * Ambil harga beli dari tabel harga_kayus.
     * Grade dikirim sebagai integer (raw dari detail_turusan_kayus).
     */
    private function getHargaBeli(mixed $item): float
    {
        $harga = \App\Models\HargaKayu::where('id_jenis_kayu',    $item->jenis_kayu_id)
            ->where('grade',             $item->grade)          // integer, jangan di-map
            ->where('panjang',           $item->panjang)
            ->where('diameter_terkecil', '<=', (float) $item->diameter)
            ->where('diameter_terbesar', '>=', (float) $item->diameter)
            ->orderBy('diameter_terkecil', 'desc')
            ->value('harga_beli');

        if (! $harga) {
            Log::warning('HppAverageService::getHargaBeli — harga tidak ditemukan', [
                'id_jenis_kayu' => $item->jenis_kayu_id,
                'grade'         => $item->grade,
                'panjang'       => $item->panjang,
                'diameter'      => $item->diameter,
            ]);
        }

        return (float) ($harga ?? 0);
    }

    // =========================================================================
    // PUBLIC — ENTRY POINT UTAMA
    // =========================================================================

    public function prosesNotaKayuMasuk(NotaKayu $nota): void
    {
        Log::info("HppAverageService::prosesNotaKayuMasuk — mulai proses NotaKayu #{$nota->id} ({$nota->no_nota})");

        $nota->loadMissing(['kayuMasuk.detailTurusanKayus']);

        $kayuMasuk = $nota->kayuMasuk;

        if (! $kayuMasuk) {
            Log::warning("HppAverageService: NotaKayu #{$nota->id} tidak memiliki KayuMasuk.");
            return;
        }

        Log::info("HppAverageService — KayuMasuk #{$kayuMasuk->id} ditemukan, tgl: {$kayuMasuk->tgl_kayu_masuk}");

        $details = $kayuMasuk->detailTurusanKayus;

        if ($details->isEmpty()) {
            Log::warning("HppAverageService: KayuMasuk #{$kayuMasuk->id} tidak memiliki DetailTurusanKayu.");
            return;
        }

        Log::info("HppAverageService — {$details->count()} detail ditemukan di KayuMasuk #{$kayuMasuk->id}");

        $tanggal = $kayuMasuk->tgl_kayu_masuk
            ? \Carbon\Carbon::parse($kayuMasuk->tgl_kayu_masuk)->toDateString()
            : now()->toDateString();

        // Group per kombinasi: lahan + jenis kayu + grade + panjang
        $grouped = $details->groupBy(function ($item) {
            $lahanId     = (int) $item->lahan_id;
            $jenisKayuId = (int) $item->jenis_kayu_id;
            $grade       = $this->mapGrade($item->grade);
            return "{$lahanId}_{$jenisKayuId}_{$grade}_{$item->panjang}";
        });

        Log::info("HppAverageService — {$grouped->count()} kombinasi (lahan+jenis+grade+panjang) ditemukan");

        try {
            DB::transaction(function () use ($grouped, $nota, $kayuMasuk, $tanggal) {
                foreach ($grouped as $key => $items) {
                    $first       = $items->first();
                    $lahanId     = (int) $first->lahan_id;
                    $jenisKayuId = (int) $first->jenis_kayu_id;
                    $grade       = $this->mapGrade($first->grade);
                    $panjang     = (int) $first->panjang;

                    Log::info("HppAverageService — proses kombinasi [{$key}]", [
                        'lahan_id'      => $lahanId,
                        'jenis_kayu_id' => $jenisKayuId,
                        'grade_raw'     => $first->grade,
                        'grade_mapped'  => $grade,
                        'panjang'       => $panjang,
                        'jumlah_item'   => $items->count(),
                    ]);

                    // Langsung pakai kolom kubikasi dari detail (tidak dihitung ulang)
                    $totalBatang   = (int)   $items->sum('kuantitas');
                    $totalKubikasi = (float) $items->sum('kubikasi');

                    // Poin per item = kubikasi × harga_beli × 1000
                    $totalPoin = (float) $items->sum(function ($item) {
                        $harga = $this->getHargaBeli($item);
                        return (float) $item->kubikasi * $harga * 1000;
                    });

                    // HPP average = total poin / total kubikasi (untuk keperluan log)
                    $hargaRataRata = $totalKubikasi > 0 ? $totalPoin / $totalKubikasi : 0.0;

                    Log::info("HppAverageService — hasil kalkulasi kombinasi [{$key}]", [
                        'total_batang'   => $totalBatang,
                        'total_kubikasi' => $totalKubikasi,
                        'total_poin'     => $totalPoin,
                        'hpp_rata_rata'  => $hargaRataRata,
                    ]);

                    $this->catatTransaksiMasuk(
                        lahanId: $lahanId,
                        jenisKayuId: $jenisKayuId,
                        grade: $grade,
                        panjang: $panjang,
                        tanggal: $tanggal,
                        totalBatang: $totalBatang,
                        totalKubikasi: $totalKubikasi,
                        harga: $hargaRataRata,
                        nilaiMasuk: $totalPoin,
                        keterangan: "Nota #{$nota->no_nota} · KayuMasuk #{$kayuMasuk->id}",
                        referensi: $nota,
                    );

                    Log::info("HppAverageService — kombinasi [{$key}] berhasil dicatat");
                }
            });

            Log::info("HppAverageService::prosesNotaKayuMasuk — selesai NotaKayu #{$nota->id}");
        } catch (\Throwable $e) {
            Log::error("HppAverageService::prosesNotaKayuMasuk — GAGAL pada NotaKayu #{$nota->id}", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function catatTransaksiKeluar(
        int    $lahanId,
        int    $jenisKayuId,
        mixed  $grade,
        int    $panjang,
        string $tanggal,
        int    $totalBatang,
        float  $totalKubikasi,
        string $keterangan = '',
        mixed  $referensi  = null,
    ): HppAverageLog {
        $grade = $this->mapGrade($grade);

        return DB::transaction(function () use (
            $lahanId,
            $jenisKayuId,
            $grade,
            $panjang,
            $tanggal,
            $totalBatang,
            $totalKubikasi,
            $keterangan,
            $referensi
        ) {
            $summary    = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $grade, $panjang);
            $hppAverage = (float) $summary->hpp_average;
            $nilaiKeluar = round($totalKubikasi * $hppAverage, 4);

            $before = [
                'btg' => (int)   $summary->stok_batang,
                'm3'  => (float) $summary->stok_kubikasi,
                'val' => (float) $summary->nilai_stok,
            ];

            $after = [
                'btg' => max(0,   $before['btg'] - $totalBatang),
                'm3'  => max(0.0, round($before['m3'] - $totalKubikasi, 6)),
                'val' => max(0.0, round($before['val'] - $nilaiKeluar,  4)),
            ];

            $log = HppAverageLog::create([
                'id_jenis_kayu'        => $jenisKayuId,
                'grade'                => $grade,
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

            $summary->update([
                'stok_batang'   => $after['btg'],
                'stok_kubikasi' => $after['m3'],
                'nilai_stok'    => $after['val'],
                'hpp_average'   => $hppAverage,
                'id_last_log'   => $log->id,
            ]);

            return $log;
        });
    }

    // =========================================================================
    // PRIVATE — LOGIKA MASUK
    // =========================================================================

    private function catatTransaksiMasuk(
        int    $lahanId,
        int    $jenisKayuId,
        string $grade,
        int    $panjang,
        string $tanggal,
        int    $totalBatang,
        float  $totalKubikasi,
        float  $harga,
        float  $nilaiMasuk,
        string $keterangan = '',
        mixed  $referensi  = null,
    ): HppAverageLog {
        Log::info('HppAverageService::catatTransaksiMasuk — forKombinasi', [
            'lahan_id'      => $lahanId,
            'jenis_kayu_id' => $jenisKayuId,
            'grade'         => $grade,
            'panjang'       => $panjang,
        ]);

        $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $grade, $panjang);

        if (! $summary) {
            Log::warning("catatTransaksiMasuk — skip kombinasi karena jenis_kayu_id={$jenisKayuId} tidak ada di master");
            return new HppAverageLog();
        }

        $before = [
            'btg' => (int)   $summary->stok_batang,
            'm3'  => (float) $summary->stok_kubikasi,
            'val' => (float) $summary->nilai_stok,
            'hpp' => (float) $summary->hpp_average,
        ];

        $after = [
            'btg' => $before['btg'] + $totalBatang,
            'm3'  => round($before['m3'] + $totalKubikasi, 6),
            'val' => round($before['val'] + $nilaiMasuk,   2),
        ];

        // HPP average = total nilai stok / total kubikasi
        $after['hpp'] = $after['m3'] > 0
            ? round($after['val'] / $after['m3'], 6)
            : $harga;

        Log::info('HppAverageService::catatTransaksiMasuk — snapshot', [
            'before' => $before,
            'after'  => $after,
        ]);

        $log = HppAverageLog::create([
            'id_jenis_kayu'        => $jenisKayuId,
            'grade'                => $grade,
            'panjang'              => $panjang,
            'tanggal'              => $tanggal,
            'tipe_transaksi'       => 'masuk',
            'keterangan'           => $keterangan,
            'referensi_id'         => $referensi?->id,
            'referensi_type'       => $referensi ? get_class($referensi) : null,
            'total_batang'         => $totalBatang,
            'total_kubikasi'       => $totalKubikasi,
            'harga'                => $harga,
            'nilai_stok'           => $nilaiMasuk,
            'stok_batang_before'   => $before['btg'],
            'stok_kubikasi_before' => $before['m3'],
            'nilai_stok_before'    => $before['val'],
            'stok_batang_after'    => $after['btg'],
            'stok_kubikasi_after'  => $after['m3'],
            'nilai_stok_after'     => $after['val'],
            'hpp_average'          => $after['hpp'],
        ]);

        Log::info("HppAverageService::catatTransaksiMasuk — log #{$log->id} berhasil dibuat");

        $summary->update([
            'stok_batang'   => $after['btg'],
            'stok_kubikasi' => $after['m3'],
            'nilai_stok'    => $after['val'],
            'hpp_average'   => $after['hpp'],
            'id_last_log'   => $log->id,
        ]);

        Log::info("HppAverageService::catatTransaksiMasuk — summary #{$summary->id} berhasil diupdate");

        return $log;
    }

    // =========================================================================
    // PUBLIC — SEED DATA HISTORIS (jalankan sekali via tinker)
    // =========================================================================

    public function seedFromNotaKayu(): void
    {
        Log::info('HppAverageService::seedFromNotaKayu — mulai seed');

        $total = NotaKayu::whereHas('kayuMasuk')->count();
        Log::info("HppAverageService::seedFromNotaKayu — {$total} NotaKayu akan diproses");

        // Reset semua data
        HppAverageLog::query()->delete();
        HppAverageSummarie::query()->update([
            'stok_batang'   => 0,
            'stok_kubikasi' => 0,
            'nilai_stok'    => 0,
            'hpp_average'   => 0,
            'id_last_log'   => null,
        ]);

        $processed = 0;
        $skipped   = 0;

        // Tiap nota punya transaction sendiri di prosesNotaKayuMasuk
        NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->whereHas('kayuMasuk')
            ->orderBy('id')
            ->each(function (NotaKayu $nota) use (&$processed, &$skipped) {
                try {
                    $this->prosesNotaKayuMasuk($nota);
                    $processed++;
                    Log::info("seedFromNotaKayu — nota #{$nota->id} selesai ({$processed})");
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::warning("seedFromNotaKayu — nota #{$nota->id} diskip: {$e->getMessage()}");
                }
            });

        $logCount     = HppAverageLog::count();
        $summaryCount = HppAverageSummarie::where('stok_batang', '>', 0)->count();
        Log::info("seedFromNotaKayu — selesai. Diproses: {$processed}, Diskip: {$skipped}, Log: {$logCount}, Summary aktif: {$summaryCount}");
    }

    // =========================================================================
    // PUBLIC — HITUNG ULANG DARI LOG YANG ADA
    // =========================================================================

    public function recalculateAll(): void
    {
        Log::info('HppAverageService::recalculateAll — mulai');

        DB::transaction(function () {
            HppAverageSummarie::query()->update([
                'stok_batang'   => 0,
                'stok_kubikasi' => 0,
                'nilai_stok'    => 0,
                'hpp_average'   => 0,
                'id_last_log'   => null,
            ]);

            $logs = HppAverageLog::orderBy('tanggal')->orderBy('id')->get();
            Log::info("HppAverageService::recalculateAll — {$logs->count()} log ditemukan");

            $state = [];

            foreach ($logs as $log) {
                $key = "{$log->id_jenis_kayu}|{$log->grade}|{$log->panjang}";

                if (! isset($state[$key])) {
                    $state[$key] = ['btg' => 0, 'm3' => 0.0, 'val' => 0.0, 'hpp' => 0.0];
                }

                $s = &$state[$key];

                $log->stok_batang_before   = $s['btg'];
                $log->stok_kubikasi_before = $s['m3'];
                $log->nilai_stok_before    = $s['val'];

                if ($log->tipe_transaksi === 'masuk') {
                    $s['btg'] += (int)   $log->total_batang;
                    $s['m3']   = round($s['m3'] + (float) $log->total_kubikasi, 6);
                    $s['val']  = round($s['val'] + (float) $log->nilai_stok,    2);
                    $s['hpp']  = $s['m3'] > 0
                        ? round($s['val'] / $s['m3'], 6)
                        : (float) $log->harga;
                } else {
                    $nilaiKeluar = round((float) $log->total_kubikasi * $s['hpp'], 2);
                    $s['btg']    = max(0,   $s['btg'] - (int)   $log->total_batang);
                    $s['m3']     = max(0.0, round($s['m3'] - (float) $log->total_kubikasi, 6));
                    $s['val']    = max(0.0, round($s['val'] - $nilaiKeluar, 2));
                }

                $log->stok_batang_after   = $s['btg'];
                $log->stok_kubikasi_after = $s['m3'];
                $log->nilai_stok_after    = $s['val'];
                $log->hpp_average         = $s['hpp'];
                $log->saveQuietly();
            }

            foreach ($state as $key => $s) {
                [$jenisKayuId, $grade, $panjang] = explode('|', $key);

                $lastLog = HppAverageLog::where('id_jenis_kayu', $jenisKayuId)
                    ->where('grade',   $grade)
                    ->where('panjang', $panjang)
                    ->orderByDesc('tanggal')
                    ->orderByDesc('id')
                    ->first();

                HppAverageSummarie::where('id_jenis_kayu', $jenisKayuId)
                    ->where('grade',   $grade)
                    ->where('panjang', $panjang)
                    ->update([
                        'stok_batang'   => $s['btg'],
                        'stok_kubikasi' => $s['m3'],
                        'nilai_stok'    => $s['val'],
                        'hpp_average'   => $s['hpp'],
                        'id_last_log'   => $lastLog?->id,
                    ]);
            }

            Log::info('HppAverageService::recalculateAll — selesai', [
                'kombinasi_diproses' => count($state),
            ]);
        });
    }
}
