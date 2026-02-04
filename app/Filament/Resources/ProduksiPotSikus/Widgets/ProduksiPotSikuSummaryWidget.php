<?php

namespace App\Filament\Resources\ProduksiPotSikus\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiPotSiku;
use App\Models\DetailBarangDikerjakanPotSiku;
use App\Models\PegawaiPotSiku;

class ProduksiPotSikuSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pot-siku.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPotSiku $record = null;
    public array $summary = [];

    /**
     * LANGKAH 1: Tambahkan Listener Pusher
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id)
            return [];

        return [
            // Mendengarkan channel production.pot_siku.{id}
            "echo:production.pot_siku.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    /**
     * LANGKAH 2: Mount Awal
     */
    public function mount(?ProduksiPotSiku $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 3: Fungsi Refresh (Dijalankan ulang otomatis oleh Pusher)
     */
    public function refreshSummary(): void
    {
        if (!$this->record)
            return;

        $produksiId = $this->record->id;

        // TOTAL PRODUKSI (TINGGI)
        $totalAll = DetailBarangDikerjakanPotSiku::where('id_produksi_pot_siku', $produksiId)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // TOTAL PEGAWAI
        $totalPegawai = PegawaiPotSiku::where('id_produksi_pot_siku', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar untuk Ukuran
        $baseQuery = DetailBarangDikerjakanPotSiku::query()
            ->where('id_produksi_pot_siku', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
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
                detail_barang_dikerjakan_pot_siku.kw,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_barang_dikerjakan_pot_siku.kw')
            ->orderBy('ukuran')
            ->get();

        // GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        //
        $targetPerPegawai = 300; // cm

        $progressPegawai = DetailBarangDikerjakanPotSiku::query()
            ->where('detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku', $produksiId)
            ->join(
                'pegawai_pot_siku',
                'pegawai_pot_siku.id',
                '=',
                'detail_barang_dikerjakan_pot_siku.id_pegawai_pot_siku'
            )
            ->join(
                'pegawais',
                'pegawais.id',
                '=',
                'pegawai_pot_siku.id_pegawai'
            )
            ->selectRaw('
            pegawais.id AS pegawais_id,
            pegawais.nama_pegawai,
            SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total_tinggi
            ')
            ->groupBy('pegawais.id', 'pegawais.nama_pegawai')
            ->get()
            ->map(function ($row) use ($targetPerPegawai) {
                $progress = min(
                    round(($row->total_tinggi / $targetPerPegawai) * 100, 1),
                    100
                );

                return [
                    'pegawais' => $row->nama_pegawai,
                    'total' => $row->total_tinggi,
                    'target' => $targetPerPegawai,
                    'progress' => $progress,
                ];
            });


        $this->summary = [
            'totalAll' => $totalAll,
            'totalPegawai' => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran' => $globalUkuran,
            'progressPegawai' => $progressPegawai,
        ];


    }
}
