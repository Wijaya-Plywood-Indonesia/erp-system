<?php

namespace App\Filament\Pages\LaporanJoin\Queries;

use App\Models\ProduksiJoint;

class LoadLaporanJoin
{
    public static function run(string $tgl)
    {
        return ProduksiJoint::with([
            // Load detail pegawai (sesuai model PegawaiJoint Anda)
            'pegawaiJoint.pegawai',

            // Load modal (sesuai model ModalJoint Anda)
            'modalJoint.ukuran',
            'modalJoint.jenisKayu',

            // Load hasil (sesuai relasi di model ProduksiJoint)
            'hasilJoint.ukuran',
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}
