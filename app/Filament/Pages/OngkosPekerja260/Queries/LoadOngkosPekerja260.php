<?php

namespace App\Filament\Pages\LaporanHarian\Services;

use App\Models\ProduksiRotary;
use Illuminate\Database\Eloquent\Collection;

class LoadOngkosPekerja260
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
            ->where(function ($query) {
                $query->whereHas('mesin', function ($q) {
                    $q->where('nama_mesin', 'LIKE', '%SPINDLESS%')
                        ->orWhere('nama_mesin', 'LIKE', '%MERANTI%');
                });
            })
            ->orderBy('tgl_produksi', 'asc')
            ->get();
    }
}
