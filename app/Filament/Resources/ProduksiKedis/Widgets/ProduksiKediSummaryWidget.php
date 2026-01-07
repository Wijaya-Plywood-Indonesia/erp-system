<?php

namespace App\Filament\Resources\ProduksiKedis\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiKedi;
use App\Models\DetailBongkarKedi;

class ProduksiKediSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-kedis.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiKedi $record = null;

    public array $summary = [];

    public function mount(?ProduksiKedi $record = null): void
    {
        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // TOTAL KESELURUHAN
        // ======================
        $totalAll = DetailBongkarKedi::where('id_produksi_kedi', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ======================
        // GLOBAL UKURAN + KW
        // ======================
        $globalUkuranKw = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_bongkar_kedi.kw,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_bongkar_kedi.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_bongkar_kedi.kw')
            ->get();

        // ======================
        // GLOBAL UKURAN (SEMUA KW)
        // ======================
        $globalUkuran = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}
