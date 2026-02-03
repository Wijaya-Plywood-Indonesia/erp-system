<?php

namespace App\Filament\Resources\ProduksiSandings\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiSanding;
use App\Models\HasilSanding;

class ProduksiSandingSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-sanding.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiSanding $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Sanding
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.sanding.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiSanding $record = null): void
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

        // 1. TOTAL KUANTITAS
        $totalAll = HasilSanding::where('id_produksi_sanding', $produksiId)
            ->sum('kuantitas');

        // 2. TOTAL PEGAWAI
        $totalPegawai = $this->record->pegawaiSandings()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + KATEGORI + GRADE
        $globalUkuranKw = (clone $baseQuery)
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN SAJA
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(hasil_sandings.kuantitas) AS total')
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
