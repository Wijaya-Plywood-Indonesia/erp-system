<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PressDryerDhbOverview extends Widget
{
    protected string $view = 'filament.widgets.press-dryer-dhb-overview';

    protected static bool $isDiscovered = true;
    protected int|string|array $columnSpan = 'full';


    public array $full_data = [];
    public array $targetKosong = [];
    public array $dataPerTanggal = [];

    public function mount(): void
    {
        $this->loadData();
        // dd($this->full_data, $this->targetKosong); // Aktifkan jika ingin debug hasil akhir
    }

    public function loadData()
    {
        $jsonPath = base_path('app/Data/target2.json');

        if (!File::exists($jsonPath)) {
            $this->full_data = [];
            return;
        }

        $listResourcesProduksi = json_decode(File::get($jsonPath), true);

        if (empty($listResourcesProduksi)) {
            $this->full_data = [];
            return;
        }

        $this->listResourceProduksi($listResourcesProduksi);
    }

    protected function listResourceProduksi($listResourcesProduksi)
    {
        $allResults = [];

        foreach ($listResourcesProduksi as $resources) {
            $dataDB = $resources["dbListName"] ?? null;
            if (!$dataDB)
                continue;

            $query_mesin = DB::table("mesins");

            // ! CARA 1
            // if (isset($dataDB["dbMesin"])) {
            //     $dbMesinName = $dataDB["dbMesin"]["dbName"];
            //     $dbMesinKeyId = $dataDB["dbMesin"]["key_id_mesin"];
            //     $query_mesin->join($dbMesinName, "$dbMesinName.$dbMesinKeyId", "=", "mesins.id");
            // }

            // $data_mesin_hasil = $query_mesin
            //     ->select("mesins.id AS id_mesin", "mesins.nama_mesin AS nama_mesin")
            //     ->groupBy("mesins.id", "mesins.nama_mesin")
            //     ->get();
            
            // ! CARA 2
            $data_mesin_hasil = $query_mesin
                ->join("kategori_mesins", "kategori_mesins.id", "=", "mesins.kategori_mesin_id")
                ->where("kategori_mesins.id", "=", $dataDB['id_kategori_mesin']);

            if(isset($dataDB['dbMesin']['spesifik'])){
                $spesifik = $dataDB['dbMesin']['spesifik'];
                $data_mesin_hasil = $data_mesin_hasil->where("mesins.nama_mesin", "LIKE", "%$spesifik%"); // Menambahkan filter nama mesin
            }    
            $data_mesin_hasil = $data_mesin_hasil
                ->select("mesins.id AS id_mesin", "mesins.nama_mesin AS nama_mesin")
                ->groupBy("mesins.id", "mesins.nama_mesin")
                ->orderBy('mesins.nama_mesin', 'ASC')
                ->get();

            // Kumpulkan hasil dari setiap mesin kedalam satu array besar
            $resultsFromMesin = $this->forMesin($data_mesin_hasil, $dataDB, $resources["name"]);
            $allResults = array_merge($allResults, $resultsFromMesin);
        }

        $this->full_data = $allResults;
    }

    protected function forMesin($data_mesin_hasil, $dataDB, $resourceLabel)
    {
        $mesinResults = [];

        foreach ($data_mesin_hasil as $data_mesin) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(6)->format('Y-m-d');
            $id_mesin = $data_mesin->id_mesin;
            $nama_mesin = $data_mesin->nama_mesin;

            $table_produksi = $dataDB['dbName'];
            $key_tanggal = $dataDB['key_tanggal'];

            $query_tanggal = DB::table($table_produksi);

            if (isset($dataDB["joins"]) && isset($dataDB["dbMesin"]) ) {
                $dbMesinName = $dataDB["dbMesin"]["dbName"];
                $dbMesinKeyId = $dataDB["dbMesin"]["key_id_mesin"];

                foreach ($dataDB["joins"] as $join) {
                    $query_tanggal->join($join[0], $join[1], $join[2], $join[3]);
                }
                $query_tanggal->where("$dbMesinName.$dbMesinKeyId", $id_mesin);
            } else if (isset($dataDB["dbMesin"])) {
                $query_tanggal->where("id_mesin", $id_mesin);
            }

            // Simpan hasil get() ke variabel agar bisa diiterasi
            $list_tanggal = $query_tanggal
                ->whereBetween($key_tanggal, [$startDate, $endDate])
                ->selectRaw("MIN($table_produksi.id) AS id, $key_tanggal AS tanggal")
                ->groupBy($key_tanggal)
                ->orderBy($key_tanggal, "ASC")
                ->get();

            // Reset data per tanggal untuk setiap mesin baru
            $this->dataPerTanggal = [];

            foreach ($list_tanggal as $tgl) {
                $this->forTargetHasil($dataDB, $id_mesin, $tgl, $nama_mesin);
            }

            // --- KALKULASI MINGGUAN (DI LUAR LOOP TANGGAL) ---
            $collectionHarian = collect($this->dataPerTanggal);
            $totalMingguan = $collectionHarian->sum("total_harian");
            $targetMingguan = $collectionHarian->sum("target_harian");
            $jumlahData = $collectionHarian->count();

            $targetRataRata = $jumlahData > 0 ? $targetMingguan / $jumlahData : 0;
            $progressMingguan = $targetMingguan > 0 ? min(round(($totalMingguan / $targetMingguan) * 100, 1), 100) : 0;

            $todayStr = now()->format('Y-m-d');
            $data_hari_ini = $collectionHarian->where("tanggal_produksi", $todayStr)->first();

            $mesinResults[] = [
                "nama_produksi" => $resourceLabel,
                "mesin" => $nama_mesin,
                "data_hari_ini" => $data_hari_ini,
                "data_minggu_ini" => [
                    "data" => $this->dataPerTanggal,
                    "total_mingguan" => $totalMingguan,
                    "target_mingguan" => $targetMingguan,
                    "progress_mingguan" => $progressMingguan,
                    "target_rata_rata_mingguan" => $targetRataRata
                ],
                "target_kosong" => array_values(array_unique($this->targetKosong, SORT_REGULAR)),
            ];
        }

        return $mesinResults;
    }

    protected function forTargetHasil($dataDB, $id_mesin, $tgl, $nama_mesin)
    {
        $allProgressForThisDay = collect();

        foreach ($dataDB["dbHasilName"] as $hasil) {
            $table_name = $dataDB["dbName"];
            $table_hasil = $hasil["dbName"];
            $key_id_relation = $dataDB["key_id_relation"];
            $keyJumlah = $hasil["key_jumlah"];

            $ukuranSql = "CONCAT(TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ', TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ', TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR))))";

            $queryProgress = DB::table($table_name)
                ->join($table_hasil, "$table_hasil.$key_id_relation", "=", "$table_name.id")
                ->join("jenis_kayus", "jenis_kayus.id", "=", "$table_hasil.id_jenis_kayu")
                ->join("ukurans", "ukurans.id", "=", "$table_hasil.id_ukuran");


            $rows = $queryProgress
                ->where("$table_name.id", "=", $tgl->id)
                ->selectRaw("
                    $table_hasil.id_jenis_kayu AS id_kayu,
                    $table_hasil.id_ukuran AS id_ukuran,
                    $ukuranSql AS ukuran_formatted,
                    jenis_kayus.nama_kayu AS nama_kayu,
                    SUM(CAST($table_hasil.$keyJumlah AS UNSIGNED)) AS total
                ")
                ->groupBy('id_kayu', 'id_ukuran', 'nama_kayu', 'ukuran_formatted')
                ->get()
                ->map(function ($rowProduksi) use ($id_mesin, $nama_mesin) {
                    return $this->mapTarget($rowProduksi, $id_mesin, $nama_mesin);
                });

            $allProgressForThisDay = $allProgressForThisDay->concat($rows);
        }

        $this->dataPerTanggal[] = [
            "tanggal_produksi" => (string) $tgl->tanggal,
            "total_harian" => $allProgressForThisDay->sum('total_produksi'),
            "target_harian" => $allProgressForThisDay->sum('target'),
            "progress_harian" => $allProgressForThisDay->sum('target') > 0
                ? min(round(($allProgressForThisDay->sum('total_produksi') / $allProgressForThisDay->sum('target')) * 100, 1), 100)
                : 0,
            "detail" => $allProgressForThisDay->toArray(),
        ];
    }

    protected function mapTarget($rowProduksi, $id_mesin, $nama_mesin)
    {
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
            "total_produksi" => (int) $rowProduksi->total,
            "target" => (int) $targetValue,
            "progress" => $targetValue > 0 ? min(round(($rowProduksi->total / $targetValue) * 100, 1), 100) : 0,
            'mesin' => $nama_mesin,
            'kayu' => $rowProduksi->nama_kayu,
            'ukuran' => $rowProduksi->ukuran_formatted,
            'id_mesin' => $id_mesin,
            'id_kayu' => $rowProduksi->id_kayu,
            'id_ukuran' => $rowProduksi->id_ukuran,
        ];
    }
}