<?php

namespace App\Filament\Pages\LaporanProduksi\Queries;

use App\Models\ProduksiRotary;

class LoadProduksi
{
    public static function run(string $tgl)
    {
        return ProduksiRotary::with([
            'mesin:id,nama_mesin',
            'detailPegawaiRotary.pegawai:id,kode_pegawai,nama_pegawai',
            'detailPaletRotary:id,id_produksi,id_ukuran,total_lembar',
            'kendalaRotaries',
        ])
            ->whereDate('tgl_produksi', $tgl)
            ->get();
    }
}
