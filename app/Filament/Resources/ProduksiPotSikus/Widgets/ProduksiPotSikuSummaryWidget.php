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

        if (!$id) {
            return [];
        }

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
     * LANGKAH 3: Fungsi Refresh (Dijalankan ulang otomatis oleh Pusher / Livewire)
     */
    public function refreshSummary(): void
    {
        if (!$this->record) {
            return;
        }

        $produksiId = $this->record->id;

        // TOTAL PRODUKSI (TINGGI)
        $totalAll = DetailBarangDikerjakanPotSiku::where('id_produksi_pot_siku', $produksiId)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // TOTAL VOLUME M3 (p * l * t * tinggi / 1.000.000)
        $totalVolumeM3 = DetailBarangDikerjakanPotSiku::query()
            ->where('id_produksi_pot_siku', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->sum(DB::raw('
                (CAST(ukurans.panjang AS DECIMAL(10,2)) * CAST(ukurans.lebar AS DECIMAL(10,2)) * CAST(ukurans.tebal AS DECIMAL(10,2)) * CAST(detail_barang_dikerjakan_pot_siku.tinggi AS DECIMAL(10,2))) / 1000000
            '));

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
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // GLOBAL UKURAN (SEMUA KW) + Kalkulasi M3 per ukuran
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total,
                SUM(
                    (CAST(ukurans.panjang AS DECIMAL(10,2)) * CAST(ukurans.lebar AS DECIMAL(10,2)) * CAST(ukurans.tebal AS DECIMAL(10,2)) * CAST(detail_barang_dikerjakan_pot_siku.tinggi AS DECIMAL(10,2))) / 1000000
                ) AS total_m³
            ')
            ->groupBy('ukuran', 'ukurans.panjang', 'ukurans.lebar', 'ukurans.tebal')
            ->orderBy('ukuran')
            ->get();

        // TARGET & PROGRESS PEGAWAI (Termasuk pengambilan Keterangan Pegawai)
        $targetPerPegawai = 300; // cm

        $progressPegawai = DetailBarangDikerjakanPotSiku::query()
            ->where('detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku', $produksiId)
            ->join('pegawai_pot_siku', 'pegawai_pot_siku.id', '=', 'detail_barang_dikerjakan_pot_siku.id_pegawai_pot_siku')
            ->join('pegawais', 'pegawais.id', '=', 'pegawai_pot_siku.id_pegawai')
            ->selectRaw('
                pegawais.id AS pegawais_id,
                pegawais.nama_pegawai,
                pegawai_pot_siku.ket AS keterangan_pegawai,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total_tinggi
            ')
            ->groupBy('pegawais.id', 'pegawais.nama_pegawai', 'pegawai_pot_siku.ket')
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
                    'keterangan' => $row->keterangan_pegawai,
                ];
            });

        // GLOBAL JENIS KAYU & UKURAN + Kalkulasi M3
        $globalJenisKayuUkuran = DetailBarangDikerjakanPotSiku::query()
            ->where('id_produksi_pot_siku', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_barang_dikerjakan_pot_siku.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_barang_dikerjakan_pot_siku.kw as kw,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total,
                SUM(
                    (CAST(ukurans.panjang AS DECIMAL(10,2)) * CAST(ukurans.lebar AS DECIMAL(10,2)) * CAST(ukurans.tebal AS DECIMAL(10,2)) * CAST(detail_barang_dikerjakan_pot_siku.tinggi AS DECIMAL(10,2))) / 1000000
                ) AS total_m³
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'ukurans.panjang', 'ukurans.lebar', 'ukurans.tebal', 'detail_barang_dikerjakan_pot_siku.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll' => $totalAll,
            'totalVolumeM3' => $totalVolumeM3,
            'totalPegawai' => $totalPegawai,
            'globalUkuran' => $globalUkuran,
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'progressPegawai' => $progressPegawai,
        ];
    }
}
