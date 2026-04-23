<?php

namespace App\Filament\Resources\ProduksiGuellotines\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\produksi_guellotine;
use App\Models\hasil_guellotine;
use App\Models\pegawai_guellotine;

class ProduksiGuellotineWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-guellotines.widgets.produksi-guellotine-widget';

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

        // 1. TOTAL HASIL
        $totalAll = hasil_guellotine::where('id_produksi_guellotine', $produksiId)
            ->sum('jumlah');

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = pegawai_guellotine::where('id_produksi_guellotine', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 3. GLOBAL UKURAN + JENIS KAYU
        $globalUkuranKayu = hasil_guellotine::query()
            ->where('id_produksi_guellotine', $produksiId)
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
            ->groupBy('ukurans.panjang', 'ukurans.lebar', 'ukurans.tebal', 'jenis_kayus.nama_kayu')
            ->orderBy('jenis_kayus.nama_kayu')
            ->get()
            ->toArray();

        // BASE QUERY - reuse untuk query berikutnya
        $baseQuery = hasil_guellotine::query()
            ->where('id_produksi_guellotine', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran');

        // 4. GLOBAL UKURAN (TOTAL SEMUA JENIS KAYU)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('
            CONCAT(
                TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
            ) AS ukuran,
            SUM(hasil_guellotine.jumlah) AS total
        ')
            ->groupBy('ukurans.panjang', 'ukurans.lebar', 'ukurans.tebal')
            ->orderByRaw('ukurans.panjang, ukurans.lebar, ukurans.tebal')
            ->get();

        $this->summary = [
            'totalAll'         => $totalAll,
            'totalPegawai'     => $totalPegawai,
            'globalUkuranKayu' => $globalUkuranKayu,
            'globalUkuran'     => $globalUkuran,
        ];
    }
}
