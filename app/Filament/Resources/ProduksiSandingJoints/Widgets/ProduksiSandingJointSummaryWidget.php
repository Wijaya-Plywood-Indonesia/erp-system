<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiSandingJoint;
use App\Models\HasilSandingJoint;
use App\Models\PegawaiSandingJoint;

class ProduksiSandingJointSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-sanding-joint.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiSandingJoint $record = null;
    public array $summary = [];

    /**
     * Listener untuk menangkap sinyal 'sanding_join'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            "echo:production.sanding_join.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiSandingJoint $record = null): void
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

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = HasilSandingJoint::where('id_produksi_sanding_joint', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = PegawaiSandingJoint::where('id_produksi_sanding_joint', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran (dengan pembersihan angka nol)
        $baseQuery = HasilSandingJoint::query()
            ->where('id_produksi_sanding_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_sanding_joint.id_ukuran')
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
                hasil_sanding_joint.kw,
                SUM(CAST(hasil_sanding_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'hasil_sanding_joint.kw')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(hasil_sanding_joint.jumlah AS UNSIGNED)) AS total')
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
