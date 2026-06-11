<?php

namespace App\Filament\Resources\ProduksiStiks\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiStik;
use App\Models\DetailHasilStik;
use App\Models\DetailPegawaiStik;

class ProduksiStikSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-stik.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiStik $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk tipe 'stik'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (! $id) return [];

        return [
            "echo:production.stik.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiStik $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi Refresh Data secara Real-time
     */
    public function refreshSummary(): void
    {
        if (! $this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = DetailHasilStik::where('id_produksi_stik', $produksiId)
            ->sum(DB::raw('CAST(total_lembar AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI (UNIK)
        $totalPegawai = DetailPegawaiStik::where('id_produksi_stik', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = DetailHasilStik::query()
            ->where('id_produksi_stik', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_stik.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = (clone $baseQuery)
            ->selectRaw('
                detail_hasil_stik.kw,
                SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_hasil_stik.kw')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // 5. GLOBAL JENIS KAYU & UKURAN
        $globalJenisKayuUkuran = DetailHasilStik::query()
            ->where('id_produksi_stik', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_stik.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_hasil_stik.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasil_stik.kw as kw,
                SUM(CAST(detail_hasil_stik.total_lembar AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_hasil_stik.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        // 6. TARGET PROGRESS (MESIN STIK - GLOBAL DARI UKURAN 0x0x0 / ID 33)
        $stikMachineIds = DB::table('mesins')
            ->join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'STIK')
            ->pluck('mesins.id')
            ->toArray();

        if (empty($stikMachineIds)) {
            $stikMachineIds = [8];
        }

        // Ambil target untuk ukuran 0x0x0 (id_ukuran = 33)
        $tgt = DB::table('targets')
            ->whereIn('id_mesin', $stikMachineIds)
            ->where('id_ukuran', 33)
            ->first();

        $targetProgress = null;

        if ($tgt) {
            $targetVal = (float) $tgt->target;
            $progress = $targetVal > 0 ? min(round(($totalAll / $targetVal) * 100, 1), 100) : 0;

            $targetProgress = [
                'hasTarget' => true,
                'ukuran' => 'Semua Ukuran (Global)',
                'actual' => $totalAll,
                'target' => $targetVal,
                'progress' => $progress,
                'orang' => $tgt->orang,
                'jam' => $tgt->jam,
            ];
        } else {
            $targetProgress = [
                'hasTarget' => false,
                'ukuran' => 'Semua Ukuran (Global)',
                'actual' => $totalAll,
                'target' => 0,
                'progress' => 0,
                'orang' => '-',
                'jam' => '-',
            ];
        }

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'targetProgress' => $targetProgress,
        ];
    }
}
