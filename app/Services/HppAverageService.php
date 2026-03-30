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

        $lastLog = HppAverageLog::where('id_lahan', $lahanId) // TAMBAHKAN INI
            ->where('id_jenis_kayu', $jenisKayuId)
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
            // 4. Catat Log dengan snapshot yang sudah terisolasi per lahan
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

            // 5. Update Summary langsung dari perhitungan tadi
            $summary->update([
                'stok_batang'   => $after['btg'],
                'stok_kubikasi' => $after['m3'],
                'nilai_stok'    => $after['val'],
                'id_last_log'   => $log->id,
            ]);

            return $log;
        });
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
        DB::transaction(function () {
            // 1. Reset Summary
            HppAverageSummarie::query()->update([
                'stok_batang' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0
            ]);

            $logs = HppAverageLog::orderBy('tanggal')->orderBy('id')->get();
            $state = [];

            foreach ($logs as $log) {
                /** * PERBAIKAN KRUSIAL: 
                 * Kita tambahkan id_lahan ke dalam key.
                 * Dengan begini, CA (Lahan 1) dan OA (Lahan 2) punya "kantong" sendiri-sendiri.
                 */
                $key = "Lahan_{$log->id_lahan}_Kayu_{$log->id_jenis_kayu}_Pjg_{$log->panjang}";

                if (!isset($state[$key])) {
                    // Jika kunci ini baru (Lahan baru/Ukuran baru di lahan tersebut), mulai dari 0
                    $state[$key] = ['btg' => 0, 'm3' => 0.0, 'val' => 0.0, 'hpp' => 0.0];
                }

                $current = &$state[$key];

                // Set saldo Sebelum (Sekarang CA 260cm akan mulai dari 0 jika belum ada data sebelumnya di CA)
                $log->stok_batang_before = $current['btg'];
                $log->stok_kubikasi_before = $current['m3'];
                $log->nilai_stok_before = $current['val'];

                if ($log->tipe_transaksi === 'masuk') {
                    $current['btg'] += $log->total_batang;
                    $current['m3']  = round($current['m3'] + $log->total_kubikasi, 4);
                    $current['val'] = round($current['val'] + $log->nilai_stok, 2);
                    $current['hpp'] = $current['m3'] > 0 ? round($current['val'] / $current['m3'], 2) : 0;
                } else {
                    $log->harga = $current['hpp'];
                    $log->nilai_stok = round($log->total_kubikasi * $current['hpp'], 2);

                    $current['btg'] -= $log->total_batang;
                    $current['m3']  = round($current['m3'] - $log->total_kubikasi, 4);
                    $current['val'] = round($current['val'] - $log->nilai_stok, 2);
                }

                // Simpan saldo Sesudah
                $log->stok_batang_after = $current['btg'];
                $log->stok_kubikasi_after = $current['m3'];
                $log->nilai_stok_after = $current['val'];
                $log->hpp_average = $current['hpp'];

                $log->saveQuietly();

                // Update Summary (Agar di halaman stok juga terpisah per lahan)
                $this->syncToSummary($log, $current);
            }
        });
    }
    private function syncToSummary($log, $currentState): void
    {
        \App\Models\HppAverageSummarie::updateOrCreate(
            [
                'id_lahan'      => $log->id_lahan,
                'id_jenis_kayu' => $log->id_jenis_kayu,
                'panjang'       => $log->panjang,
                'grade'         => null // Sesuaikan dengan desainmu
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

    public function fixMissingLahanIds()
    {
        $logs = HppAverageLog::where('id_lahan', 0)->orWhereNull('id_lahan')->get();

        foreach ($logs as $log) {
            if ($log->referensi_type === 'App\Models\NotaKayu') {
                $nota = NotaKayu::find($log->referensi_id);
                if ($nota) {
                    $log->update(['id_lahan' => $nota->id_lahan]);
                }
            }
            // Tambahkan pengecekan untuk tipe referensi lain jika ada (misal: KayuKeluar)
        }

        return "Data id_lahan telah disinkronkan.";
    }
}
