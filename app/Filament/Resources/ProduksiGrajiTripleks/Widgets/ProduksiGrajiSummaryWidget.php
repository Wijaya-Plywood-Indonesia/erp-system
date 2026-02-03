<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiGrajitriplek;
use App\Models\HasilGrajiTriplek;

class ProduksiGrajiSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-graji.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiGrajitriplek $record = null;
    public array $summary = [];

    /**
     * Listener untuk mendengarkan broadcast 'graji_triplek'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.graji_triplek.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiGrajitriplek $record = null): void
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

        // 1. TOTAL HASIL
        $totalAll = HasilGrajiTriplek::where('id_produksi_graji_triplek', $produksiId)
            ->sum('isi');

        // 2. TOTAL PEGAWAI
        $totalPegawai = $this->record->pegawaiGrajiTriplek()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar (Ukuran)
        $baseQuery = HasilGrajiTriplek::query()
            ->where('hasil_graji_triplek.id_produksi_graji_triplek', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_graji_triplek.id_barang_setengah_jadi_hp')
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
                SUM(hasil_graji_triplek.isi) AS total
            ')
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN SAJA
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(hasil_graji_triplek.isi) AS total')
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
