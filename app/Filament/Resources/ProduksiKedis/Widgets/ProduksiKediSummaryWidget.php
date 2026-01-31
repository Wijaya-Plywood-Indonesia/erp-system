<?php

namespace App\Filament\Resources\ProduksiKedis\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiKedi;
use App\Models\DetailBongkarKedi;
use App\Models\DetailPegawaiKedi;

class ProduksiKediSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-kedis.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiKedi $record = null;

    public array $summary = [];

    /**
     * LANGKAH 1: Listener untuk menangkap sinyal 'kedi'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            "echo:production.kedi.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiKedi $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 2: Fungsi refresh untuk update data real-time
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL HASIL PRODUKSI
        $totalAll = DetailBongkarKedi::where('id_produksi_kedi', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = DetailPegawaiKedi::where('id_produksi_kedi', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar
        $baseQuery = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
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
                detail_bongkar_kedi.kw,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_bongkar_kedi.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_bongkar_kedi.kw')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total')
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
