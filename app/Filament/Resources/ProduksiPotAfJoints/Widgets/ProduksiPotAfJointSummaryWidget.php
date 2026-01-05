<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiPotAfJoint;
use App\Models\HasilPotAfJoint;
use App\Models\PegawaiPotAfJoint;

class ProduksiPotAfJointSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pot-af-joint.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiPotAfJoint $record = null;

    public array $summary = [];

    public function mount(?ProduksiPotAfJoint $record = null): void
    {
        if (! $record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // TOTAL PRODUKSI
        // ======================
        $totalAll = HasilPotAfJoint::where('id_produksi_pot_af_joint', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ======================
        // TOTAL PEGAWAI
        // ======================
        $totalPegawai = PegawaiPotAfJoint::where('id_produksi_pot_af_joint', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ======================
        // GLOBAL UKURAN + KW
        // ======================
        $globalUkuranKw = HasilPotAfJoint::query()
            ->where('id_produksi_pot_af_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_pot_af_joint.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                hasil_pot_af_joint.kw,
                SUM(CAST(hasil_pot_af_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'hasil_pot_af_joint.kw')
            ->orderBy('ukuran')
            ->orderBy('hasil_pot_af_joint.kw')
            ->get();

        // ======================
        // GLOBAL UKURAN (SEMUA KW)
        // ======================
        $globalUkuran = HasilPotAfJoint::query()
            ->where('id_produksi_pot_af_joint', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_pot_af_joint.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(hasil_pot_af_joint.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'  => $totalPegawai,
            'globalUkuranKw'=> $globalUkuranKw,
            'globalUkuran'  => $globalUkuran,
        ];
    }
}
