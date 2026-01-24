<?php

namespace App\Filament\Pages\LaporanPotJelek\Queries;

use App\Models\ProduksiPotJelek;

class LoadLaporanPotJelek
{
    public static function run(string $tgl)
    {
        return ProduksiPotJelek::with([
            // Load detail barang pengerjaan sebagai sumber utama
            'detailBarangDikerjakanPotJelek.ukuran',
            'detailBarangDikerjakanPotJelek.jenisKayu',
            // Dari detail pengerjaan, kita ambil data pegawai-nya
            'detailBarangDikerjakanPotJelek.PegawaiPotJelek.pegawai',
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}
