<?php

namespace App\Filament\Resources\ProduksiPressDryers\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Import Log untuk debugging
use App\Models\ProduksiPressDryer;
use App\Models\DetailHasil;
use App\Models\DetailPegawai;

class ProduksiPressDryerSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-press-dryers.widgets.summary';
    protected int|string|array $columnSpan = 'full';
    public ?ProduksiPressDryer $record = null;
    public array $summary = [];

    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];
        return [
            "echo:production.dryer.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiPressDryer $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    public function refreshSummary(): void
    {
        if (!$this->record) return;

        try {
            $produksiId = $this->record->id;

            // 1. TOTAL PRODUKSI (LEMBAR)
            $totalAll = DetailHasil::where('id_produksi_dryer', $produksiId)
                ->sum(DB::raw('CAST(isi AS UNSIGNED)'));

            // 2. TOTAL PEGAWAI (UNIK)
            $totalPegawai = DetailPegawai::where('id_produksi_dryer', $produksiId)
                ->distinct('id_pegawai')
                ->count('id_pegawai');

            // 3. LOGIKA KUBIKASI (P x L x T x Qty / 10.000.000)
            // Mengambil semua detail hasil beserta ukuran terkait
            $details = DetailHasil::query()
                ->where('id_produksi_dryer', $produksiId)
                ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
                ->select([
                    'ukurans.panjang',
                    'ukurans.lebar',
                    'ukurans.tebal',
                    'detail_hasils.isi'
                ])
                ->get();

            $totalKubikasi = 0;
            $breakdownLog = [];

            foreach ($details as $index => $item) {
                $p = (float) $item->panjang;
                $l = (float) $item->lebar;
                $t = (float) $item->tebal;
                $qty = (float) $item->isi;

                // Rumus Kubikasi
                $kubikasiBaris = ($p * $l * $t * $qty) / 10000000;
                $totalKubikasi += $kubikasiBaris;

                // Simpan ke log breakdown
                $breakdownLog[] = "Baris #$index: ($p x $l x $t x $qty) / 10jt = $kubikasiBaris";
            }

            // Mencatat LOG ke storage/logs/laravel.log
            Log::info("=== BREAKDOWN KUBIKASI DRYER ID: $produksiId ===");
            foreach ($breakdownLog as $logLine) Log::info($logLine);
            Log::info("TOTAL KUBIKASI AKHIR: $totalKubikasi");

            // Query Dasar Ukuran (Untuk tampilan List)
            $baseQuery = DetailHasil::query()
                ->where('detail_hasils.id_produksi_dryer', $produksiId)
                ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
                ->selectRaw('
                    CONCAT(
                        TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                        TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                        TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                    ) AS ukuran
                ');

            $globalUkuranKw = (clone $baseQuery)
                ->selectRaw('
                    detail_hasils.kw,
                    SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
                ')
                ->groupBy('ukuran', 'detail_hasils.kw')
                ->orderBy('ukuran')
                ->get();

            $globalUkuran = (clone $baseQuery)
                ->selectRaw('SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total')
                ->groupBy('ukuran')
                ->orderBy('ukuran')
                ->get();

            $this->summary = [
                'totalAll' => $totalAll,
                'totalPegawai' => $totalPegawai,
                'totalKubikasi' => $totalKubikasi, // Data Baru
                'globalUkuranKw' => $globalUkuranKw,
                'globalUkuran' => $globalUkuran,
            ];
        } catch (\Exception $e) {
            Log::error("Error pada Summary Widget Dryer: " . $e->getMessage());
        }
    }
}
