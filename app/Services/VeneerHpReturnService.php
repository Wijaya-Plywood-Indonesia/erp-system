<?php

namespace App\Services;

use App\Models\HppVeneerJadiLog;
use App\Models\StokVeneerJadi;
use App\Models\User;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Illuminate\Support\Facades\DB;

/**
 * 🌟 BARU
 *
 * Menangani pengembalian sisa veneer yang sudah diterima Hotpress tapi
 * ternyata tidak semuanya terpakai di "Bahan Hot Press" (bahan_hotpress),
 * balik menjadi stok di Gudang Veneer Jadi.
 *
 * Dipisah sebagai service sendiri (bukan menambah method ke
 * StokVeneerJadiService yang sudah ada) supaya tidak berisiko konflik
 * dengan logic lain yang sudah berjalan di service tersebut.
 */
class VeneerHpReturnService
{
    /**
     * Kembalikan sejumlah lembar dari satu palet veneer ke stok Gudang
     * Veneer Jadi.
     *
     * Alur kebalikan dari aksi "TERIMA" di tab Serah Terima: stok
     * bertambah lagi, dicatat di HppVeneerJadiLog (tipe 'masuk'), dan
     * `jumlah_dikembalikan` pada palet bertambah supaya `sisa` ikut
     * berkurang (mencegah retur dobel / retur melebihi sisa yang benar).
     *
     * Palet itu sendiri TIDAK dibuka kuncinya (`diterima_by` tetap
     * terisi) karena retur ini bisa parsial.
     */
    public function kembalikanSisa(
        VeneerJadiMutasiKeluarPalet $palet,
        float $jumlah,
        ?int $userId = null,
    ): StokVeneerJadi {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah yang dikembalikan harus lebih dari 0.');
        }

        return DB::transaction(function () use ($palet, $jumlah, $userId) {
            $palet = VeneerJadiMutasiKeluarPalet::lockForUpdate()->findOrFail($palet->id);
            $mutasi = $palet->mutasiKeluar;

            if (! $mutasi) {
                throw new \RuntimeException('Data mutasi keluar veneer jadi tidak ditemukan.');
            }

            if (is_null($palet->diterima_by)) {
                throw new \RuntimeException('Palet ini belum pernah diterima di Hotpress.');
            }

            // Hitung ulang sisa di dalam lock supaya tidak race condition
            // dengan input "Bahan Hot Press" / retur lain yang berjalan
            // bersamaan.
            $terpakai = (float) $palet->pemakaianHotpress()->sum('isi');
            $sisa = (float) $palet->jumlah_lembar - $terpakai - (float) $palet->jumlah_dikembalikan;

            if ($jumlah > $sisa) {
                throw new \RuntimeException(
                    'Jumlah melebihi sisa yang tersedia. Sisa saat ini: '.(int) round($sisa).' lembar.'
                );
            }

            $idJenisKayu = (int) $mutasi->id_jenis_kayu;
            $panjang = $mutasi->panjang;
            $lebar = $mutasi->lebar;
            $tebal = $mutasi->tebal;
            $kwGrade = (string) $mutasi->kw_grade;

            $kubikasi = ((float) $panjang * (float) $lebar * (float) $tebal * $jumlah) / 10000000;

            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->lockForUpdate()
                ->first();

            if (! $stok) {
                throw new \RuntimeException('Baris stok Veneer Jadi untuk kombinasi ini tidak ditemukan.');
            }

            $userName = $userId ? (User::find($userId)?->name ?? 'System') : 'System';

            $keterangan = "Retur sisa dari Hotpress — Palet #{$palet->nomor_palet} — "
                .(int) round($jumlah)." lbr oleh {$userName}";

            $stokLembarBefore = $stok->stok_lembar;
            $stokKubikasiBefore = $stok->stok_kubikasi;
            $nilaiStokBefore = $stok->nilai_stok;
            $hppAverage = $stok->hpp_average ?? 0.0;

            $nilaiTransaksi = $kubikasi * $hppAverage;

            $stokLembarAfter = $stokLembarBefore + $jumlah;
            $stokKubikasiAfter = $stokKubikasiBefore + $kubikasi;
            $nilaiStokAfter = $nilaiStokBefore + $nilaiTransaksi;

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
                'kw_grade' => $kwGrade,
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'masuk',
                'keterangan' => $keterangan,
                'referensi_type' => VeneerJadiMutasiKeluarPalet::class,
                'referensi_id' => $palet->id,
                'total_lembar' => $jumlah,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $hppAverage,
                'nilai_stok' => $nilaiTransaksi,
                'stok_lembar_before' => $stokLembarBefore,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'nilai_stok_before' => $nilaiStokBefore,
                'stok_lembar_after' => $stokLembarAfter,
                'stok_kubikasi_after' => $stokKubikasiAfter,
                'nilai_stok_after' => $nilaiStokAfter,
            ]);

            $stok->update([
                'stok_lembar' => $stokLembarAfter,
                'stok_kubikasi' => $stokKubikasiAfter,
                'nilai_stok' => $nilaiStokAfter,
                'id_last_log' => $log->id,
            ]);

            $palet->update([
                'jumlah_dikembalikan' => (float) $palet->jumlah_dikembalikan + $jumlah,
            ]);

            return $stok->fresh();
        });
    }
}
