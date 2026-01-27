<?php

namespace App\Filament\Resources\ProduksiKedis\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiKedi;
use App\Models\DetailBongkarKedi;
use App\Models\DetailPegawaiKedi; // Tambahkan import ini

class ProduksiKediSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-kedis.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiKedi $record = null;

    public array $summary = [];

    public function mount(?ProduksiKedi $record = null): void
    {
        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        // 1. TOTAL HASIL PRODUKSI (DARI TABEL BONGKAR)
        $totalAll = DetailBongkarKedi::where('id_produksi_kedi', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        // 2. TOTAL PEGAWAI UNIK (DARI TABEL PEGAWAI KEDI)
        // Menggunakan distinct agar jika 1 orang punya 2 tugas tetap dihitung 1 orang
        $totalPegawai = DetailPegawaiKedi::where('id_produksi_kedi', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 3. GLOBAL UKURAN + KW
        $globalUkuranKw = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_bongkar_kedi.kw,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'detail_bongkar_kedi.kw')
            ->orderBy('ukuran')
            ->orderBy('detail_bongkar_kedi.kw')
            ->get();

        // 4. GLOBAL UKURAN (SEMUA KW)
        $globalUkuran = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // SIMPAN SEMUA KE ARRAY SUMMARY UNTUK DIKIRIM KE BLADE
        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai, // Baris ini yang sebelumnya hilang
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}