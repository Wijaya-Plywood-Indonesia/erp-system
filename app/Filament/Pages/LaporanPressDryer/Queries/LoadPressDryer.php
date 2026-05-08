<?php

namespace App\Filament\Pages\LaporanPressDryer\Queries;

use App\Models\ProduksiPressDryer;

class LoadPressDryer
{
    public static function run(string $tanggal)
    {
        $start = $tanggal . ' 00:00:00';
        $end   = $tanggal . ' 23:59:59';

        return ProduksiPressDryer::with([
            'detailPegawais.pegawai:id,kode_pegawai,nama_pegawai',

            // Detail hasil + relasi ukuran & jenis kayu (untuk sheet Hasil Produksi)
            'detailHasils:id,id_produksi_dryer,no_palet,kw,isi,id_ukuran,id_jenis_kayu',
            'detailHasils.ukuran:id,panjang,lebar,tebal',
            'detailHasils.jenisKayu:id,kode_kayu',  // ← sesuaikan nama kolom di tabel jenis_kayus

            // Detail masuk
            'detailMasuks:id,id_produksi_dryer',

            // Mesin
            'detailMesins:id,id_produksi_dryer,id_mesin_dryer',
            'detailMesins.mesin:id,nama_mesin',
            'detailMesins.kategoriMesin:id,nama_kategori_mesin',
        ])
            ->select('id', 'tanggal_produksi', 'shift', 'kendala')
            ->whereBetween('tanggal_produksi', [$start, $end])
            ->orderBy('shift', 'asc')
            ->get();
    }
}