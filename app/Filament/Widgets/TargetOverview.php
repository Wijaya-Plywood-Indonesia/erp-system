<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TargetOverview extends Widget
{
    protected static bool $isDiscovered = true;

    public array $cards = [];

    public array $anjays = [];
    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        // dd($this->loadAndProcessData());
    }
    protected string $view = 'filament.widgets.target-overview';

    public function loadAndProcessData()
    {
        $table_name = "produksi_rotaries";
        $table_hasil = "detail_hasil_palet_rotaries";
        $keyJumlah = "total_lembar";

        // 1. Rentang waktu (Senin - Minggu)
        $startOfWeek = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d 00:00:00');
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d 23:59:59');

        // 2. Ambil List Mesin Rotary
        $data_mains = DB::table("mesins")
            ->join("kategori_mesins", "mesins.kategori_mesin_id", "=", "kategori_mesins.id")
            ->where('mesins.kategori_mesin_id', 1)
            ->orWhere('kategori_mesins.nama_kategori_mesin', 'ROTARY')
            ->select("mesins.id AS id_mesin", "mesins.nama_mesin")
            ->get();

        if ($data_mains->isEmpty()) return [];

        $allResults = [];

        // 3. Loop per Mesin
        foreach ($data_mains as $data_main) {
            $id_mesin = $data_main->id_mesin;
            $nama_mesin = $data_main->nama_mesin;

            $ukuranSql = "CONCAT(
                TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
                TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
                TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
            )";

            // 4. Ambil Produksi Real
            $progressMesin = DB::table($table_name)
                ->join($table_hasil, "$table_hasil.id_produksi", "=", "$table_name.id")
                ->join("penggunaan_lahan_rotaries", "$table_hasil.id_penggunaan_lahan", "=", "penggunaan_lahan_rotaries.id")
                ->join("jenis_kayus", "jenis_kayus.id", "=", "penggunaan_lahan_rotaries.id_jenis_kayu")
                ->join("ukurans", "ukurans.id", "=", "$table_hasil.id_ukuran")
                ->whereBetween("$table_hasil.created_at", [$startOfWeek, $endOfWeek])
                ->where("$table_name.id_mesin", $id_mesin) // Filter per mesin
                ->selectRaw("
                    penggunaan_lahan_rotaries.id_jenis_kayu AS id_kayu,
                    $table_hasil.id_ukuran AS id_ukuran,
                    $ukuranSql AS ukuran_formatted,
                    jenis_kayus.nama_kayu AS nama_kayu,
                    SUM(CAST($table_hasil.$keyJumlah AS UNSIGNED)) AS total
                ")
                ->groupBy('id_kayu', 'id_ukuran', 'nama_kayu', 'ukuran_formatted')
                ->get()
                ->map(function ($rowProduksi) use ($id_mesin, $nama_mesin, $ukuranSql) {
                    // Cari target per item
                    $targetData = DB::table("targets")
                        ->where('id_mesin', $id_mesin)
                        ->where("id_jenis_kayu", $rowProduksi->id_kayu)
                        ->where("id_ukuran", $rowProduksi->id_ukuran)
                        ->select('target')
                        ->first();

                    $targetValue = $targetData->target ?? 0;
                    $progress = $targetValue > 0 ? min(round(($rowProduksi->total / $targetValue) * 100, 1), 100) : 0;

                    return [
                        "nama_kayu" => $rowProduksi->nama_kayu,
                        "ukuran" => $rowProduksi->ukuran_formatted,
                        "total_produksi" => $rowProduksi->total,
                        "target" => $targetValue,
                        "progress" => $progress,
                    ];
                });

            // 5. Kalkulasi Global per Mesin
            $total_global = $progressMesin->sum('total_produksi');
            $target_global = $progressMesin->sum('target');
            $progress_global = $target_global > 0 ? min(round(($total_global / $target_global) * 100, 1), 100) : 0;

            $allResults[] = [
                "mesin" => $nama_mesin,
                "data" => $progressMesin->toArray(),
                "total_global" => $total_global,
                "target_global" => $target_global,
                "progress_global" => $progress_global,
            ];
        }

        $this->cards = $allResults;
        return $allResults;
    }
    protected function getViewData(): array
    {
        return ['cards' => $this->cards];
    }

}
