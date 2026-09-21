<?php

namespace App\Filament\Pages\LaporanSanding\Queries;

use App\Models\ProduksiSanding;

class LoadLaporanSanding
{
    public const RELASI = [
        'mesin',
        'pegawaiSandings.pegawai',
        'hasilSandings.barangSetengahJadi.ukuran',
        'hasilSandings.barangSetengahJadi.grade.kategoriBarang',
        'hasilSandings.barangSetengahJadi.jenisBarang',
    ];

    public static function run(string $tgl)
    {
        return ProduksiSanding::with(self::RELASI)
            ->whereDate('tanggal', $tgl)
            ->get();
    }
}