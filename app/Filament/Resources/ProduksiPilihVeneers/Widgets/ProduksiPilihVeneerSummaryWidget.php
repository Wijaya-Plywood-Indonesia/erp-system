<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiPilihVeneer;
use App\Models\HasilPilihVeneer;
use App\Models\PegawaiPilihVeneer;

class ProduksiPilihVeneerSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pilih-veneer.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPilihVeneer $record = null;

    public array $summary = [];

    /**
     * LANGKAH 1: Tambahkan Listeners Echo
     * Menunggu sinyal "ProductionUpdated" dari Reverb
     */
    // Tambahkan ini di bagian atas class
    public function getListeners(): array
    {
        $produksiId = $this->record->id;

        return [
            // Gunakan sintaks string penuh untuk memastikan tidak ada typo
            "echo:production.veneer.{$produksiId},ProductionUpdated" => 'refreshSummary',
        ];
    }

    /**
     * LANGKAH 2: Modifikasi mount() 
     * Memanggil fungsi load data utama
     */
    public function mount(?ProduksiPilihVeneer $record = null): void
    {
        $this->refreshSummary();
    }

    /**
     * LANGKAH 3: Fungsi Refresh Data
     * Fungsi ini akan dipanggil otomatis oleh Reverb setiap kali ada data baru disimpan
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL HASIL (LEMBAR)
        $totalAll = HasilPilihVeneer::where('id_produksi_pilih_veneer', $produksiId)
            ->sum('jumlah');

        // 2. TOTAL PEGAWAI UNIK
        $totalPegawai = PegawaiPilihVeneer::where('id_produksi_pilih_veneer', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = HasilPilihVeneer::query()
            ->where('hasil_pilih_veneer.id_produksi_pilih_veneer', $produksiId)
            ->join('modal_pilih_veneer', 'modal_pilih_veneer.id', '=', 'hasil_pilih_veneer.id_modal_pilih_veneer')
            ->join('ukurans', 'ukurans.id', '=', 'modal_pilih_veneer.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                hasil_pilih_veneer.kw,
                SUM(hasil_pilih_veneer.jumlah) AS total
            ')
            ->groupBy('ukuran', 'hasil_pilih_veneer.kw')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuranSemua = HasilPilihVeneer::query()
            ->where('hasil_pilih_veneer.id_produksi_pilih_veneer', $produksiId)
            ->join('modal_pilih_veneer', 'modal_pilih_veneer.id', '=', 'hasil_pilih_veneer.id_modal_pilih_veneer')
            ->join('ukurans', 'ukurans.id', '=', 'modal_pilih_veneer.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_pilih_veneer.jumlah) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'          => $totalAll,
            'totalPegawai'      => $totalPegawai,
            'globalUkuranKw'    => $globalUkuranKw,
            'globalUkuranSemua' => $globalUkuranSemua,
        ];
    }
}
