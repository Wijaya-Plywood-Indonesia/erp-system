<?php

namespace App\Filament\Pages\LaporanDempul\Queries;

use App\Models\ProduksiDempul;

class LoadLaporanDempul
{
    public const RELASI = [
        'rencanaPegawaiDempuls.pegawai',
        'detailDempuls.pegawais',
        'detailDempuls.barangSetengahJadi.ukuran',
        'detailDempuls.barangSetengahJadi.grade',
        'detailDempuls.barangSetengahJadi.jenisBarang',
    ];

    public static function run(string $tgl)
    {
        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();

        return ProduksiDempul::with(self::RELASI)
            ->whereDate($kolomTanggal, $tgl)
            ->get();
    }
}