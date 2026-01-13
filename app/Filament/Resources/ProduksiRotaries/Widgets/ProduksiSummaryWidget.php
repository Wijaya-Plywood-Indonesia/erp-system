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

    public function mount(?ProduksiRotary $record = null): void
    {
        $this->record = $record;

        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // TOTAL KESELURUHAN
        // ======================
        $totalAll = DetailHasilPaletRotary::where('id_produksi', $produksiId)
            ->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // ======================
        // TOTAL PER KW
        // ======================
        $perKw = DetailHasilPaletRotary::where('id_produksi', $produksiId)
            ->selectRaw('kw, SUM(CAST(total_lembar AS UNSIGNED)) AS total')
            ->groupBy('kw')
            ->orderBy('kw')
            ->get();

        // ======================
        // TOTAL PER LAHAN
        // ======================
        $perLahan = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join(
                'penggunaan_lahan_rotaries',
                'penggunaan_lahan_rotaries.id',
                '=',
                'detail_hasil_palet_rotaries.id_penggunaan_lahan'
            )
            ->join(
                'lahans',
                'lahans.id',
                '=',
                'penggunaan_lahan_rotaries.id_lahan'
            )
            ->selectRaw('
                lahans.kode_lahan,
                lahans.nama_lahan,
                SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('lahans.kode_lahan', 'lahans.nama_lahan')
            ->orderBy('lahans.kode_lahan')
            ->get();
        $perJenisKayuKw = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join(
                'penggunaan_lahan_rotaries',
                'penggunaan_lahan_rotaries.id',
                '=',
                'detail_hasil_palet_rotaries.id_penggunaan_lahan'
            )
            ->join(
                'jenis_kayus',
                'jenis_kayus.id',
                '=',
                'penggunaan_lahan_rotaries.id_jenis_kayu'
            )
            ->selectRaw('
        jenis_kayus.nama_kayu AS jenis_kayu,
        detail_hasil_palet_rotaries.kw,
        SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
    ')
            ->groupBy('jenis_kayus.nama_kayu', 'detail_hasil_palet_rotaries.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('detail_hasil_palet_rotaries.kw')
            ->get();
        $perLahanJenisKayuKw = DetailHasilPaletRotary::query()
            ->where('detail_hasil_palet_rotaries.id_produksi', $produksiId)
            ->join(
                'penggunaan_lahan_rotaries',
                'penggunaan_lahan_rotaries.id',
                '=',
                'detail_hasil_palet_rotaries.id_penggunaan_lahan'
            )
            ->join(
                'lahans',
                'lahans.id',
                '=',
                'penggunaan_lahan_rotaries.id_lahan'
            )
            ->leftJoin(
                'jenis_kayus',
                'jenis_kayus.id',
                '=',
                'penggunaan_lahan_rotaries.id_jenis_kayu'
            )
            ->selectRaw('
        lahans.id AS lahan_id,
        lahans.kode_lahan,
        lahans.nama_lahan,
        COALESCE(jenis_kayus.nama_kayu, "Tidak Diketahui") AS jenis_kayu,
        detail_hasil_palet_rotaries.kw,
        SUM(CAST(detail_hasil_palet_rotaries.total_lembar AS UNSIGNED)) AS total
    ')
            ->groupBy(
                'lahans.id',
                'lahans.kode_lahan',
                'lahans.nama_lahan',
                'jenis_kayu',
                'detail_hasil_palet_rotaries.kw'
            )
            ->orderBy('lahans.kode_lahan')
            ->orderBy('jenis_kayu')
            ->orderBy('detail_hasil_palet_rotaries.kw')
            ->get();
        // ======================
        // SET HASIL KE VIEW
        // ======================
        $this->summary = [
            'totalAll' => $totalAll,
            'perKw' => $perKw,
            'perJenisKayuKw' => $perJenisKayuKw,
            'perLahanJenisKayuKw' => $perLahanJenisKayuKw,
        ];
    }
}