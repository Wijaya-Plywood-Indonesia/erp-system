<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiPotJelek;
use App\Models\DetailBarangDikerjakanPotJelek;
use App\Models\PegawaiPotJelek;

class ProduksiPotJelekSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pot-jelek.widget.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPotJelek $record = null;

    public array $summary = [];

    /**
     * Listener untuk menangkap broadcast dari Pusher
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (! $id) return [];

        return [
            // Sesuai dengan tipe 'pot_jelek' di model Anda
            "echo:production.pot_jelek.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiPotJelek $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi utama untuk load data (Real-time)
     */
    public function refreshSummary(): void
    {
        if (! $this->record) return;

        $produksiId = $this->record->id;

        // TOTAL PRODUKSI (TINGGI)
        $totalAll = DetailBarangDikerjakanPotJelek::where('id_produksi_pot_jelek', $produksiId)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // TOTAL PEGAWAI
        $totalPegawai = PegawaiPotJelek::where('id_produksi_pot_jelek', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = DetailBarangDikerjakanPotJelek::query()
            ->where('id_produksi_pot_jelek', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_jelek.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // GLOBAL UKURAN + KW
        $globalUkuranKw = (clone $baseQuery)
            ->selectRaw('
                detail_barang_dikerjakan_pot_jelek.kw,
                SUM(CAST(detail_barang_dikerjakan_pot_jelek.tinggi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_barang_dikerjakan_pot_jelek.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_barang_dikerjakan_pot_jelek.kw')
            ->get();

        // GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_barang_dikerjakan_pot_jelek.tinggi AS UNSIGNED)) AS total')
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
