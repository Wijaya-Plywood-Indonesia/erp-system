<?php

namespace App\Filament\Resources\TurunKayus\Widgets;

use Filament\Widgets\Widget;
use App\Models\TurunKayu;
use App\Models\PegawaiTurunKayu;
use Illuminate\Support\Facades\DB;

class TurunKayuSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.turun-kayu.widgets.turun-kayu-summary-widget';

    protected int|string|array $columnSpan = 'full';

    public ?TurunKayu $record = null;

    protected function getViewData(): array
    {
        if (!$this->record) {
            return ['summary' => null];
        }

        // 1. TOTAL PEGAWAI (Unique)
        $totalPegawai = PegawaiTurunKayu::where('id_turun_kayu', $this->record->id)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 2. REKAPAN DETAIL KENDARAAN (Berapa Pickup, Truk, Fuso)
        $rekapDetail = DB::table('detail_turun_kayus')
            ->join('kayu_masuks', 'detail_turun_kayus.id_kayu_masuk', '=', 'kayu_masuks.id')
            ->join('kendaraan_supplier_kayus', 'kayu_masuks.id_kendaraan_supplier_kayus', '=', 'kendaraan_supplier_kayus.id')
            ->where('detail_turun_kayus.id_turun_kayu', $this->record->id)
            ->select('kendaraan_supplier_kayus.jenis_kendaraan', DB::raw('count(DISTINCT kayu_masuks.id) as jumlah'))
            ->groupBy('kendaraan_supplier_kayus.jenis_kendaraan')
            ->get();

        // 3. TOTAL SEMUA KENDARAAN
        $totalKendaraan = $rekapDetail->sum('jumlah');

        return [
            'summary' => [
                'totalPegawai' => $totalPegawai,
                'totalKendaraan' => $totalKendaraan,
                'details' => $rekapDetail,
            ],
        ];
    }
}
