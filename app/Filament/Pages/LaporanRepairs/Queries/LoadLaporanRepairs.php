<?php

namespace App\Filament\Pages\LaporanRepairs\Queries;

use App\Models\ProduksiRepair;

class LoadLaporanRepairs
{
    public static function run(string $tgl)
    {
        return ProduksiRepair::with([
            // Relasi modal repair
            'modalRepairs.ukuran',
            'modalRepairs.jenisKayu',
            'modalRepairs.rencanaRepairs.hasilRepairs',

            // Relasi pekerja
            'rencanaPegawais.pegawai',
            'rencanaPegawais.rencanaRepairs.hasilRepairs',

            // Relasi bahan penolong
            'bahanPenolongRepair.bahanPenolong',

        ])
            ->whereDate('tanggal', $tgl)
            ->get();
    }
}