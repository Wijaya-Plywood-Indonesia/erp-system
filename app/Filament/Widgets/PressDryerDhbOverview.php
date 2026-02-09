<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;


class PressDryerDhbOverview extends Widget
{
    protected string $view = 'filament.widgets.press-dryer-dhb-overview';

    protected static bool $isDiscovered = true;

    public function mount(): void
    {
        $this->loadData();
    }

    public array $full_data = [];
    public array $targetKosong = []; 

    public function loadData (){
        $jsonPath = base_path('app/Data/target2.json');
        if (!File::exists($jsonPath)) return;
        
        $listResourcesProduksi = json_decode(File::get($jsonPath), true);
        
        $gatheringData = [];
        foreach ($listResourcesProduksi as $resources) {
            $dataDB = $resources["dbListName"];
            // $join = $dataDB["join"];
            
            $data_mesin = DB::table("mesins");
            if(isset($dataDB["dbMesin"])){
                $dbMesinName = $dataDB["dbMesin"]["dbName"];
                $dbMesinKeyId = $dataDB["dbMesin"]["key_id_mesin"];
                $data_mesin->join($dbMesinName, "$dbMesinName.$dbMesinKeyId", "=", "mesins.id");
            }

            $data_mesin_hasil = $data_mesin
                ->select("mesins.id AS id_mesin", "mesins.nama_mesin AS nama_mesin")
                ->groupBy("mesins.id", "mesins.nama_mesin") // Perbaikan Typo & SQL Standard
                ->get();

            foreach( $data_mesin_hasil as $data_mesin ){
                $endDate = now()->format('Y-m-d'); 
                $startDate = now()->subDays(6)->format('Y-m-d');
                $id_mesin = $data_mesin->id_mesin;
                $nama_mesin = $data_mesin->nama_mesin;

                $key_tanggal = $dataDB['key_tanggal'];
                $data_per_tanggal = DB::table($dataDB["dbName"])
                ->whereBetween($key_tanggal, [$startDate, $endDate])
                ->where("id_mesin", $id_mesin)
                ->selectRaw("MIN(id) AS id, $key_tanggal AS tanggal")
                ->groupBy($key_tanggal)
                ->orderBy($key_tanggal, "ASC")
                ->get();

                $dataPerTanggal = [];
                foreach ($data_per_tanggal as $tgl) {
                    foreach ($dataDB["dbHasilName"] as $hasil) {
                        $table_name = $dataDB["dbName"];
                        $table_hasil = $hasil["dbName"];
                        $ukuranSql = "CONCAT(TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ', TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ', TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR))))";
                        $keyJumlah = $hasil["key_jumlah"];

                        $progressMesin = DB::table($table_name)
                            ->join($hasil["dbName"], "$table_hasil.id_produksi", "=", "$table_name.id")
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
                                $targetData = DB::table("targets")
                                    ->where('id_mesin', $id_mesin)
                                    ->where("id_jenis_kayu", $rowProduksi->id_kayu)
                                    ->where("id_ukuran", $rowProduksi->id_ukuran)
                                    ->first();

                                $targetValue = $targetData->target ?? 0;
                                if (!$targetData) {
                                    $this->targetKosong[] = [
                                        'mesin' => $nama_mesin,
                                        'kayu' => $rowProduksi->nama_kayu,
                                        'ukuran' => $rowProduksi->ukuran_formatted
                                    ];
                                }

                                return [
                                    "total_produksi" => (int)$rowProduksi->total,
                                    "target" => (int)$targetValue,
                                    "progress" => $targetValue > 0 ? min(round(($rowProduksi->total / $targetValue) * 100, 1), 100) : 0,
                                ];
                            });

                        $dataPerTanggal[] = [
                            "tanggal_produksi" => (string)$tgl->tanggal,
                            "total_harian" => $progressMesin->sum('total_produksi'),
                            "target_harian" => $progressMesin->sum('target'),
                            "progress_harian" => $progressMesin->sum('progress'),
                            "detail" => $progressMesin->toArray(),
                        ];
                    }
                    // --- PROSES DATA MINGGUAN ---
                    $collectionHarian = collect($dataPerTanggal);
                    $totalMingguan = $collectionHarian->sum("total_harian");
                    // Jumlah Data Minggu ini
                    $jumlahDataMingguan = $collectionHarian->count();
                    $targetMingguan = $collectionHarian->sum("target_harian") ; 
                    $targetMingguanRataRata = $jumlahDataMingguan > 0 
                        ? $targetMingguan / $jumlahDataMingguan 
                        : 0;

                    // Rumus progress mingguan: (Total Produksi / Total Target)
                    $progressMingguan = $collectionHarian->sum("progress_harian");

                    // --- FILTER DATA HARI INI ---
                    $todayStr = now()->format('Y-m-d'); // String tanggal hari ini
                    $data_hari_ini = $collectionHarian->where("tanggal_produksi", $todayStr)->first();

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
            }
        }
    } 
}
