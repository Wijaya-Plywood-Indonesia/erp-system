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

    public function mount(?ProduksiJoint $record = null): void
    {
        if (! $record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // 1. TOTAL PRODUKSI (LEMBAR)
        // ======================
        $totalAll = HasilJoint::where('id_produksi_joint', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ======================
        // 2. TOTAL PEGAWAI (UNIK)
        // ======================
        $totalPegawai = PegawaiJoint::where('id_produksi_joint', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ======================
        // 3. GLOBAL UKURAN + KW
        // ======================
        $globalUkuranKw = HasilJoint::query()
            ->where('id_produksi_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_joint.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                hasil_joint.kw,
                SUM(CAST(hasil_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'hasil_joint.kw')
            ->orderBy('ukuran')
            ->orderBy('hasil_joint.kw')
            ->get();

        // ======================
        // 4. GLOBAL UKURAN (SEMUA KW)
        // ======================
        $globalUkuran = HasilJoint::query()
            ->where('id_produksi_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_joint.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(hasil_joint.jumlah AS UNSIGNED)) AS total
            ')
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
