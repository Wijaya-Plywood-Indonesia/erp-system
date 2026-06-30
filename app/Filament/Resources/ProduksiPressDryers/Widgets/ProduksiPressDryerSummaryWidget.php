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
    public array $summary = [
        'totalAll' => 0,
        'totalPegawai' => 0,
        'totalKubikasi' => 0,
        'globalUkuranKw' => [],
        'globalUkuran' => [],
    ];

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
            // Eager load necessary relationships safely
            $this->record->loadMissing([
                'detailMesins.mesin',
                'detailMesins.kategoriMesin',
            ]);

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
                ->leftJoin('jenis_kayus', 'jenis_kayus.id', '=', 'detail_hasils.id_jenis_kayu')
                ->selectRaw('
                    CONCAT(
                        TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                        TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                    ) AS ukuran,
                    jenis_kayus.nama_kayu AS jenis_kayu
                ');

            $globalUkuranKw = (clone $baseQuery)
                ->addSelect(DB::raw('
                    detail_hasils.kw,
                    SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
                '))
                ->groupBy('ukuran', 'jenis_kayu', 'detail_hasils.kw')
                ->orderBy('ukuran')
                ->get();

            $globalUkuran = (clone $baseQuery)
                ->addSelect(DB::raw('SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total'))
                ->groupBy('ukuran', 'jenis_kayu')
                ->orderBy('ukuran')
                ->get();

            // 5. GLOBAL JENIS KAYU & UKURAN
            $globalJenisKayuUkuran = DetailHasil::query()
                ->where('id_produksi_dryer', $produksiId)
                ->join('ukurans', 'ukurans.id', '=', 'detail_hasils.id_ukuran')
                ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_hasils.id_jenis_kayu')
                ->selectRaw('
                    jenis_kayus.nama_kayu as jenis_kayu,
                    CONCAT(
                        TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                        TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                        TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                    ) AS ukuran,
                    detail_hasils.kw as kw,
                    SUM(CAST(detail_hasils.isi AS UNSIGNED)) AS total
                ')
                ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_hasils.kw')
                ->orderBy('jenis_kayus.nama_kayu')
                ->orderBy('ukuran')
                ->get();

            // 6. LOGIKA TARGET
            $firstMesin = $this->record->detailMesins->first();
            $namaMesin = '-';
            $mesinUtamaId = null;

            if ($firstMesin) {
                $namaMesin = $firstMesin->mesin->nama_mesin
                    ?? $firstMesin->kategoriMesin->nama_kategori_mesin
                    ?? 'MESIN ?';
                $mesinUtamaId = $firstMesin->id_mesin_dryer;
            }

            $shift = strtoupper($this->record->shift ?? 'PAGI');
            $targetModel = null;

            if ($mesinUtamaId) {
                if (stripos($namaMesin, 'DRYER') !== false) {
                    if ($shift === 'PAGI') {
                        $targetModel = \App\Models\Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } else {
                        $targetModel = \App\Models\Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    }
                } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                    $targetModel = \App\Models\Target::where('kode_ukuran', 'DRYER PAGI')->first();
                } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                    $targetModel = \App\Models\Target::where('kode_ukuran', 'DRYER MALAM')->first();
                } else {
                    $targetModel = \App\Models\Target::where('id_mesin', $mesinUtamaId)->first();
                }
            }

            $targetValue = $targetModel ? (float) $targetModel->target : 0;
            $isDryer = stripos($namaMesin, 'DRYER') !== false;
            $progress = 0;

            if ($targetValue > 0) {
                $actual = $isDryer ? $totalKubikasi : $totalAll;
                $progress = min(round(($actual / $targetValue) * 100, 1), 100);
            }

            $targetSummary = [
                'hasTarget' => $targetModel !== null,
                'targetName' => $targetModel->kode_ukuran ?? ($targetModel ? $namaMesin : 'TIDAK ADA TARGET'),
                'targetValue' => $targetValue,
                'unit' => $isDryer ? 'm³' : 'Lembar',
                'actualValue' => $isDryer ? $totalKubikasi : $totalAll,
                'progress' => $progress,
            ];

            $this->summary = [
                'totalAll' => $totalAll,
                'totalPegawai' => $totalPegawai,
                'totalKubikasi' => $totalKubikasi, // Data Baru
                'globalUkuranKw' => $globalUkuranKw,
                'globalUkuran' => $globalUkuran,
                'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
                'targetSummary' => $targetSummary,
            ];
        } catch (\Exception $e) {
            Log::error("Error pada Summary Widget Dryer: " . $e->getMessage());
        }
    }
}
