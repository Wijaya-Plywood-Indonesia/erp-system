<?php

namespace App\Filament\Resources\ProduksiRepairs\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiRepair;
use App\Models\DetailHasilRepair;

class ProduksiRepairSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-repairs.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiRepair $record = null;
    public array $summary = [];

    /**
     * Listener untuk menangkap sinyal 'repair'
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;

        if (!$id) return [];

        return [
            "echo:production.repair.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiRepair $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL PRODUKSI (LEMBAR)
        $totalAll = DetailHasilRepair::where('id_produksi_repair', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI KESELURUHAN (UNIK) VIA PIVOT TABLE
        $totalPegawai = DB::table('detail_repair_pegawai')
            ->join('detail_hasil_repairs', 'detail_hasil_repairs.id', '=', 'detail_repair_pegawai.detail_hasil_repair_id')
            ->where('detail_hasil_repairs.id_produksi_repair', $produksiId)
            ->distinct('detail_repair_pegawai.rencana_pegawai_repair_id')
            ->count('detail_repair_pegawai.rencana_pegawai_repair_id');

        // FORMULA QUERY DASAR DENGAN FALLBACK NAMA KAYU DARI MODAL REPAIR / MANUAL
        $baseQuery = DetailHasilRepair::query()
            ->where('detail_hasil_repairs.id_produksi_repair', $produksiId)
            ->leftJoin('modal_repairs', 'modal_repairs.id', '=', 'detail_hasil_repairs.id_modal_repair')
            ->leftJoin('jenis_kayus AS jk_modal', 'jk_modal.id', '=', 'modal_repairs.id_jenis_kayu')
            ->leftJoin('jenis_kayus AS jk_direct', 'jk_direct.id', '=', 'detail_hasil_repairs.id_jenis_kayu')
            ->join('ukurans', 'ukurans.id', '=', 'detail_hasil_repairs.id_ukuran')
            ->selectRaw('
                COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-") AS jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_hasil_repairs.kw,
                SUM(CAST(detail_hasil_repairs.jumlah AS UNSIGNED)) AS total
            ');

        // 3. GLOBAL UKURAN + KW + JUMLAH ORANG
        $globalUkuranKw = (clone $baseQuery)
            ->leftJoin('detail_repair_pegawai', 'detail_repair_pegawai.detail_hasil_repair_id', '=', 'detail_hasil_repairs.id')
            ->selectRaw('COUNT(DISTINCT detail_repair_pegawai.rencana_pegawai_repair_id) AS jumlah_orang')
            ->groupBy(
                DB::raw('COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-")'),
                'ukuran',
                'detail_hasil_repairs.kw'
            )
            ->orderBy(DB::raw('COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-")'))
            ->orderBy('ukuran')
            ->orderBy('detail_hasil_repairs.kw')
            ->get();

        // 4. GLOBAL JENIS KAYU & UKURAN
        $globalJenisKayuUkuran = (clone $baseQuery)
            ->groupBy(
                DB::raw('COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-")'),
                'ukuran',
                'detail_hasil_repairs.kw'
            )
            ->orderBy(DB::raw('COALESCE(jk_modal.nama_kayu, jk_direct.nama_kayu, "-")'))
            ->orderBy('ukuran')
            ->orderBy('detail_hasil_repairs.kw')
            ->get();

        $this->summary = [
            'totalAll'               => $totalAll,
            'totalPegawai'           => $totalPegawai,
            'globalUkuranKw'         => $globalUkuranKw,
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
        ];
    }
}
