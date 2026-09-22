<?php

namespace App\Filament\Pages\LaporanNyusup\Queries;

use App\Models\ProduksiNyusup;

class LoadLaporanNyusup
{
    public const RELASI = [
        'pegawaiNyusup.pegawai',
        'detailBarangDikerjakan.barangSetengahJadiHp.ukuran',
        'detailBarangDikerjakan.barangSetengahJadiHp.grade.kategoriBarang',
        'detailBarangDikerjakan.barangSetengahJadiHp.jenisBarang',
    ];

    public static function run(string $tgl)
    {
        return ProduksiNyusup::with(self::RELASI)
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}