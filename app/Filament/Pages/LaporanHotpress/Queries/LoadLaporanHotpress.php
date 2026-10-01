<?php

namespace App\Filament\Pages\LaporanHotpress\Queries;

use App\Models\ProduksiHp;

class LoadLaporanHotpress
{
    public const RELASI = [
        'detailPegawaiHp.pegawaiHp',
        'platformHasilHp.barangSetengahJadi.ukuran',
        'platformHasilHp.barangSetengahJadi.grade.kategoriBarang',
        'platformHasilHp.barangSetengahJadi.jenisBarang',
        'triplekHasilHp.barangSetengahJadi.ukuran',
        'triplekHasilHp.barangSetengahJadi.grade.kategoriBarang',
        'triplekHasilHp.barangSetengahJadi.jenisBarang',
    ];

    public static function run(string $tgl)
    {
        return ProduksiHp::with(self::RELASI)
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}