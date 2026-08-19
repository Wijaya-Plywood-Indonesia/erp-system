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

            // Load modal (bahan baku veneer SEBELUM di-join) — dipakai
            // hanya untuk referensi bahan, TIDAK untuk hitung target/potongan.
            'modalJoint.ukuran',
            'modalJoint.jenisKayu',

            // Load hasil (ukuran SETELAH di-join) — ini yang dipakai untuk
            // menentukan kode ukuran, cari Target, dan hitung potongan.
            'hasilJoint.ukuran',
            'hasilJoint.jenisKayu',
        ])
            ->whereDate('tanggal_produksi', $tgl)
            ->get();
    }
}