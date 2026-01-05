<?php

namespace App\Filament\Resources\ProduksiPressDryers\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiPressDryer;
use App\Models\DetailHasil;
use App\Models\DetailPegawai;

class ProduksiPressDryerSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-press-dryers.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPressDryer $record = null;

    public array $summary = [];

    public function mount(?ProduksiPressDryer $record = null): void
    {
        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        /**
         * ======================
         * 1. TOTAL PRODUKSI
         * ======================
         */
        $totalAll = DetailHasil::where('id_produksi_dryer', $produksiId)
            ->sum(DB::raw('CAST(isi AS UNSIGNED)'));

        /**
         * ======================
         * 2. TOTAL PEGAWAI (UNIK)
         * ======================
         * Ambil dari tabel detail_pegawais
         */
        $totalPegawai = DetailPegawai::where('id_produksi_dryer', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        /**
         * ======================
         * 3. GLOBAL UKURAN + KW
         * ======================
         */
        $globalUkuranKw = DetailHasil::query()
            ->where('detail_hasils.id_produksi_dryer', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasils.kw,
                SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_hasils.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_hasils.kw')
            ->get();

        /**
         * ======================
         * 4. GLOBAL UKURAN
         * ======================
         */
        $globalUkuran = DetailHasil::query()
            ->where('detail_hasils.id_produksi_dryer', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
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
