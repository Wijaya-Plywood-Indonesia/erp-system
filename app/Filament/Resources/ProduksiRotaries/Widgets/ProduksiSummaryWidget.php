<?php

namespace App\Filament\Resources\ProduksiRotaries\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\DetailHasilPaletRotary;
use App\Models\ProduksiRotary;

class ProduksiSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-rotaries.widgets.produksi-summary-widget';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiRotary $record = null;
    public array $summary = [];

    /**
     * LANGKAH 1: Tambahkan Listeners
     * Mendengarkan channel 'production.rotary.{id}'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            // Sesuai dengan tipe 'rotary' yang Anda set di Model tadi
            "echo:production.rotary.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    /**
     * LANGKAH 2: Mount awal
     */
    public function mount(?ProduksiRotary $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 3: Pindahkan logika query ke fungsi terpisah
     * Fungsi ini akan dijalankan ulang secara instan setiap kali Pusher mengirim sinyal
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $record = $this->record;
        $produksiId = $record->id;

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = $record->detailPaletRotary()->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = $record->detailPegawaiRotary()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar
        $baseQuery = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_palet_rotaries.id_ukuran')
            ->join('penggunaan_lahan_rotaries', 'penggunaan_lahan_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_penggunaan_lahan')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'penggunaan_lahan_rotaries.id_jenis_kayu')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                jenis_kayus.nama_kayu as jenis_kayu,
                detail_hasil_palet_rotaries.kw,
                SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
            ');

        // 3. REKAP UKURAN + KW + JENIS KAYU
        $globalUkuranKwJenis = (clone $baseQuery)
            ->groupBy('ukuran', 'jenis_kayu', 'detail_hasil_palet_rotaries.kw')
            ->orderBy('ukuran')
            ->orderBy('jenis_kayu')
            ->get();

        // 4. REKAP UKURAN SAJA
        $globalUkuran = (clone $baseQuery)
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'            => $totalAll,
            'totalPegawai'        => $totalPegawai,
            'globalUkuranKwJenis' => $globalUkuranKwJenis,
            'globalUkuran'        => $globalUkuran,
        ];
    }
}
