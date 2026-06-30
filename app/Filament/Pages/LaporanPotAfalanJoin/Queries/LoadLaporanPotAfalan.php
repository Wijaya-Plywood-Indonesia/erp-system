<?php

namespace App\Filament\Pages\LaporanPotAfalanJoin\Queries;

use App\Models\ProduksiPotAfJoint;

class LoadLaporanPotAfalan
{
    public static function run(string $tgl)
    {
        return ProduksiPotAfJoint::with([
            'pegawaiPotAfJoint.pegawai',
            'hasilPotAfJoint.ukuran',
            'hasilPotAfJoint.jenisKayu',
            'validasiTerakhir'
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}
