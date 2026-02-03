<?php

namespace App\Filament\Resources\ProduksiNyusups\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiNyusup;
use App\Models\DetailBarangDikerjakan;
use App\Models\PegawaiNyusup;

class ProduksiNyusupSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-nyusups.widget.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiNyusup $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Nyusup
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.nyusup.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiNyusup $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi utama untuk update data otomatis
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL PRODUKSI
        $totalAll = DetailBarangDikerjakan::where('id_produksi_nyusup', $produksiId)
            ->sum(DB::raw('CAST(hasil AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = PegawaiNyusup::where('id_produksi_nyusup', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Base Query untuk efisiensi
        $baseQuery = DetailBarangDikerjakan::query()
            ->where('detail_barang_dikerjakan.id_produksi_nyusup', $produksiId)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'detail_barang_dikerjakan.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + GRADE
        $globalUkuranGrade = (clone $baseQuery)
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->selectRaw('
                grades.nama_grade AS kw,
                SUM(CAST(detail_barang_dikerjakan.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA GRADE)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_barang_dikerjakan.hasil AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'          => $totalAll,
            'totalPegawai'      => $totalPegawai,
            'globalUkuranGrade' => $globalUkuranGrade,
            'globalUkuran'      => $globalUkuran,
        ];
    }
}
