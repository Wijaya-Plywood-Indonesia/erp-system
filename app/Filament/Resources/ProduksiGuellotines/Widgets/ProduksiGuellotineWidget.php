<?php

namespace App\Filament\Resources\ProduksiGuellotines\Widgets;

use App\Models\hasil_guellotine;
use App\Models\pegawai_guellotine;
use App\Models\produksi_guellotine;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ProduksiGuellotineWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-guellotines.widgets.produksi-guellotine-widget';


    protected int|string|array $columnSpan = 'full';

    public ?produksi_guellotine $record = null;

    public array $summary = [];

    // /*
    public function mount(?produksi_guellotine $record = null): void
    {
        if (! $record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // 1. TOTAL PRODUKSI (LEMBAR)
        // ======================
        $totalAll = hasil_guellotine::where('id_produksi_guellotine', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // ======================
        // 2. TOTAL PEGAWAI (UNIK)
        // ======================
        $totalPegawai = pegawai_guellotine::where('id_produksi_guellotine', $produksiId)
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');


        // ======================
        // 3. GLOBAL UKURAN + KW
        // ======================
        // $globalUkuranKw = hasil_guellotine::query()
        //     ->where('id_produksi_guellotine', $produksiId)
        //     ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
        //     ->selectRaw('
        //         CONCAT(
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
        //         ) AS ukuran,
        //         hasil_guellotine.id_jenis_kayu,
        //         SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS total
        //     ')
        //     ->groupBy('ukuran', 'hasil_guellotine.id_jenis_kayu')
        //     ->orderBy('ukuran')
        //     ->orderBy('hasil_guellotine.id_jenis_kayu')
        //     ->get();
        // ======================
        // 3. GLOBAL UKURAN + kayu
        // ======================
        $globalUkuranKayu = hasil_guellotine::query()
            ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'hasil_guellotine.id_jenis_kayu')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran_label,
                jenis_kayus.nama_kayu AS jenis_kayu_label,
                SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS jumlah
            ')
            ->groupBy('ukuran_label', 'jenis_kayu_label')
            ->orderBy('jenis_kayu_label')
            ->get()
            ->toArray();

        // ======================
        // 4. GLOBAL UKURAN (SEMUA KW)
        // ======================
        // $globalUkuran = hasil_guellotine::query()
        //     ->where('id_produksi_guellotine', $produksiId)
        //     ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
        //     ->selectRaw('
        //         CONCAT(
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
        //             TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
        //         ) AS ukuran,
        //         SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS total
        //     ')
        //     ->groupBy('ukuran')
        //     ->orderBy('ukuran')
        //     ->get();

        $globalUkuran = hasil_guellotine::query()
            ->where('id_produksi_guellotine', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'hasil_guellotine.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(hasil_guellotine.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKayu' => $globalUkuranKayu,
            'globalUkuran'   => $globalUkuran,
        ];
    }
        // */
}