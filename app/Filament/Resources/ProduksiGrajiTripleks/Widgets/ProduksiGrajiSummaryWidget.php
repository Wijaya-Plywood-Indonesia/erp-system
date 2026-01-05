<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiGrajitriplek;
use App\Models\HasilGrajiTriplek;

class ProduksiGrajiSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-graji.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiGrajitriplek $record = null;

    public array $summary = [];

    public function mount(?ProduksiGrajitriplek $record = null): void
    {
        if (!$record) return;

        $produksiId = $record->id;

        // 1. TOTAL HASIL
        $totalAll = HasilGrajiTriplek::where('id_produksi_graji_triplek', $produksiId)
            ->sum('isi');

        // 2. TOTAL PEGAWAI
        $totalPegawai = $record->pegawaiGrajiTriplek()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ==========================================================
        // 3. GLOBAL UKURAN + KATEGORI (Via Grade) + GRADE
        // ==========================================================
        $globalUkuranKw = HasilGrajiTriplek::query()
            ->where('hasil_graji_triplek.id_produksi_graji_triplek', $produksiId)

            // A. Join ke Barang Setengah Jadi
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_graji_triplek.id_barang_setengah_jadi_hp')

            // B. Join ke Ukuran
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')

            // C. Join ke Grade (Jembatan ke Kategori)
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')

            // D. Join ke KATEGORI BARANG
            // ⚠️ PERBAIKAN: Ubah 'kategori_barangs' (jamak) menjadi 'kategori_barang' (tunggal)
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')

            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                
                -- 👇 Gabungkan Nama Kategori + Nama Grade
                -- Pastikan menggunakan "kategori_barang.nama_kategori"
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                
                SUM(hasil_graji_triplek.isi) AS total
            ')

            // Group By (Sesuaikan nama tabelnya juga)
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')

            // Sorting
            ->orderBy('ukuran')
            ->orderBy('kategori_barang.nama_kategori')
            ->orderBy('grades.nama_grade')
            ->get();

        // 4. GLOBAL UKURAN SAJA
        $globalUkuran = HasilGrajiTriplek::query()
            ->where('hasil_graji_triplek.id_produksi_graji_triplek', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_graji_triplek.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_graji_triplek.isi) AS total
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
