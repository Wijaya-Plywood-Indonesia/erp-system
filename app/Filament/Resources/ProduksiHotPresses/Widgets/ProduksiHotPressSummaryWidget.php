<?php

namespace App\Filament\Resources\ProduksiHotPresses\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiHp;
use App\Models\PlatformHasilHp;
use App\Models\TriplekHasilHp;

class ProduksiHotPressSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-hotpress.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiHp $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Hot Press
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.hotpress.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiHp $record = null): void
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

        $record = $this->record;
        $produksiId = $record->id;

        // 1. HITUNG PEGAWAI
        $totalPegawai = $record->detailPegawaiHp()
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 2. DATA HASIL PLATFORM
        $totalPlatform = PlatformHasilHp::where('id_produksi_hp', $produksiId)->sum('isi');

        $listPlatform = PlatformHasilHp::query()
            ->where('platform_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(platform_hasil_hp.isi) as total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 3. DATA HASIL TRIPLEK
        $totalTriplek = TriplekHasilHp::where('id_produksi_hp', $produksiId)->sum('isi');

        $listTriplek = TriplekHasilHp::query()
            ->where('triplek_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(triplek_hasil_hp.isi) as total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalPegawai'  => $totalPegawai,
            'totalPlatform' => $totalPlatform,
            'listPlatform'  => $listPlatform,
            'totalTriplek'  => $totalTriplek,
            'listTriplek'   => $listTriplek,
        ];
    }
}
