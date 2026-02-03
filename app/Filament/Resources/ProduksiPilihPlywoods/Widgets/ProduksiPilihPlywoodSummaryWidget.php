<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\HasilPilihPlywood;
use App\Models\PegawaiPilihPlywood;

class ProduksiPilihPlywoodSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pilih-plywood.widgets.hasil-pilih-plywood-summary';

    public ?Model $record = null;
    public array $summary = []; // Simpan data di sini agar bisa diakses Blade
    protected int | string | array $columnSpan = 'full';

    /**
     * LANGKAH 1: Aktifkan Listener Pusher
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.pilih_plywood.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 2: Masukkan Logika Perhitungan di sini
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // Hitung Total Hasil
        $totalAll = HasilPilihPlywood::where('id_produksi_pilih_plywood', $produksiId)->sum('jumlah');

        // Hitung Headcount Pegawai
        $totalPegawai = PegawaiPilihPlywood::where('id_produksi_pilih_plywood', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Detail per Ukuran & Grade
        $listHasil = HasilPilihPlywood::query()
            ->where('id_produksi_pilih_plywood', $produksiId)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'hasil_pilih_plywood.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade AS kw,
                SUM(hasil_pilih_plywood.jumlah) AS total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->get();

        $this->summary = [
            'totalAll' => $totalAll,
            'totalPegawai' => $totalPegawai,
            'listHasil' => $listHasil,
        ];
    }
}
