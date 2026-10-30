<?php

namespace App\Services;

use App\Models\HppPlatformJadiLog;
use App\Models\HppPlatformMthLog;
use App\Models\JenisBarang;
use App\Models\PlatformMthMutasiKeluar;
use App\Models\SerahTerimaHp;
use App\Models\StokPlatformJadi;
use App\Models\StokPlatformMth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi Gudang Platform Mentah -> Gudang Platform Jadi.
 * Stok sumber & tujuan baru berubah saat Gudang Platform Jadi menerima.
 * Antrean memakai SerahTerimaHp (tujuan = 'platform_jadi', id_platform_mth_mutasi_keluar terisi).
 */
class TransferPlatformMthKeJadiService
{
    public const TUJUAN = 'platform_jadi';

    public function terima(int $idSerahTerimaHp): void
    {
        DB::transaction(function () use ($idSerahTerimaHp) {
            // 1. Kunci baris serah terima: pencegah terima ganda.
            $st = SerahTerimaHp::lockForUpdate()->find($idSerahTerimaHp);

            if (! $st || $st->diterima_gudang_at || $st->ditolak_oleh || $st->diterima_oleh !== '-') {
                throw new \RuntimeException('Barang ini sudah diterima atau ditolak.');
            }

            if ($st->tujuan !== self::TUJUAN || ! $st->id_platform_mth_mutasi_keluar) {
                throw new \RuntimeException('Record bukan mutasi Platform Mentah ke Platform Jadi.');
            }

            $mutasi = PlatformMthMutasiKeluar::with('jenisKayu')
                ->lockForUpdate()
                ->find($st->id_platform_mth_mutasi_keluar);

            if (! $mutasi) {
                throw new \RuntimeException('Data mutasi keluar tidak ditemukan.');
            }

            $qty = (int) $mutasi->stok_lembar;
            $kubikasi = (float) $mutasi->stok_kubikasi;

            if ($qty <= 0) {
                throw new \RuntimeException('Jumlah mutasi tidak valid.');
            }

            // 2. JenisKayu -> JenisBarang (tidak ada relasi di model, dipetakan lewat nama).
            $nama = $mutasi->jenisKayu?->nama_kayu;
            $jenisBarang = $nama ? JenisBarang::where('nama_jenis_barang', $nama)->first() : null;

            if (! $jenisBarang) {
                throw new \RuntimeException("Jenis barang \"{$nama}\" tidak ditemukan di master Jenis Barang. Samakan namanya dulu.");
            }

            $ukuran = [
                'panjang' => $mutasi->panjang,
                'lebar' => $mutasi->lebar,
                'tebal' => $mutasi->tebal,
                'kw_grade' => $mutasi->kw_grade,
            ];
            $keySumber = ['id_jenis_kayu' => $mutasi->id_jenis_kayu] + $ukuran;
            $keyTujuan = ['id_jenis_barang' => $jenisBarang->id] + $ukuran;

            // 3. Kunci stok sumber.
            $sumber = StokPlatformMth::where($keySumber)->lockForUpdate()->first();

            if (! $sumber || (int) $sumber->stok_lembar < $qty) {
                throw new \RuntimeException(
                    'Stok Platform Mentah tidak cukup. Tersedia: '.($sumber?->stok_lembar ?? 0)." lembar, diminta: {$qty}."
                );
            }

            // 4. Nilai yang pindah proporsional terhadap nilai stok sumber.
            $nilaiPindah = (float) $sumber->stok_lembar > 0
                ? round((float) $sumber->nilai_stok * $qty / (float) $sumber->stok_lembar, 2)
                : 0.0;

            // 5. Kunci / buat stok tujuan.
            $tujuan = StokPlatformJadi::where($keyTujuan)->lockForUpdate()->first()
                ?? StokPlatformJadi::create($keyTujuan + [
                    'stok_lembar' => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok' => 0,
                    'hpp_average' => 0,
                    'hpp_pekerja_last' => 0,
                    'hpp_bahan_penolong_last' => 0,
                ]);

            $user = Auth::user()?->name ?? 'System';
            // Akun yang mencatat barang keluar (platform_mth_mutasi_keluars.dikeluarkan_by).
            $pengeluar = $mutasi->operator?->name ?? ($st->diserahkan_oleh ?: '-');
            $keterangan = "Mutasi Platform Mentah -> Platform Jadi (serah terima #{$st->id}, mutasi #{$mutasi->id}), dikeluarkan oleh {$pengeluar}, diterima oleh {$user}";

            // ── SUMBER (Platform Mentah): keluar ──
            $sBefore = [(int) $sumber->stok_lembar, (float) $sumber->stok_kubikasi, (float) $sumber->nilai_stok];

            $sumber->stok_lembar = $sBefore[0] - $qty;
            $sumber->stok_kubikasi = $sumber->stok_lembar === 0 ? 0 : max(0, $sBefore[1] - $kubikasi);
            $sumber->nilai_stok = $sumber->stok_lembar === 0 ? 0 : max(0, $sBefore[2] - $nilaiPindah);
            $sumber->save();

            $logSumber = HppPlatformMthLog::create($keySumber + [
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'keluar',
                'keterangan' => $keterangan,
                'referensi_type' => PlatformMthMutasiKeluar::class,
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

            // ── TUJUAN (Platform Jadi): masuk ──
            $tBefore = [(int) $tujuan->stok_lembar, (float) $tujuan->stok_kubikasi, (float) $tujuan->nilai_stok];
            $lembarAfter = $tBefore[0] + $qty;

            $hppAfter = $lembarAfter > 0
                ? (($tBefore[0] * (float) $tujuan->hpp_average) + ($qty * (float) $sumber->hpp_average)) / $lembarAfter
                : 0;

            $tujuan->stok_lembar = $lembarAfter;
            $tujuan->stok_kubikasi = round($tBefore[1] + $kubikasi, 6);
            $tujuan->nilai_stok = $tBefore[2] + $nilaiPindah;
            $tujuan->hpp_average = $hppAfter;
            $tujuan->save();

            $logTujuan = HppPlatformJadiLog::create($keyTujuan + [
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

            // 6. Tandai diterima (di transaksi yang sama).
            $st->update([
                'diterima_oleh' => $user.' - Gudang Platform Jadi',
                'status' => 'Terima Gudang Platform Jadi',
                'diterima_gudang_at' => now(),
                'diterima_gudang_oleh' => $user,
            ]);
        });
    }
}
