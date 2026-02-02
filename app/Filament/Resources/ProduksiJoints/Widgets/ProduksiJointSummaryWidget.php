<?php

namespace App\Filament\Resources\ProduksiJoints\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiJoint;
use App\Models\HasilJoint;
use App\Models\PegawaiJoint;

class ProduksiJointSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-joint.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiJoint $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk mendengarkan perubahan pada departemen 'join'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (! $id) return [];

        return [
            "echo:production.join.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiJoint $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi Refresh Data (Dijalankan otomatis saat ada broadcast)
     */
    public function refreshSummary(): void
    {
        if (! $this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = HasilJoint::where('id_produksi_joint', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = PegawaiJoint::where('id_produksi_joint', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran (menggunakan TRIM agar tampilan angka bersih)
        $baseQuery = HasilJoint::query()
            ->where('id_produksi_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_joint.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = (clone $baseQuery)
            ->selectRaw('
                hasil_joint.kw,
                SUM(CAST(hasil_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'hasil_joint.kw')
            ->orderBy('ukuran')
            ->orderBy('hasil_joint.kw')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(hasil_joint.jumlah AS UNSIGNED)) AS total')
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
