<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class TargetOverview extends Widget
{
    protected static bool $isDiscovered = true;

    public array $full_data = [];
    public array $targetKosong = [];

    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.target-overview';

    public function mount(): void
    {
        $this->loadAndProcessData();
    }

    public function loadAndProcessData()
    {
        $this->targetKosong = [];

        $table_name = "produksi_rotaries";
        $table_hasil = "detail_hasil_palet_rotaries";
        $keyJumlah = "total_lembar";

        $todayStr = now()->format('Y-m-d');
        $endDate = $todayStr;
        $startDate = now()->subDays(6)->format('Y-m-d');

        /** GET DATA MESIN */
        $data_mesin = DB::table("mesins")
            ->join("kategori_mesins", "mesins.kategori_mesin_id", "=", "kategori_mesins.id")
            ->where(function ($q) {
                $q->where('mesins.kategori_mesin_id', 1)
                    ->orWhere('kategori_mesins.nama_kategori_mesin', 'ROTARY');
            })
            ->select("mesins.id AS id_mesin", "mesins.nama_mesin")
            ->get();

        $allResults = [];

        /** FORMAT SQL UKURAN */
        $ukuranSql = "
            CONCAT(
                TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)),
                ' x ',
                TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)),
                ' x ',
                TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
            )
        ";

        foreach ($data_mesin as $mesin) {

            $id_mesin = $mesin->id_mesin;
            $nama_mesin = $mesin->nama_mesin;

            /** Ambil ID produksi per tanggal */
            $ambil_data_tanggal = DB::table($table_name)
                ->whereBetween("tgl_produksi", [$startDate, $endDate])
                ->where("id_mesin", $id_mesin)
                ->selectRaw("MIN(id) AS id, tgl_produksi AS tanggal")
                ->groupBy("tgl_produksi")
                ->orderBy("tgl_produksi", "ASC")
                ->get();

            $dataPerTanggal = [];

            foreach ($ambil_data_tanggal as $tgl) {

                /** Ambil detail produksi jenis kayu + ukuran */
                $progressMesin = DB::table($table_name)
                    ->join($table_hasil, "$table_hasil.id_produksi", "=", "$table_name.id")
                    ->join("penggunaan_lahan_rotaries", "$table_hasil.id_penggunaan_lahan", "=", "penggunaan_lahan_rotaries.id")
                    ->join("jenis_kayus", "jenis_kayus.id", "=", "penggunaan_lahan_rotaries.id_jenis_kayu")
                    ->join("ukurans", "ukurans.id", "=", "$table_hasil.id_ukuran")
                    ->where("$table_name.id_mesin", $id_mesin)
                    ->where("$table_name.id", $tgl->id)
                    ->selectRaw("
                        penggunaan_lahan_rotaries.id_jenis_kayu AS id_kayu,
                        $table_hasil.id_ukuran AS id_ukuran,
                        $ukuranSql AS ukuran_formatted,
                        jenis_kayus.nama_kayu AS nama_kayu,
                        SUM(CAST($table_hasil.$keyJumlah AS UNSIGNED)) AS total
                    ")
                    ->groupBy('id_kayu', 'id_ukuran', 'nama_kayu', 'ukuran_formatted')
                    ->get()
                    ->map(function ($rowProduksi) use ($id_mesin, $nama_mesin) {

                        /** Cari target */
                        $targetData = DB::table("targets")
                            ->where('id_mesin', $id_mesin)
                            ->where("id_jenis_kayu", $rowProduksi->id_kayu)
                            ->where("id_ukuran", $rowProduksi->id_ukuran)
                            ->first();

                        $targetValue = $targetData->target ?? 0;

                        /** Catat target kosong */
                        if (!$targetData) {
                            $this->targetKosong[] = [
                                'mesin' => $nama_mesin,
                                'kayu' => $rowProduksi->nama_kayu,
                                'ukuran' => $rowProduksi->ukuran_formatted,
                            ];
                        }

                        return [
                            "total_produksi" => (int) $rowProduksi->total,
                            "target" => (int) $targetValue,
                            "progress" => $targetValue > 0
                                ? min(round(($rowProduksi->total / $targetValue) * 100, 1), 100)
                                : 0,
                        ];
                    });

                /** Hasil harian per tanggal */
                $dataPerTanggal[] = [
                    "tanggal_produksi" => (string) $tgl->tanggal,
                    "total_harian" => $progressMesin->sum('total_produksi'),
                    "target_harian" => $progressMesin->sum('target'),
                    "progress_harian" => $progressMesin->sum('progress'),
                    "detail" => $progressMesin->toArray(),
                ];
            }

            /** PROSES MINGGUAN */
            $collectionHarian = collect($dataPerTanggal);

            $totalMingguan = $collectionHarian->sum("total_harian");
            $jumlahDataMingguan = $collectionHarian->count();
            $targetMingguan = $collectionHarian->sum("target_harian");

            $targetMingguanRataRata = $jumlahDataMingguan > 0
                ? $targetMingguan / $jumlahDataMingguan
                : 0;

            $progressMingguan = $collectionHarian->sum("progress_harian");

            /** DATA HARI INI — diberi fallback aman */
            $data_hari_ini = $collectionHarian
                ->firstWhere("tanggal_produksi", $todayStr);

            if (!$data_hari_ini) {
                $data_hari_ini = [
                    "tanggal_produksi" => $todayStr,
                    "total_harian" => 0,
                    "target_harian" => 0,
                    "progress_harian" => 0,
                    "detail" => [],
                ];
            }

            /** FINAL PUSH */
            $allResults[] = [
                "nama_produksi" => "Produksi Rotary",
                "mesin" => $nama_mesin,

                "data_hari_ini" => $data_hari_ini,

                "data_minggu_ini" => [
                    "data" => $dataPerTanggal,
                    "total_mingguan" => $totalMingguan,
                    "target_mingguan" => $targetMingguan,
                    "progress_mingguan" => $progressMingguan,
                    "target_rata_rata_mingguan" => $targetMingguanRataRata
                ],

                "target_kosong" => array_values(array_unique($this->targetKosong, SORT_REGULAR)),
            ];
        }

        $this->full_data = $allResults;
        logger()->info('HARDDUMP', $this->full_data); // <--- LETAKKAN DI SINI
    }
}
