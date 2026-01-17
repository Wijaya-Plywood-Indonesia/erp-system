<?php

namespace App\Filament\Resources\ProduksiPotSikus\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiPotSiku;
use App\Models\DetailBarangDikerjakanPotSiku;
use App\Models\PegawaiPotSiku;

class ProduksiPotSikuSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pot-siku.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPotSiku $record = null;

    public array $summary = [];

    public function mount(?ProduksiPotSiku $record = null): void
    {
        if (! $record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // TOTAL PRODUKSI (TINGGI)
        // ======================
        $totalAll = DetailBarangDikerjakanPotSiku::where('id_produksi_pot_siku', $produksiId)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // ======================
        // TOTAL PEGAWAI
        // ======================
        $totalPegawai = PegawaiPotSiku::where('id_produksi_pot_siku', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ======================
        // GLOBAL UKURAN + KW (TINGGI)
        // ======================
        $globalUkuranKw = DetailBarangDikerjakanPotSiku::query()
            ->where('id_produksi_pot_siku', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_barang_dikerjakan_pot_siku.kw,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_barang_dikerjakan_pot_siku.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_barang_dikerjakan_pot_siku.kw')
            ->get();

        // ======================
        // GLOBAL UKURAN (SEMUA KW) - TINGGI
        // ======================
        $globalUkuran = DetailBarangDikerjakanPotSiku::query()
            ->where('id_produksi_pot_siku', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'        => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}
