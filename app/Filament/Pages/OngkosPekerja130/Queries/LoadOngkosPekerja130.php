<?php

namespace App\Filament\Pages\LaporanHarian\Services;

use App\Models\ProduksiRotary;
use Illuminate\Database\Eloquent\Collection;

class LoadOngkosPekerja130
{
    public static function fetch(string $startDate, string $endDate): Collection
    {
        return ProduksiRotary::query()
            ->with([
                'mesin',
                'detailPegawaiRotary.pegawai',
                'detailPaletRotary.ukuran',
                'detailPaletRotary.penggunaanLahan.jenisKayu'
            ])
            ->whereBetween('tgl_produksi', [$startDate, $endDate])
            ->whereHas('mesin', function ($q) {
                // Filter khusus mesin 130
                $q->where('nama_mesin', 'LIKE', '%SANJI%')
                    ->orWhere('nama_mesin', 'LIKE', '%YUEQUN%');
            })
            ->orderBy('tgl_produksi', 'asc')
            ->get();
    }
}
