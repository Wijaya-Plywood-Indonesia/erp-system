<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
class CardAi extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.card-produksi-overwiew';

    public array $cards = [];

    public function mount(): void
    {
        $this->loadAndProcessData();
        dd($this->cards);
    }
    protected int|string|array $columnSpan = 'full';

    public function loadAndProcessData()
    {
        // Pastikan record utama ada
        if (!$this->record)
            return;
        $produksiId = $this->record->id;

        $jsonPath = base_path('app/Data/cai.json');
        if (!File::exists($jsonPath))
            return;

        $listProduksi = json_decode(File::get($jsonPath), true);
        $processedCards = [];

        foreach ($listProduksi as $produksi) {
            $totalProduksi = 0;
            $rekapKualitasUkuran = [];

            // AMBIL ID PRODUKSI UNTUK HARI INI
            // Misalnya dari tabel 'produksi_guellotine' yang dibuat hari ini
            $todayProductionIds = DB::table($produksi['dbListName']['dbName'])
                ->whereDate('created_at', today())
                ->pluck('id') // Ambil semua ID yang ada hari ini
                ->toArray();

            // Jika tidak ada produksi sama sekali hari ini, kita bisa skip atau set 0
            if (empty($todayProductionIds)) {
                $processedCards[] = [
                    'name' => $produksi['name'],
                    'urlResource' => $produksi['urlResource'],
                    'total_produksi' => 0,
                    'total_pegawai' => 0,
                    'satuan_hasil' => $produksi['dbListName']['dbHasilName'][0]['satuan_hasil'] ?? 'Pcs',
                    'rekap_ukuran' => [],
                    'additional_info' => $produksi['addtional_info'] ?? null,
                ];
                continue; // Lanjut ke jenis produksi berikutnya
            }

            foreach ($produksi['dbListName']['dbHasilName'] as $hasilConfig) {
                $tableName = $hasilConfig['dbName'];
                $keyJumlah = $hasilConfig['key_jumlah'];

                // Ambil Total Produksi berdasarkan ID yang ditemukan tadi
                $sum = DB::table($tableName)
                    ->whereIn($this->getProduksiForeignKey($tableName), $todayProductionIds)
                    ->sum(DB::raw("CAST($keyJumlah AS UNSIGNED)"));
                $totalProduksi += $sum;

                // Kirim $todayProductionIds ke fungsi rekap
                $detail = $this->getRekapKualitas($hasilConfig, $todayProductionIds);
                $rekapKualitasUkuran = array_merge($rekapKualitasUkuran, $detail);
            }

            // Query Total Pegawai berdasarkan ID yang ditemukan tadi
            $totalPegawai = DB::table($produksi['dbListName']['dbPegawaiName'])
                ->whereIn($this->getProduksiForeignKey($produksi['dbListName']['dbName']), $todayProductionIds)
                ->whereNotNull('id_pegawai')
                ->distinct('id_pegawai')
                ->count('id_pegawai');

            // ... (lanjutkan ke bagian processedCards)
        }

        $this->cards = $processedCards;
    }

    public function getRekapKualitas($hasilConfig, array $productionIds)
    {
        $tableName = $hasilConfig['dbName'];
        $keyJumlah = $hasilConfig['key_jumlah'] ?? 'jumlah';
        $kwExpression = $hasilConfig['kw_expression'] ?? "'N/A'";

        $query = DB::table($tableName);

        // Dynamic Join... (sama seperti kode sebelumnya)
        if (isset($hasilConfig['join_paths'])) {
            foreach ($hasilConfig['join_paths'] as $path) {
                $targetTable = $path['table'];
                $foreignKey = $path['on'];
                $localKey = str_contains($foreignKey, '.') ? $foreignKey : "$tableName.$foreignKey";
                $query->join($targetTable, "$targetTable.id", '=', $localKey);
            }
        }

        if (!str_contains(json_encode($hasilConfig['join_paths'] ?? []), 'ukurans')) {
            $query->join('ukurans', 'ukurans.id', '=', "$tableName.id_ukuran");
        }

        // GUNAKAN whereIn untuk memfilter banyak ID produksi sekaligus
        return $query
            ->whereIn("$tableName." . $this->getProduksiForeignKey($tableName), $productionIds)
            ->selectRaw("
            CONCAT(
                TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
                TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
                TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
            ) AS ukuran,
            $kwExpression AS kw,
            SUM(CAST($tableName.$keyJumlah AS UNSIGNED)) AS total
        ")
            ->groupBy('ukuran', DB::raw($kwExpression))
            ->orderBy('ukuran')
            ->get()
            ->map(fn($item) => [
                'label' => "{$item->ukuran} ({$item->kw})",
                'total' => (int) $item->total,
                'satuan' => $hasilConfig['satuan_hasil']
            ])->toArray();
    }

    /**
     * Helper untuk menentukan nama kolom foreign key produksi secara otomatis
     * Contoh: tabel 'hasil_repairs' biasanya punya 'id_produksi_repair'
     */
    private function getProduksiForeignKey($tableName)
    {
        // Logika sederhana: ambil kata setelah 'produksi_' atau tebak berdasarkan nama tabel
        // Kamu bisa juga menambahkan ini di JSON jika nama kolomnya terlalu unik
        if (str_contains($tableName, 'dempul'))
            return 'id_produksi_dempul';
        if (str_contains($tableName, 'repair'))
            return 'id_produksi_repair';

        // Fallback umum: id_produksi + nama_tabel_tanpa_hasil
        $cleanName = str_replace(['hasil_', 'detail_', 'platform_'], '', $tableName);
        return "id_produksi_" . $cleanName;
    }

    protected function getViewData(): array
    {
        return $this->cards;
    }

}


