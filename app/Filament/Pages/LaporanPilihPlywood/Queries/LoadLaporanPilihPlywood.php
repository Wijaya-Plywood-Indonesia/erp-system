<?php

namespace App\Filament\Pages\LaporanPilihPlywood\Queries;

use App\Models\ProduksiPilihPlywood;

class LoadLaporanPilihPlywood
{
    public const RELASI = [
        'pegawaiPilihPlywood.pegawai',
        'hasilPilihPlywood.pegawais',
        'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
        'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
        'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
    ];

    public static function run(string $tgl)
    {
        return ProduksiPilihPlywood::with(self::RELASI)
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}