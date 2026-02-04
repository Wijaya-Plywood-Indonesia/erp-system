<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CardProduksiOverwiew extends Widget
{
    protected static bool $isDiscovered = true;
    protected string $view = 'filament.widgets.card-produksi-overwiew';
    public array $cards = [];
    protected int | string | array $columnSpan = 'full';

    public function mount(): void
    {
        $this->loadAndProcessData();
    }

    public function loadAndProcessData()
    {
        $jsonPath = base_path('app/Data/cai2.json');
        if (!File::exists($jsonPath)) return;
        
        $listProduksi = json_decode(File::get($jsonPath), true);
        $processedCards = [];

        foreach ($listProduksi as $produksi) {
            $totalProduksi = 0;
            $rekapUtama = [];
            $uniquePegawai = collect();

            $listShowData = $produksi['dbListName']['dbHasilName'] ?? [];

            foreach ($listShowData as $hasilConfig) {
                $rekapGlobal = [];
                $rekapDetail = [];

                $tableName = $hasilConfig['dbName'];
                $keyJumlah = $hasilConfig['key_jumlah'];
                $satuanKwalitas = $hasilConfig['satuan_kualitas'] ?? 'kw';

                // SQL format ukuran sesuai kebutuhan resource Anda (Trim .00)
                $ukuranSql = "CONCAT(
                    TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
                    TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
                    TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
                )";

                $query = DB::table($tableName)->whereDate("$tableName.created_at", now()->today());

                // Logic Join Berantai
                if (isset($hasilConfig['joins'])) {
                    foreach ($hasilConfig['joins'] as $join) {
                        $query->join($join[0], $join[1], $join[2], $join[3]);
                    }
                } else {
                    $query->join('ukurans', 'ukurans.id', '=', "$tableName.id_ukuran");
                }

                $kwRaw = $hasilConfig['custom_kw'] ?? "$tableName.$satuanKwalitas";
                $pegawaiCol = $hasilConfig['pegawai_join_key'] ?? null;

                // Ambil Data Rekap & Pegawai dalam satu query hasil jika memungkinkan
                $data = $query->selectRaw("
                    $ukuranSql as ukuran,
                    $kwRaw as kualitas,
                    SUM(CAST($tableName.$keyJumlah AS UNSIGNED)) as total
                ")
                ->groupBy(DB::raw("ukuran"), DB::raw($kwRaw))
                ->get();

                $totalProduksi += $data->sum('total');

                // Mapping Rekap
                foreach ($data->groupBy('ukuran') as $ukuran => $items) {
                    $rekapGlobal[] = ['ukuran_title' => $ukuran, 'jumlah' => $items->sum('total')];
                    foreach ($items as $item) {
                        $rekapDetail[] = [
                            'ukuran_title' => $item->ukuran . " (" . ($item->kualitas ?? '') . ")",
                            'jumlah' => $item->total
                        ];
                    }
                }

                $rekapUtama[] = [
                    'name' => $hasilConfig['name'],
                    'rekap' => [
                        [
                            'title' => "Hasil Berdasrkan Kualitas",
                            'data' => $rekapDetail,
                        ],
                        [
                            'title' => "Hasil Berdasrkan Ukuran",
                            'data' => $rekapGlobal,
                        ]
                    ]
                ];
            }


            // Logic Pegawai yang lebih akurat
            $pegawaiTable = $produksi['dbListName']['dbPegawaiName'] ?? null;
            $totalPegawai = 0;

            if ($pegawaiTable) {
                // Khusus untuk Repair atau yang punya join kompleks di JSON
                $totalPegawai = DB::table($pegawaiTable)
                    ->whereDate("$pegawaiTable.created_at", now()->today())
                    ->whereNotNull('id_pegawai')
                    ->distinct('id_pegawai')
                    ->count('id_pegawai');
            }

            $processedCards[] = [
                'name' => $produksi['name'],
                'urlResource' => $produksi['urlResource'],
                'total_produksi' => $totalProduksi,
                'total_pegawai' => $totalPegawai,
                'satuan_hasil' => $listShowData[0]['satuan_hasil'] ?? 'Pcs',
                'data_rekap' => $rekapUtama,
                'additional_info' => $produksi['addtional_info'] ?? null,
            ];

        }

        

        $this->cards = $processedCards;
    }

    protected function getViewData(): array
    {
        return ['cards' => $this->cards];
    }
}