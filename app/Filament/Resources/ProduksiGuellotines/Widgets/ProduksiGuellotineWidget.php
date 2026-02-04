<?php

namespace App\Filament\Resources\ProduksiGuellotines\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\produksi_guellotine;
use App\Models\hasil_guellotine;
use App\Models\pegawai_guellotine;

class ProduksiGuellotineSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-guellotine.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?produksi_guellotine $record = null;

    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Guellotine
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            // Mendengarkan channel production.guellotine.{id}
            "echo:production.guellotine.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?produksi_guellotine $record = null): void
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

        // 1. TOTAL HASIL (SUM JUMLAH)
        $totalAll = hasil_guellotine::where('id_produksi_guellotine', $produksiId)
            ->sum('jumlah');

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = pegawai_guellotine::where('id_produksi_guellotine', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');


        // ======================
        // 3. GLOBAL UKURAN + (satuan kualitas[hasil_kayu|kw|grade])
        // ======================
        $globalUkuranKayu = hasil_guellotine::query()
            ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_guellotine.id_jenis_kayu')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran_label,
                jenis_kayus.nama_kayu AS jenis_kayu_label,
                SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS jumlah
            ')
            ->groupBy('ukuran_label', 'jenis_kayu_label')
            ->orderBy('jenis_kayu_label')
            ->get()
            ->toArray();

        // ======================
        // 4. GLOBAL UKURAN (SEMUA satuan kualitas[hasil_kayu|kw|grade])
        // ======================
        $globalUkuran = hasil_guellotine::query()
            ->where('id_produksi_guellotine', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
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
                hasil_guellotine.kw,
                SUM(hasil_guellotine.jumlah) AS total
            ')
            ->groupBy('ukuran', 'hasil_guellotine.kw')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (TOTAL SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(hasil_guellotine.jumlah) AS total')
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
