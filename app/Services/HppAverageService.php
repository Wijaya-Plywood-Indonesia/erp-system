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
 * TRIGGER:
 *   - Masuk  → NotaKayuObserver::updated() saat status → "Sudah Diperiksa"
 *   - Keluar → catatTransaksiKeluar() dipanggil manual
 *   - Hapus  → batalkanNotaKayuMasuk() via NotaKayuObserver::deleting()
 *
 * ALUR DATA:
 *   NotaKayu → KayuMasuk (tgl_kayu_masuk)
 *                → DetailTurusanKayu (lahan_id, jenis_kayu_id, panjang, diameter, kuantitas, grade)
 */
class HppAverageService
{
    // =========================================================================
    // HELPERS PRIVATE
    // =========================================================================

    private function mapGrade(int $grade): string
    {
        return match ($grade) {
            1       => 'A',
            2       => 'B',
            default => 'C',
        };
    }

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
        $harga = HargaKayu::where('id_jenis_kayu',     $jenisKayuId)
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
    // PROSES NOTA KAYU MASUK
    // Dipanggil dari observer saat status berubah ke "Sudah Diperiksa"
    // =========================================================================

    public function prosesNotaKayuMasuk(NotaKayu $nota): void
    {
        Log::info('[HPP] prosesNotaKayuMasuk mulai', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
            'status'  => $nota->status,
        ]);

        if (! str_contains($nota->status ?? '', 'Sudah Diperiksa')) {
            Log::warning('[HPP] SKIP — status bukan Sudah Diperiksa', [
                'nota_id' => $nota->id,
                'status'  => $nota->status,
            ]);
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

        $tanggal    = \Carbon\Carbon::parse($kayuMasuk->tgl_kayu_masuk)->format('Y-m-d');
        $keterangan = "Nota #{$nota->no_nota}";

        Log::info('[HPP] mulai grouping detail', [
            'nota_id'      => $nota->id,
            'detail_count' => $details->count(),
            'tanggal'      => $tanggal,
        ]);

        DB::transaction(function () use ($nota, $tanggal, $keterangan, $details) {

            // Grouping: per lahan + jenis_kayu + panjang (grade diabaikan di HPP)
            $grouped = $details->groupBy(
                fn($d) => "{$d->lahan_id}_{$d->jenis_kayu_id}_{$d->panjang}"
            );

            Log::info('[HPP] grouped kombinasi', [
                'nota_id' => $nota->id,
                'count'   => $grouped->count(),
                'keys'    => $grouped->keys()->toArray(),
            ]);

            foreach ($grouped as $key => $rows) {
                $lahanId     = (int) $rows->first()->lahan_id;
                $jenisKayuId = (int) $rows->first()->jenis_kayu_id;
                $panjang     = (int) $rows->first()->panjang;

                // Akumulasi batang
                $totalBatang = (int) $rows->sum('kuantitas');

                // Kubikasi: round per baris dulu, lalu dijumlahkan (ikut nota cetak)
                $totalKubikasi = (float) $rows->sum(fn($d) => $this->hitungKubikasi(
                    (float) $d->panjang,
                    (float) $d->diameter,
                    (float) $d->kuantitas
                ));

                // Nilai masuk = Σ (kubikasi_per_baris × harga × 1000)
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

                Log::info('[HPP] kombinasi dihitung', [
                    'key'            => $key,
                    'lahan_id'       => $lahanId,
                    'jenis_kayu_id'  => $jenisKayuId,
                    'panjang'        => $panjang,
                    'total_batang'   => $totalBatang,
                    'total_kubikasi' => $totalKubikasi,
                    'total_nilai'    => $totalNilai,
                ]);

                if ($totalBatang === 0 || $lahanId === 0 || $jenisKayuId === 0) {
                    Log::warning('[HPP] kombinasi SKIP — data tidak lengkap', [
                        'nota_id' => $nota->id,
                        'key'     => $key,
                    ]);
                    continue;
                }

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

        Log::info('[HPP] prosesNotaKayuMasuk selesai', ['nota_id' => $nota->id]);
    }

    // =========================================================================
    // CATAT TRANSAKSI MASUK (private)
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

        $lastLog = HppAverageLog::where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->whereNull('grade')
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

        $hppAverage = $after['m3'] > 0
            ? round($after['val'] / $after['m3'], 2)
            : 0.0;

        $hargaSatuan = $totalKubikasi > 0
            ? round($totalNilai / $totalKubikasi, 2)
            : 0.0;

        Log::info('[HPP] catatTransaksiMasuk snapshot', [
            'lahan_id'      => $lahanId,
            'jenis_kayu_id' => $jenisKayuId,
            'panjang'       => $panjang,
            'before'        => $before,
            'after'         => $after,
            'hpp_average'   => $hppAverage,
        ]);

        $log = HppAverageLog::create([
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
            'harga'                => $hargaSatuan,
            'nilai_stok'           => $totalNilai,
            'stok_batang_before'   => $before['btg'],
            'stok_kubikasi_before' => $before['m3'],
            'nilai_stok_before'    => $before['val'],
            'stok_batang_after'    => $after['btg'],
            'stok_kubikasi_after'  => $after['m3'],
            'nilai_stok_after'     => $after['val'],
            'hpp_average'          => $hppAverage,
        ]);

        Log::info('[HPP] log dibuat', ['log_id' => $log->id]);

        $this->updateSummaryMasuk(
            lahanId: $lahanId,
            jenisKayuId: $jenisKayuId,
            panjang: $panjang,
            totalBatang: $totalBatang,
            totalKubikasi: $totalKubikasi,
            totalNilai: $totalNilai,
            hppAverage: $hppAverage,
            logId: $log->id,
        );

        return $log;
    }

    // =========================================================================
    // UPDATE SUMMARY PER LAHAN — MASUK
    // =========================================================================

    private function updateSummaryMasuk(
        int   $lahanId,
        int   $jenisKayuId,
        int   $panjang,
        int   $totalBatang,
        float $totalKubikasi,
        float $totalNilai,
        float $hppAverage,
        int   $logId,
    ): void {
        $summary = HppAverageSummarie::forKombinasi($lahanId, $jenisKayuId, $panjang);

        if (! $summary) {
            Log::error('[HPP] summary NULL — kombinasi tidak ditemukan', [
                'lahan_id'      => $lahanId,
                'jenis_kayu_id' => $jenisKayuId,
                'panjang'       => $panjang,
            ]);
            return;
        }

        $summary->update([
            'stok_batang'   => $summary->stok_batang   + $totalBatang,
            'stok_kubikasi' => round($summary->stok_kubikasi + $totalKubikasi, 4),
            'nilai_stok'    => round($summary->nilai_stok    + $totalNilai,    2),
            'hpp_average'   => $hppAverage,
            'id_last_log'   => $logId,
        ]);

        Log::info('[HPP] summary diupdate (masuk)', [
            'summary_id'    => $summary->id,
            'lahan_id'      => $lahanId,
            'stok_batang'   => $summary->fresh()->stok_batang,
            'stok_kubikasi' => $summary->fresh()->stok_kubikasi,
            'hpp_average'   => $hppAverage,
        ]);
    }

    // =========================================================================
    // CATAT TRANSAKSI KELUAR (public — dipanggil manual)
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

        Log::info('[HPP] catatTransaksiKeluar', [
            'lahan_id'       => $lahanId,
            'jenis_kayu_id'  => $jenisKayuId,
            'panjang'        => $panjang,
            'total_batang'   => $totalBatang,
            'total_kubikasi' => $totalKubikasi,
            'hpp_saat_ini'   => $hppAverage,
            'nilai_keluar'   => $nilaiKeluar,
        ]);

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
                'stok_kubikasi' => max(0.0, round($summary->stok_kubikasi - $totalKubikasi, 4)),
                'nilai_stok'    => max(0.0, round($summary->nilai_stok    - $nilaiKeluar,   2)),
                'hpp_average'   => $hppAverage,
                'id_last_log'   => $log->id,
            ]);
        }

        return $log;
    }

    // =========================================================================
    // BATALKAN NOTA KAYU MASUK
    // Dipanggil dari observer::deleting() sebelum nota dihapus
    // =========================================================================

    public function batalkanNotaKayuMasuk(NotaKayu $nota): void
    {
        Log::info('[HPP] batalkanNotaKayuMasuk mulai', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        $logs = HppAverageLog::where('referensi_type', NotaKayu::class)
            ->where('referensi_id', $nota->id)
            ->whereNull('grade')
            ->get();

        if ($logs->isEmpty()) {
            Log::info('[HPP] batalkanNotaKayuMasuk SKIP — tidak ada log', ['nota_id' => $nota->id]);
            return;
        }

        Log::info('[HPP] batalkanNotaKayuMasuk — hapus log', [
            'nota_id'   => $nota->id,
            'log_count' => $logs->count(),
            'log_ids'   => $logs->pluck('id')->toArray(),
        ]);

        DB::transaction(function () use ($nota) {
            HppAverageLog::where('referensi_type', NotaKayu::class)
                ->where('referensi_id', $nota->id)
                ->whereNull('grade')
                ->delete();

            $this->recalculateAll();
        });

        Log::info('[HPP] batalkanNotaKayuMasuk selesai', ['nota_id' => $nota->id]);
    }

    // =========================================================================
    // SEED DATA HISTORIS
    // =========================================================================

    public function seedFromNotaKayu(): void
    {
        Log::info('[HPP] seedFromNotaKayu MULAI');

        HppAverageLog::whereNull('grade')->delete();
        HppAverageSummarie::whereNull('grade')->update([
            'stok_batang'   => 0,
            'stok_kubikasi' => 0,
            'nilai_stok'    => 0,
            'hpp_average'   => 0,
            'id_last_log'   => null,
        ]);

        Log::info('[HPP] reset selesai, mulai loop nota');

        $processed = 0;
        $skipped   = 0;

        NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->whereHas('kayuMasuk')
            ->where('status', 'like', '%Sudah Diperiksa%')
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

    // =========================================================================
    // RECALCULATE ALL
    // =========================================================================

    public function recalculateAll(): void
    {
        Log::info('[HPP] recalculateAll MULAI');

        $logs = HppAverageLog::whereNull('grade')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        Log::info('[HPP] total log akan diproses', ['count' => $logs->count()]);

        $state = [];

        foreach ($logs as $log) {
            $key = "{$log->id_jenis_kayu}_{$log->panjang}";

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
                : ($before['m3'] > 0 ? round($before['val'] / $before['m3'], 2) : 0);

            $log->updateQuietly([
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

        Log::info('[HPP] snapshot selesai, sync summary');

        $this->syncSummariesFromLogs();

        Log::info('[HPP] recalculateAll SELESAI');
    }

    // =========================================================================
    // SYNC SUMMARIES FROM LOGS
    // =========================================================================

    private function syncSummariesFromLogs(): void
    {
        HppAverageSummarie::whereNull('grade')->update([
            'stok_batang'   => 0,
            'stok_kubikasi' => 0,
            'nilai_stok'    => 0,
            'hpp_average'   => 0,
            'id_last_log'   => null,
        ]);

        $logs = HppAverageLog::whereNull('grade')
            ->whereNotNull('referensi_id')
            ->where('referensi_type', NotaKayu::class)
            ->where('tipe_transaksi', 'masuk')
            ->with(['referensi.kayuMasuk.detailTurusanKayus'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $summaryState = [];

        foreach ($logs as $log) {
            $nota      = $log->referensi;
            $kayuMasuk = $nota?->kayuMasuk;

            if (! $kayuMasuk) continue;

            $details = $kayuMasuk->detailTurusanKayus
                ->where('jenis_kayu_id', $log->id_jenis_kayu)
                ->where('panjang', $log->panjang);

            if ($details->isEmpty()) continue;

            $lahanId = (int) $details->first()->lahan_id;
            if (! $lahanId) continue;

            $key = "{$lahanId}_{$log->id_jenis_kayu}_{$log->panjang}";

            if (! isset($summaryState[$key])) {
                $summaryState[$key] = [
                    'lahan_id'      => $lahanId,
                    'jenis_kayu_id' => $log->id_jenis_kayu,
                    'panjang'       => $log->panjang,
                    'btg'           => 0,
                    'm3'            => 0.0,
                    'val'           => 0.0,
                ];
            }

            $summaryState[$key]['btg'] += $log->total_batang;
            $summaryState[$key]['m3']   = round($summaryState[$key]['m3'] + $log->total_kubikasi, 4);
            $summaryState[$key]['val']  = round($summaryState[$key]['val'] + $log->nilai_stok,    2);
        }

        $logsKeluar = HppAverageLog::whereNull('grade')
            ->where('tipe_transaksi', 'keluar')
            ->with(['referensi'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        foreach ($logsKeluar as $log) {
            $lahanId = $log->referensi?->lahan_id ?? null;
            if (! $lahanId) continue;

            $key = "{$lahanId}_{$log->id_jenis_kayu}_{$log->panjang}";
            if (! isset($summaryState[$key])) continue;

            $nilaiKeluar = round($log->total_kubikasi * $log->hpp_average, 2);
            $summaryState[$key]['btg'] = max(0, $summaryState[$key]['btg'] - $log->total_batang);
            $summaryState[$key]['m3']  = max(0.0, round($summaryState[$key]['m3'] - $log->total_kubikasi, 4));
            $summaryState[$key]['val'] = max(0.0, round($summaryState[$key]['val'] - $nilaiKeluar,        2));
        }

        foreach ($summaryState as $s) {
            $hppAverage = $s['m3'] > 0 ? round($s['val'] / $s['m3'], 2) : 0.0;

            $lastLog = HppAverageLog::where('id_jenis_kayu', $s['jenis_kayu_id'])
                ->where('panjang', $s['panjang'])
                ->whereNull('grade')
                ->orderByDesc('tanggal')
                ->orderByDesc('id')
                ->first();

            $summary = HppAverageSummarie::forKombinasi(
                $s['lahan_id'],
                $s['jenis_kayu_id'],
                $s['panjang']
            );

            if ($summary) {
                $summary->updateQuietly([
                    'stok_batang'   => $s['btg'],
                    'stok_kubikasi' => $s['m3'],
                    'nilai_stok'    => $s['val'],
                    'hpp_average'   => $hppAverage,
                    'id_last_log'   => $lastLog?->id,
                ]);
            }
        }

        Log::info('[HPP] syncSummariesFromLogs selesai', [
            'kombinasi_count' => count($summaryState),
        ]);
    }
}
