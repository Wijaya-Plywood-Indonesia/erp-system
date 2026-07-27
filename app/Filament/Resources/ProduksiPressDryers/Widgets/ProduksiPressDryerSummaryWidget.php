<?php

namespace App\Filament\Resources\ProduksiPressDryers\Widgets;

use App\Models\DetailHasil;
use App\Models\DetailPegawai;
use App\Models\ProduksiPressDryer; // Import Log untuk debugging
use App\Models\Target;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        if (! $id) {
            return [];
        }

        return [
            "echo:production.dryer.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiPressDryer $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Format angka: max 4 desimal, trailing zero dibuang.
     * Contoh: 12345.6700 -> "12.345,67" ; 12345.0000 -> "12.345"
     */
    private function formatSmart(float $value): string
    {
        $formatted = number_format($value, 4, ',', '.');
        if (str_contains($formatted, ',')) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, ',');
        }

        return $formatted;
    }

    public function refreshSummary(): void
    {
        if (! $this->record) {
            return;
        }

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
                    'detail_hasils.isi',
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
            foreach ($breakdownLog as $logLine) {
                Log::info($logLine);
            }
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

            // ==========================================================
            // SIMPAN DULU DATA INTI YANG SUDAH PASTI BERHASIL DIHITUNG
            // Ini memastikan kubikasi & data lain tetap tampil walaupun
            // logika target di bawah nanti gagal / melempar exception.
            // ==========================================================
            $this->summary = [
                'totalAll' => $totalAll,
                'totalPegawai' => $totalPegawai,
                'totalKubikasi' => $totalKubikasi,
                'globalUkuranKw' => $globalUkuranKw,
                'globalUkuran' => $globalUkuran,
                'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
                'targetSummary' => [
                    'hasTarget' => false,
                    'targetName' => 'TIDAK ADA TARGET',
                    'targetValue' => $this->formatSmart(0),
                    'unit' => 'm³',
                    'actualValue' => $this->formatSmart($totalKubikasi),
                    'progress' => 0,
                ],
            ];

            // ==========================================================
            // 6. LOGIKA TARGET — dibungkus try-catch terpisah.
            // Jika gagal (misal relasi mesin null), hanya targetSummary
            // yang fallback ke default; data inti di atas tetap aman.
            // ==========================================================
            try {
                $firstMesin = $this->record->detailMesins->first();
                $namaMesin = '-';
                $mesinUtamaId = null;

                if ($firstMesin) {
                    // Nullsafe operator (?->) mencegah error saat relasi
                    // mesin/kategoriMesin bernilai null (data sudah dihapus)
                    $namaMesin = $firstMesin->mesin?->nama_mesin
                        ?? $firstMesin->kategoriMesin?->nama_kategori_mesin
                        ?? 'MESIN ?';
                    $mesinUtamaId = $firstMesin->id_mesin_dryer;
                }

                $shift = strtoupper($this->record->shift ?? 'PAGI');
                $targetModel = null;

                if ($mesinUtamaId) {
                    if (stripos($namaMesin, 'DRYER') !== false) {
                        if ($shift === 'PAGI') {
                            $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                        } else {
                            $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                        }
                    } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                        $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    } else {
                        $targetModel = Target::where('id_mesin', $mesinUtamaId)->first();
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
                    // Nilai float asli ($targetValue, $totalKubikasi, $totalAll) tetap
                    // dipakai untuk hitung $progress di atas SEBELUM diformat ke string,
                    // supaya perhitungan persentase tidak terpengaruh pembulatan tampilan.
                    'targetValue' => $isDryer
                        ? $this->formatSmart($targetValue)
                        : number_format($targetValue, 0, ',', '.'),
                    'unit' => $isDryer ? 'm³' : 'Lembar',
                    'actualValue' => $isDryer
                        ? $this->formatSmart($totalKubikasi)
                        : number_format($totalAll, 0, ',', '.'),
                    'progress' => $progress,
                ];

                // Hanya bagian targetSummary yang di-update di sini,
                // data inti (kubikasi, total, breakdown) tidak disentuh lagi.
                $this->summary['targetSummary'] = $targetSummary;
            } catch (\Exception $e) {
                Log::error("Gagal memuat target untuk dryer {$produksiId}: ".$e->getMessage());
                // summary utama (termasuk kubikasi) tetap aman karena
                // sudah di-assign sebelum blok try-catch ini dijalankan.
            }
        } catch (\Exception $e) {
            Log::error('Error pada Summary Widget Dryer: '.$e->getMessage());
        }
    }
}
