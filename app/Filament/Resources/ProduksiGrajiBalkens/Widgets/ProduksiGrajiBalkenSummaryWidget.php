<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiGrajiBalken;
use App\Models\HasilGrajiBalken;
use App\Models\PegawaiGrajiBalken;

class ProduksiGrajiBalkenSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-graji-balken.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiGrajiBalken $record = null;

    public array $summary = [];

    public function mount(?ProduksiGrajiBalken $record = null): void
    {
        if (!$record) return;

        $produksiId = $record->id;

        // 1. TOTAL HASIL (SUM JUMLAH)
        $totalAll = HasilGrajiBalken::where('id_produksi_graji_balken', $produksiId)
            ->sum('jumlah');

        // 2. TOTAL PEGAWAI UNIK PADA PRODUKSI INI
        $totalPegawai = PegawaiGrajiBalken::where('id_produksi_graji_balken', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 3. GLOBAL UKURAN + JENIS KAYU
        $globalUkuranJenis = HasilGrajiBalken::query()
            ->where('id_produksi_graji_balken', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_graji_balken.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_graji_balken.id_jenis_kayu')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                jenis_kayus.nama_kayu as jenis_kayu,
                SUM(hasil_graji_balken.jumlah) AS total
            ')
            ->groupBy('ukuran', 'jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA JENIS KAYU)
        $globalUkuranSemua = HasilGrajiBalken::query()
            ->where('id_produksi_graji_balken', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_graji_balken.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_graji_balken.jumlah) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'          => $totalAll,
            'totalPegawai'      => $totalPegawai,
            'globalUkuranJenis' => $globalUkuranJenis,
            'globalUkuranSemua' => $globalUkuranSemua,
        ];
    }
}