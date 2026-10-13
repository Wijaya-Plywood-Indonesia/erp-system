<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use Illuminate\Support\Facades\DB;

/**
 * HppVeneerBasahRecalcService
 *
 * Menghitung ulang NILAI & HPP moving average satu kombinasi
 * (jenis kayu + ukuran + KW) dengan memutar ulang semua log berurutan (by id).
 *
 * YANG DIUBAH : hpp_average, nilai_stok, nilai_stok_before, nilai_stok_after (log)
 *               nilai_stok, hpp_average, hpp_*_last (summary)
 * YANG TIDAK  : jumlah lembar & kubikasi (before/after) — dipakai apa adanya.
 *
 * Aturan nilai per jenis log:
 *   - MASUK palet rotary yang sudah punya komponen HPP → nilai = (kayu+pekerja+mesin+bahan) × Δm³
 *   - MASUK lain (opname) / palet yang belum punya HPP → nilai ikut rata-rata berjalan (netral)
 *   - KELUAR → nilai = Δm³ × rata-rata berjalan; log "SKIP" (stok tidak berubah) tidak mengurangi nilai
 *
 * ARAH & JUMLAH ditentukan dari perubahan stok NYATA (stok_kubikasi_after − before),
 * BUKAN dari tipe_transaksi / total_kubikasi. Alasan: ada log opname bertipe "masuk"
 * padahal stoknya turun (dan sebaliknya), serta log SKIP yang kubikasinya ≠ perubahan stok.
 * Memakai tipe/total_kubikasi membuat nilai & stok tidak sejalan sehingga rata-rata
 * "bocor" jauh melebihi biaya masuk tertinggi.
 */
class HppVeneerBasahRecalcService
{
    private const EPS = 0.0000005;

    public function hitungUlangKombinasi(int $idJenisKayu, $panjang, $lebar, $tebal, ?string $kw): array
    {
        return DB::transaction(function () use ($idJenisKayu, $panjang, $lebar, $tebal, $kw) {
            $kunci = [
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
            ];

            $filter = function ($q) use ($kunci, $kw) {
                $q->where($kunci);
                $kw === null ? $q->whereNull('kw') : $q->where('kw', $kw);

                return $q;
            };

            $summary = $filter(HppVeneerBasahSummary::query())->lockForUpdate()->first();
            $logs = $filter(HppVeneerBasahLog::query())->orderBy('id')->get();

            if ($logs->isEmpty()) {
                return ['logs' => 0, 'berubah' => 0];
            }

            $first = $logs->first();
            $nilai = (float) $first->nilai_stok_before;
            $qtyAwal = (float) $first->stok_kubikasi_before;
            $avg = $qtyAwal > 0 ? round($nilai / $qtyAwal, 2) : 0.0;

            $avgSebelum = $summary ? (float) $summary->hpp_average : null;
            $nilaiSebelum = $summary ? (float) $summary->nilai_stok : null;

            $berubah = 0;
            $logKomponen = null;

            foreach ($logs as $log) {
                $kub = (float) $log->total_kubikasi;
                $qB = (float) $log->stok_kubikasi_before;
                $qA = (float) $log->stok_kubikasi_after;
                $delta = $qA - $qB;   // perubahan stok NYATA (m³): + bertambah, − berkurang
                $nilaiBefore = $nilai;

                if (abs($delta) < self::EPS) {
                    // stok tidak berubah (mis. log SKIP): nilai tidak berubah, hanya dicatat dari kubikasi log
                    $nilaiTrx = round($kub * $avg, 2);
                } elseif ($delta > 0) {
                    // stok BERTAMBAH → masuk nilai. Palet berkomponen HPP memakai HPP-nya,
                    // selain itu (opname dsb) ikut rata-rata berjalan (netral).
                    $entry = $this->hppMasuk($log, $avg);
                    $nilaiTrx = round($entry * $delta, 2);
                    $nilai = round($nilai + $nilaiTrx, 2);
                    $avg = $qA > self::EPS ? round($nilai / $qA, 2) : $entry;

                    if ($this->punyaKomponen($log)) {
                        $logKomponen = $log;
                    }
                } else {
                    // stok BERKURANG → nilai keluar pada rata-rata berjalan
                    $nilaiTrx = round(abs($delta) * $avg, 2);
                    $nilai = $qA <= self::EPS ? 0.0 : max(0.0, round($nilai - $nilaiTrx, 2));
                }

                $baru = [
                    'hpp_average' => $avg,
                    'nilai_stok' => $nilaiTrx,
                    'nilai_stok_before' => $nilaiBefore,
                    'nilai_stok_after' => $nilai,
                ];

                if ($this->berbeda($log, $baru)) {
                    // query builder → updated_at tidak ikut berubah
                    HppVeneerBasahLog::whereKey($log->id)->update($baru);
                    $berubah++;
                }
            }

            if ($summary) {
                $update = ['nilai_stok' => $nilai, 'hpp_average' => $avg];

                if ($logKomponen) {
                    $update += [
                        'hpp_kayu_last' => $logKomponen->hpp_kayu,
                        'hpp_pekerja_last' => $logKomponen->hpp_pekerja,
                        'hpp_mesin_last' => $logKomponen->hpp_mesin,
                        'hpp_bahan_penolong_last' => $logKomponen->hpp_bahan_penolong,
                    ];
                }

                HppVeneerBasahSummary::whereKey($summary->id)->update($update);
            }

            return [
                'logs' => $logs->count(),
                'berubah' => $berubah,
                'avg_sebelum' => $avgSebelum,
                'avg_sesudah' => $avg,
                'nilai_sebelum' => $nilaiSebelum,
                'nilai_sesudah' => $nilai,
            ];
        });
    }

    private function punyaKomponen(HppVeneerBasahLog $log): bool
    {
        return $log->referensi_type === DetailHasilPaletRotary::class
            && ($log->hpp_kayu + $log->hpp_pekerja + $log->hpp_mesin + $log->hpp_bahan_penolong) > 0;
    }

    private function hppMasuk(HppVeneerBasahLog $log, float $avgBerjalan): float
    {
        if ($this->punyaKomponen($log)) {
            return round($log->hpp_kayu + $log->hpp_pekerja + $log->hpp_mesin + $log->hpp_bahan_penolong, 2);
        }

        return $avgBerjalan;
    }

    private function berbeda(HppVeneerBasahLog $log, array $baru): bool
    {
        foreach ($baru as $kolom => $nilai) {
            if (abs((float) $log->{$kolom} - (float) $nilai) > 0.005) {
                return true;
            }
        }

        return false;
    }
}