<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
class CardMurni extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.card-produksi-overwiew';

    public array $cards = [];

    public function mount(): void
    {
        $this->loadAndProcessData();
        dd($this->cards);
    }
    protected int | string | array $columnSpan = 'full';

    public function loadAndProcessData()
    {
        // 1. Ambil data dari JSON
        $jsonPath = base_path('app/Data/resources.json');
        $listProduksi = json_decode(File::get($jsonPath), true);

        $processedCards = [];

        foreach ($listProduksi as $produksi) {
            $totalProduksi = 0;
            $rekapKualitasUkuran = [];
            $rekapKualitasUkuran2 = [];


            // 2. Query untuk setiap Tabel Hasil (Bisa lebih dari satu per Produksi)
            foreach ($produksi['dbListName']['dbHasilName'] as $hasilConfig) {
                $tableName = $hasilConfig['dbName'];

                $keyJumlah = $hasilConfig['key_jumlah'];

                // Ambil ID Product Today
                $idProduct = DB::table($tableName)
                ->whereDate('created_at','>=', now()->startOfDay())
                ->orderBy('id','desc')
                ->first();

                // Ambil Total Produksi
                $sum = DB::table($tableName)
                ->whereDate('created_at','>=', now()->startOfDay())
                ->sum(DB::raw(
                    'CAST('.$keyJumlah.' AS UNSIGNED)'
                    ));
                $totalProduksi += $sum;

                // Ambil Rekap Ukuran + Kualitas (Join ke tabel ukurans)

                
                // Kita asumsikan struktur kolom: id_ukuran, id_jenis_kayu/grade/kw, dan jumlah
                $detail = DB::table($tableName)
                    // Gunakan nama tabel agar tidak ambigu (tableName.created_at)
                    ->whereDate("$tableName.created_at", now()->today())
                    ->join('ukurans', 'ukurans.id', '=', "$tableName.id_ukuran")
                    ->selectRaw("
                        CONCAT(ukurans.panjang, ' x ', ukurans.lebar, ' x ', ukurans.tebal) as ukuran,
                        SUM(CAST($keyJumlah AS UNSIGNED)) as total
                    ")
                    ->groupBy('ukuran')
                    ->get()
                    ->map(fn($item) => [
                        'ukuran_title' => $item->ukuran,
                        'jumlah' => $item->total
                    ])
                    ->toArray();
                
                $satuan_kualitas = $hasilConfig['satuan_kualitas'];
                $detail2 = DB::table($tableName)
                    // Gunakan nama tabel agar tidak ambigu (tableName.created_at)
                    ->whereDate("$tableName.created_at", now()->today())
                    ->join('ukurans', 'ukurans.id', '=', "$tableName.id_ukuran")
                    ->selectRaw("
                        CONCAT(ukurans.panjang, ' x ', ukurans.lebar, ' x ', ukurans.tebal) as ukuran,
                        $tableName.$satuan_kualitas as kualitas,
                        SUM(CAST($keyJumlah AS UNSIGNED)) as total
                    ")
                    ->groupBy('ukuran', "{$tableName}.{$satuan_kualitas}")
                    ->get()
                    ->map(fn($item) => [
                        'ukuran_title' => "{$item->ukuran} {$item->kualitas}",
                        'jumlah' => $item->total
                    ])
                    ->toArray();

                $rekapKualitasUkuran = array_merge($rekapKualitasUkuran, $detail);
                $rekapKualitasUkuran2 = array_merge($rekapKualitasUkuran2, $detail2);
            }

            // 3. Query Total Pegawai (Unik)
            $totalPegawai = DB::table($produksi['dbListName']['dbPegawaiName'])
            
                ->whereDate('created_at','>=', now()->startOfDay())
                ->whereNotNull('id_pegawai')
                ->distinct('id_pegawai')
                ->count('id_pegawai');

            // 4. Masukkan ke Array untuk Blade
            $processedCards[] = [
                'name' => $produksi['name'],
                'urlResource' => $produksi['urlResource'],
                'total_produksi' => $totalProduksi,
                'total_pegawai' => $totalPegawai,
                'satuan_hasil' => $produksi['dbListName']['dbHasilName'][0]['satuan_hasil'] ?? 'Pcs',
                'data_rekap' => [
                    [
                        "title" => "Detail Ukuran Kayu (Global)",
                        "data" => $rekapKualitasUkuran,
                    ],
                    [
                        "title" => "Detail Ukuran Kayu + KW",
                        "data" => $rekapKualitasUkuran2,
                    ],
                ],
                'additional_info' => $produksi['addtional_info'] ?? null,
            ];
        }

        $this->cards = $processedCards;
    }

    public function customProduksiRepair()
    {
        // ! REPAIR
            //     $globalUkuranKw = HasilRepair::query()
            // ->where('hasil_repairs.id_produksi_repair', $produksiId)
            // ->join('rencana_repairs', 'rencana_repairs.id', '=', 'hasil_repairs.id_rencana_repair')
            // ->join('rencana_pegawais', 'rencana_pegawais.id', '=', 'rencana_repairs.id_rencana_pegawai')
            // ->join('modal_repairs', 'modal_repairs.id', '=', 'rencana_repairs.id_modal_repair')
            // ->join('ukurans', 'ukurans.id', '=', 'modal_repairs.id_ukuran')
            // ->selectRaw('
            //     CONCAT(
            //         TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
            //         TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
            //         TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
            //     ) AS ukuran,
            //     rencana_repairs.kw,
            //     SUM(CAST(hasil_repairs.jumlah AS UNSIGNED)) AS total,
            //     COUNT(DISTINCT rencana_pegawais.id_pegawai) AS jumlah_orang
            // ')
            // ->groupBy('ukuran', 'rencana_repairs.kw')
            // ->orderBy('ukuran')
            // ->orderBy('rencana_repairs.kw')
            // ->get();

        //   $globalUkuran = $globalUkuranKw->groupBy('ukuran')->map(function ($rows) {
        // return (object) [
        // 'ukuran' => $rows->first()->ukuran,
        // 'total' => $rows->sum('total'),
        // 'total_orang' => $rows->sum('jumlah_orang')
        // ];
        // })->values();

        // 
    }

    protected function getViewData(): array
    {
        return $this->cards;
    }

}


