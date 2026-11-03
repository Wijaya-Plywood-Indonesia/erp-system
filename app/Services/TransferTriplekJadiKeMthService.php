<?php

namespace App\Services;

use App\Models\HppTriplekJadiLog;
use App\Models\HppTriplekMthLog;
use App\Models\SerahTerimaHp;
use App\Models\StokTriplekJadi;
use App\Models\StokTriplekMth;
use App\Models\TriplekJadiMutasiKeluar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi Gudang Triplek Jadi -> Gudang Triplek Mentah.
 * Stok sumber & tujuan baru berubah saat Gudang Triplek Mentah menerima.
 * Antrean memakai SerahTerimaHp (tujuan = 'triplek_mth', id_triplek_mutasi_keluar terisi).
 */
class TransferTriplekJadiKeMthService
{
    public const TUJUAN = 'triplek_mth';

    public function terima(int $idSerahTerimaHp): void
    {
        DB::transaction(function () use ($idSerahTerimaHp) {
            // 1. Kunci baris serah terima: pencegah terima ganda.
            $st = SerahTerimaHp::lockForUpdate()->find($idSerahTerimaHp);

            if (! $st || $st->diterima_gudang_at || $st->ditolak_oleh || $st->diterima_oleh !== '-') {
                throw new \RuntimeException('Barang ini sudah diterima atau ditolak.');
            }

            if ($st->tujuan !== self::TUJUAN || ! $st->id_triplek_mutasi_keluar) {
                throw new \RuntimeException('Record bukan mutasi Triplek Jadi ke Triplek Mentah.');
            }

            $mutasi = TriplekJadiMutasiKeluar::lockForUpdate()->find($st->id_triplek_mutasi_keluar);

            if (! $mutasi) {
                throw new \RuntimeException('Data mutasi keluar tidak ditemukan.');
            }

            $qty = (int) $mutasi->stok_lembar;
            $kubikasi = (float) $mutasi->stok_kubikasi;

            if ($qty <= 0) {
                throw new \RuntimeException('Jumlah mutasi tidak valid.');
            }

            // 2. Kunci stok sumber (identitas: kayu + ukuran + grade).
            $sumber = StokTriplekJadi::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
                ->where('panjang', $mutasi->panjang)
                ->where('lebar', $mutasi->lebar)
                ->where('tebal', $mutasi->tebal)
                ->where('kw_grade', $mutasi->kw_grade)
                ->lockForUpdate()
                ->first();

            if (! $sumber || (int) $sumber->stok_lembar < $qty) {
                throw new \RuntimeException(
                    'Stok Triplek Jadi tidak cukup. Tersedia: '.($sumber?->stok_lembar ?? 0)." lembar, diminta: {$qty}."
                );
            }

            // 3. Nilai yang pindah proporsional terhadap nilai stok sumber.
            $nilaiPindah = (float) $sumber->stok_lembar > 0
                ? round((float) $sumber->nilai_stok * $qty / (float) $sumber->stok_lembar, 2)
                : 0.0;

            // 4. Kunci / buat stok tujuan.
            $key = [
                'id_jenis_kayu' => $mutasi->id_jenis_kayu,
                'panjang' => $mutasi->panjang,
                'lebar' => $mutasi->lebar,
                'tebal' => $mutasi->tebal,
                'kw_grade' => $mutasi->kw_grade,
            ];

            $tujuan = StokTriplekMth::where($key)->lockForUpdate()->first()
                ?? StokTriplekMth::create($key + [
                    'stok_lembar' => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok' => 0,
                    'hpp_average' => 0,
                    'hpp_pekerja_last' => 0,
                    'hpp_bahan_penolong_last' => 0,
                ]);

            $user = Auth::user()?->name ?? 'System';
            $keterangan = "Mutasi Triplek Jadi -> Triplek Mentah (serah terima #{$st->id}, mutasi #{$mutasi->id}), diterima oleh {$user}";

            // ── SUMBER (Triplek Jadi): keluar ──
            $sBefore = [(int) $sumber->stok_lembar, (float) $sumber->stok_kubikasi, (float) $sumber->nilai_stok];

            $sumber->stok_lembar = $sBefore[0] - $qty;
            $sumber->stok_kubikasi = $sumber->stok_lembar === 0 ? 0 : max(0, $sBefore[1] - $kubikasi);
            $sumber->nilai_stok = $sumber->stok_lembar === 0 ? 0 : max(0, $sBefore[2] - $nilaiPindah);
            $sumber->save();

            $logSumber = HppTriplekJadiLog::create($key + [
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'keluar',
                'keterangan' => $keterangan,
                'referensi_type' => TriplekJadiMutasiKeluar::class,
                'referensi_id' => $mutasi->id,
                'total_lembar' => $qty,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $sumber->hpp_average,
                'nilai_stok' => $nilaiPindah,
                'stok_lembar_before' => $sBefore[0],
                'stok_kubikasi_before' => $sBefore[1],
                'nilai_stok_before' => $sBefore[2],
                'stok_lembar_after' => $sumber->stok_lembar,
                'stok_kubikasi_after' => $sumber->stok_kubikasi,
                'nilai_stok_after' => $sumber->nilai_stok,
            ]);
            $sumber->update(['id_last_log' => $logSumber->id]);

            // ── TUJUAN (Triplek Mentah): masuk ──
            $tBefore = [(int) $tujuan->stok_lembar, (float) $tujuan->stok_kubikasi, (float) $tujuan->nilai_stok];
            $lembarAfter = $tBefore[0] + $qty;

            $hppAfter = $lembarAfter > 0
                ? (($tBefore[0] * (float) $tujuan->hpp_average) + ($qty * (float) $sumber->hpp_average)) / $lembarAfter
                : 0;

            $tujuan->stok_lembar = $lembarAfter;
            $tujuan->stok_kubikasi = $tBefore[1] + $kubikasi;
            $tujuan->nilai_stok = $tBefore[2] + $nilaiPindah;
            $tujuan->hpp_average = $hppAfter;
            $tujuan->save();

            $logTujuan = HppTriplekMthLog::create($key + [
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'masuk',
                'keterangan' => $keterangan,
                'referensi_type' => SerahTerimaHp::class,
                'referensi_id' => $st->id,
                'total_lembar' => $qty,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => $sumber->hpp_pekerja_last ?? 0,
                'hpp_bahan_penolong' => $sumber->hpp_bahan_penolong_last ?? 0,
                'hpp_average' => $hppAfter,
                'nilai_stok' => $nilaiPindah,
                'stok_lembar_before' => $tBefore[0],
                'stok_kubikasi_before' => $tBefore[1],
                'nilai_stok_before' => $tBefore[2],
                'stok_lembar_after' => $tujuan->stok_lembar,
                'stok_kubikasi_after' => $tujuan->stok_kubikasi,
                'nilai_stok_after' => $tujuan->nilai_stok,
            ]);
            $tujuan->update(['id_last_log' => $logTujuan->id]);

            // 5. Tandai diterima (di transaksi yang sama).
            $st->update([
                'diterima_oleh' => $user.' - Gudang Triplek Mentah',
                'status' => 'Terima Gudang Triplek Mentah',
                'diterima_gudang_at' => now(),
                'diterima_gudang_oleh' => $user,
            ]);
        });
    }
}
