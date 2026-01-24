<?php

namespace App\Filament\Pages\LaporanPotJelek\Queries;

use App\Models\ProduksiPotJelek;

class LoadLaporanPotJelek
{
    public static function run(string $tgl)
    {
        return ProduksiPotJelek::with([
            // Load detail pegawai (Sesuai relasi di Model ProduksiPotJelek)
            'pegawaiPotJelek.pegawai',

            // Load hasil pengerjaan (Sesuai relasi di Model ProduksiPotJelek)
            'detailBarangDikerjakanPotJelek.ukuran',
            'detailBarangDikerjakanPotJelek.jenisKayu',

            // Load validasi (Opsional, jika ingin menampilkan status validasi)
            'validasiTerakhir'
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}
