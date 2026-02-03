<?php

namespace App\Filament\Resources\ProduksiDempuls\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiDempul;
use App\Models\DetailDempul;

class ProduksiDempulSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-dempul.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiDempul $record = null;
    public array $summary = [];

    /**
     * Listener untuk mendengarkan broadcast 'dempul'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.dempul.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiDempul $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi Refresh Data Real-time
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL KESELURUHAN (HASIL)
        $totalAll = DetailDempul::where('id_produksi_dempul', $produksiId)
            ->sum(DB::raw('CAST(hasil AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (HEADCOUNT)
        // Karena Many-to-Many, kita ambil ID pegawai unik dari tabel pivot
        $totalPegawai = DB::table('detail_dempul_pegawai')
            ->join('detail_dempuls', 'detail_dempuls.id', '=', 'detail_dempul_pegawai.id_detail_dempul')
            ->where('detail_dempuls.id_produksi_dempul', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = DetailDempul::query()
            ->where('detail_dempuls.id_produksi_dempul', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'detail_dempuls.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = (clone $baseQuery)
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,  
                SUM(CAST(detail_dempuls.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_dempuls.hasil AS UNSIGNED)) AS total
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
