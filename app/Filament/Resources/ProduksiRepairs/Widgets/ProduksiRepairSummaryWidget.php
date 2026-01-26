<?php

namespace App\Filament\Resources\ProduksiRepairs\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiRepair;
use App\Models\HasilRepair;

class ProduksiRepairSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-repairs.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiRepair $record = null;

    public array $summary = [];

    public function mount(?ProduksiRepair $record = null): void
    {
        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = HasilRepair::where('id_produksi_repair', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI KESELURUHAN (UNIK)
        $totalPegawai = $record->rencanaPegawais()
            ->get()
            ->unique('id_pegawai')
            ->count();

        // 3. GLOBAL UKURAN + KW + JUMLAH ORANG (INI KUNCINYA)
        $globalUkuranKw = HasilRepair::query()
            ->where('hasil_repairs.id_produksi_repair', $produksiId)
            ->join('rencana_repairs', 'rencana_repairs.id', '=', 'hasil_repairs.id_rencana_repair')
            ->join('rencana_pegawais', 'rencana_pegawais.id', '=', 'rencana_repairs.id_rencana_pegawai')
            ->join('modal_repairs', 'modal_repairs.id', '=', 'rencana_repairs.id_modal_repair')
            ->join('ukurans', 'ukurans.id', '=', 'modal_repairs.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                rencana_repairs.kw,
                SUM(CAST(hasil_repairs.jumlah AS UNSIGNED)) AS total,
                COUNT(DISTINCT rencana_pegawais.id_pegawai) AS jumlah_orang
            ')
            ->groupBy('ukuran', 'rencana_repairs.kw')
            ->orderBy('ukuran')
            ->orderBy('rencana_repairs.kw')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
        ];
    }
}
