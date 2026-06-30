<?php

namespace App\Filament\Pages\LaporanProduksi\Queries;

use App\Models\ProduksiStik;

class LoadProduksiStik
{
    public static function run(string $tanggal)
    {
        $start = $tanggal . ' 00:00:00';
        $end = $tanggal . ' 23:59:59';
        return ProduksiStik::with([
            'detailPegawaiStik.pegawai:id,kode_pegawai,nama_pegawai',
        ])
        ->whereDate('tanggal_produksi', $tanggal)
        ->get();
    }
}
