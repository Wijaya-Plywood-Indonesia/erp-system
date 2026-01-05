<?php

namespace App\Filament\Resources\ProduksiStiks\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiStik;
use App\Models\DetailHasilStik;
use App\Models\DetailPegawaiStik;

class ProduksiStikSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-stik.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiStik $record = null;

    public array $summary = [];

    public function mount(?ProduksiStik $record = null): void
    {
        if (! $record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // 1. TOTAL PRODUKSI (LEMBAR)
        // ======================
        $totalAll = DetailHasilStik::where('id_produksi_stik', $produksiId)
            ->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // ======================
        // 2. TOTAL PEGAWAI (UNIK)
        // ======================
        $totalPegawai = DetailPegawaiStik::where('id_produksi_stik', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ======================
        // 3. GLOBAL UKURAN + KW
        // ======================
        $globalUkuranKw = DetailHasilStik::query()
            ->where('id_produksi_stik', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_stik.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasil_stik.kw,
                SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_hasil_stik.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_hasil_stik.kw')
            ->get();

        // ======================
        // 4. GLOBAL UKURAN (SEMUA KW)
        // ======================
        $globalUkuran = DetailHasilStik::query()
            ->where('id_produksi_stik', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_stik.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}
