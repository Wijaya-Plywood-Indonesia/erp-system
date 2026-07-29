<?php

namespace App\Filament\Pages\LaporanRepairs\Queries;

use App\Models\ProduksiRepair;

class LoadLaporanRepairs
{
    public static function run(string $tgl)
    {
        return ProduksiRepair::with([
            // Eager load langsung ke Detail Hasil Repair beserta semua relasinya
            'detailHasilRepairs.ukuran',
            'detailHasilRepairs.modalRepair.jenisKayu',
            'detailHasilRepairs.rencanaPegawais.pegawai',

            // Relasi pendukung laporan
            'rencanaPegawais.pegawai',
            'bahanPenolongRepair.bahanPenolong',
        ])
            ->whereDate('tanggal', $tgl)
            ->get();
    }
}
