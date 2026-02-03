<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiGrajiBalken;
use App\Models\HasilGrajiBalken;
use App\Models\PegawaiGrajiBalken;

class ProduksiGrajiBalkenSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-graji-balken.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiGrajiBalken $record = null;
    public array $summary = [];

    /**
     * Listener untuk menangkap sinyal 'graji_balken'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            "echo:production.graji_balken.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiGrajiBalken $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi utama untuk memperbarui data summary secara real-time
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL HASIL
        $totalAll = HasilGrajiBalken::where('id_produksi_graji_balken', $produksiId)
            ->sum('jumlah');

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = PegawaiGrajiBalken::where('id_produksi_graji_balken', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = HasilGrajiBalken::query()
            ->where('id_produksi_graji_balken', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_graji_balken.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + JENIS KAYU
        $globalUkuranJenis = (clone $baseQuery)
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_graji_balken.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                SUM(hasil_graji_balken.jumlah) AS total
            ')
            ->groupBy('ukuran', 'jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA JENIS KAYU)
        $globalUkuranSemua = (clone $baseQuery)
            ->selectRaw('SUM(hasil_graji_balken.jumlah) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'          => $totalAll,
            'totalPegawai'      => $totalPegawai,
            'globalUkuranJenis' => $globalUkuranJenis,
            'globalUkuranSemua' => $globalUkuranSemua,
        ];
    }
}
