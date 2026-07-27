<?php

namespace App\Services;

use App\Models\DetailHasilRepair;
use App\Models\GudangVeneerJadi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetailHasilRepairService
{
    /**
     * Memproses serah terima data hasil repair ke Gudang Veneer Jadi
     */
    public function serahKeGudang(DetailHasilRepair $record): void
    {
        $totalHasil = (int) $record->jumlah;

        if ($totalHasil <= 0) {
            throw new \Exception('Gagal: Hasil Produksi masih 0 lembar.');
        }

        DB::transaction(function () use ($record, $totalHasil) {
            // Load relasi ukuran & modal repair
            $record->load(['ukuran', 'modalRepair.jenisKayu']);

            $ukuran = $record->ukuran;
            if (! $ukuran) {
                throw new \Exception("Gagal: Dimensi ukuran untuk Hasil Repair ID #{$record->id} tidak ditemukan.");
            }

            $idJenisKayu = $record->modalRepair?->id_jenis_kayu ?? $record->id_jenis_kayu;
            $tanggalProduksi = $record->produksiRepair?->tanggal ?? now()->toDateString();
            $idProduksi = $record->id_produksi_repair;

            // 1. Eksekusi pencatatan ke gudang
            $this->prosesMasukGudangUtama(
                idJenisKayu: $idJenisKayu,
                panjang: $ukuran->panjang,
                lebar: $ukuran->lebar,
                tebal: $ukuran->tebal,
                kwGrade: $record->kw,
                totalLembar: $totalHasil,
                tanggalProduksi: $tanggalProduksi,
                keteranganProduksi: "Serah Terima Hasil Repair ID #{$record->id}",
                idProduksiRepair: $idProduksi
            );

            // 2. Update status diserahkan pada record hasil repair
            $record->update([
                'diserahkan_at' => now(),
                'diserahkan_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Helper internal kalkulasi kubikasi dan HPP gudang
     */
    protected function prosesMasukGudangUtama(
        $idJenisKayu,
        $panjang,
        $lebar,
        $tebal,
        $kwGrade,
        $totalLembar,
        $tanggalProduksi,
        $keteranganProduksi = null,
        $idProduksiRepair = null
    ): GudangVeneerJadi {
        $volumePerLembar = ($panjang * $lebar * $tebal) / 10000000;
        $totalKubikasiMasuk = $totalLembar * $volumePerLembar;

        $hppPekerjaMasuk = 0;
        $hppBahanPenolongMasuk = 0;
        $hppAverageMasuk = $hppPekerjaMasuk + $hppBahanPenolongMasuk;
        $nilaiStokMasuk = $totalLembar * $hppAverageMasuk;

        return GudangVeneerJadi::create([
            'id_stok_veneer_jadi'     => null,
            'id_jenis_kayu'           => $idJenisKayu,
            'panjang'                 => $panjang,
            'lebar'                   => $lebar,
            'tebal'                   => $tebal,
            'kw_grade'                => $kwGrade,
            'stok_lembar'             => $totalLembar,
            'stok_kubikasi'           => $totalKubikasiMasuk,
            'nilai_stok'              => $nilaiStokMasuk,
            'hpp_average'             => $hppAverageMasuk,
            'hpp_pekerja_last'        => $hppPekerjaMasuk,
            'hpp_bahan_penolong_last' => $hppBahanPenolongMasuk,
            'id_last_log'             => null,
            'tanggal_produksi'        => $tanggalProduksi,
            'status_gudang'           => 'belum diterima',
            'keterangan'              => $keteranganProduksi ?? "Dari Produksi Repair #{$idProduksiRepair}",
            'diterima_by'             => null,
            'diterima_at'             => null,
        ]);
    }
}
