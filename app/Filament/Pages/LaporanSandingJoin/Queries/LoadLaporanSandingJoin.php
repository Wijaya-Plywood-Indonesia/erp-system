<?php

namespace App\Filament\Pages\LaporanSandingJoin\Queries;

use App\Models\ProduksiSandingJoint;

class LoadLaporanSandingJoin
{
    public static function run(string $tgl)
    {
        return ProduksiSandingJoint::with([
            // Load Pegawai dan Master Pegawai
            'pegawaiSandingJoint.pegawai',

            // Load Hasil Produksi dan Master Spesifikasinya
            'hasilSandingJoint.ukuran',
            'hasilSandingJoint.jenisKayu',

            // Load Validasi jika diperlukan summary di masa depan
            'validasiTerakhir'
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}
