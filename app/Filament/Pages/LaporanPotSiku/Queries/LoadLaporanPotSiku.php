<?php

namespace App\Filament\Pages\LaporanPotSiku\Queries;

use App\Models\ProduksiPotSiku;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoadLaporanPotSiku
{
    public static function byTanggal(Carbon $tanggal): Collection
    {
        return ProduksiPotSiku::query()
            ->with([
                'pegawaiPotSiku.pegawai',
                'detailBarangDikerjakanPotSiku',
                'detailBarangDikerjakanPotSiku.pegawaiPotSiku.pegawai',
                'validasiTerakhir',
            ])
            ->whereBetween('tanggal_produksi', [
                $tanggal->copy()->startOfDay(),
                $tanggal->copy()->endOfDay(),
            ])
            ->get();
    }
}
