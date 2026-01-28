<?php

namespace App\Filament\Resources\ProduksiDempuls\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiDempul;
use App\Models\DetailDempul;

class ProduksiDempulSummaryWidget extends Widget
{
    // Pastikan view ini mengarah ke file blade yang benar
    protected string $view = 'filament.resources.produksi-dempul.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiDempul $record = null;

    public array $summary = [];

    public function mount(?ProduksiDempul $record = null): void
    {
        if (!$record) {
            return;
        }

        $produksiId = $record->id;

        // ======================
        // 1. TOTAL KESELURUHAN (HASIL)
        // ======================
        $totalAll = DetailDempul::where('id_produksi_dempul', $produksiId)
            ->sum(DB::raw('CAST(hasil AS UNSIGNED)'));

        // ======================
        // 2. TOTAL PEGAWAI (HEADCOUNT / ORANG) - [BARU]
        // ======================
        // Logika: Ambil detail -> Load Pegawai -> Gabung -> Hapus Duplikat
        $totalPegawai = DetailDempul::where('id_produksi_dempul', $produksiId)
            ->with('pegawais')  // Load relasi many-to-many
            ->get()
            ->pluck('pegawais') // Ambil hanya collection pegawainya
            ->flatten()         // Gabungkan array pegawai yang terpisah-pisah
            ->unique('id')      // Filter agar 1 ID Pegawai hanya dihitung 1 kali
            ->count();          // Hitung jumlah akhirnya

        // ======================
        // 3. GLOBAL UKURAN + KW (GRADE)
        // ======================
        $globalUkuranKw = DetailDempul::query()
            ->where('detail_dempuls.id_produksi_dempul', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'detail_dempuls.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,  
                SUM(CAST(detail_dempuls.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->orderBy('grades.nama_grade')
            ->get();

        // ======================
        // 4. GLOBAL UKURAN (SEMUA KW)
        // ======================
        $globalUkuran = DetailDempul::query()
            ->where('detail_dempuls.id_produksi_dempul', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'detail_dempuls.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(CAST(detail_dempuls.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai, 
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}
