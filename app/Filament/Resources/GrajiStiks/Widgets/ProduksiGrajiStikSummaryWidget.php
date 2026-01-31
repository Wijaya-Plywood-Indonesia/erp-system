<?php

namespace App\Filament\Resources\GrajiStiks\Widgets;

use Filament\Widgets\Widget;
use App\Models\GrajiStik;
use App\Models\HasilGrajiStik;
use App\Models\PegawaiGrajiStik;

class ProduksiGrajiStikSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-graji-stik.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?GrajiStik $record = null;
    public array $summary = [];

    /**
     * Listener untuk menangkap sinyal 'graji_stik'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            "echo:production.graji_stik.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?GrajiStik $record = null): void
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
        $totalAll = HasilGrajiStik::where('id_graji_stiks', $produksiId)
            ->sum('hasil_graji');

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = PegawaiGrajiStik::where('id_graji_stiks', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 3. GLOBAL UKURAN (Via Join Modal)
        $globalUkuran = HasilGrajiStik::query()
            ->where('hasil_graji_stiks.id_graji_stiks', $produksiId)
            ->join('modal_graji_stiks', 'modal_graji_stiks.id', '=', 'hasil_graji_stiks.id_modal_graji_stiks')
            ->join('ukurans', 'ukurans.id', '=', 'modal_graji_stiks.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_graji_stiks.hasil_graji) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'      => $totalAll,
            'totalPegawai'  => $totalPegawai,
            'globalUkuran'  => $globalUkuran,
        ];
    }
}
