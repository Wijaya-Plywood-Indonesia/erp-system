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

    /**
     * LANGKAH 1: Tambahkan Listener Echo/Pusher
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id)
            return [];

        return [
            // Gunakan identitas 'dryer' sesuai yang di-dispatch di Model
            "echo:production.dryer.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    /**
     * LANGKAH 2: Modifikasi mount
     */
    public function mount(?ProduksiPressDryer $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 3: Pindahkan logika query ke refreshSummary
     */
    public function refreshSummary(): void
    {
        if (!$this->record)
            return;

        $produksiId = $this->record->id;

        // 1. TOTAL PRODUKSI
        $totalAll = DetailHasil::where('id_produksi_dryer', $produksiId)
            ->sum(DB::raw('CAST(isi AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = DetailPegawai::where('id_produksi_dryer', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = DetailHasil::query()
            ->where('detail_hasils.id_produksi_dryer', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = (clone $baseQuery)
            ->selectRaw('
                detail_hasils.kw,
                SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_hasils.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_hasils.kw')
            ->get();

        // 4. GLOBAL UKURAN
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll' => $totalAll,
            'totalPegawai' => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran' => $globalUkuran,
        ];
    }
}
