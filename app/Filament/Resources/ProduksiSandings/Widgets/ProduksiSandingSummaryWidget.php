<?php

namespace App\Filament\Resources\ProduksiSandings\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiSanding;
use App\Models\HasilSanding;

class ProduksiSandingSummaryWidget extends Widget
{
    // Pastikan path view ini sesuai dengan folder Anda
    protected string $view = 'filament.resources.produksi-sanding.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiSanding $record = null;

    public array $summary = [];

    public function mount(?ProduksiSanding $record = null): void
    {
        if (!$record) return;

        $produksiId = $record->id;

        // ==========================================================
        // 1. TOTAL KUANTITAS (HASIL SANDING)
        // ==========================================================
        $totalAll = HasilSanding::where('id_produksi_sanding', $produksiId)
            ->sum('kuantitas');

        // ==========================================================
        // 2. TOTAL PEGAWAI (HEADCOUNT)
        // ==========================================================
        // Menggunakan relasi 'pegawaiSandings' dari Model ProduksiSanding
        $totalPegawai = $record->pegawaiSandings()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // ==========================================================
        // 3. GLOBAL UKURAN + KATEGORI + GRADE
        // ==========================================================
        $globalUkuranKw = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)

            // A. Join ke Barang Setengah Jadi
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')

            // B. Join ke Ukuran
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')

            // C. Join ke Grade
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')

            // D. Join ke Kategori Barang (Lewat Grade)
            // ⚠️ PERBAIKAN DISINI: Ubah 'kategori_barangs' menjadi 'kategori_barang'
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')

            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                
                -- Gabungkan Kategori + Grade (Contoh: PLYWOOD UTY)
                -- Pastikan menggunakan tabel "kategori_barang" (tanpa s)
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                
                SUM(hasil_sandings.kuantitas) AS total
            ')

            // Group By juga harus disesuaikan nama tabelnya
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->orderBy('kategori_barang.nama_kategori')
            ->orderBy('grades.nama_grade')
            ->get();

        // ==========================================================
        // 4. GLOBAL UKURAN SAJA (RINGKASAN)
        // ==========================================================
        $globalUkuran = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "0" FROM TRIM(TRAILING "." FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // Masukkan ke array summary untuk dikirim ke Blade
        $this->summary = [
            'totalAll'       => $totalAll,
            'totalPegawai'   => $totalPegawai,
            'globalUkuranKw' => $globalUkuranKw,
            'globalUkuran'   => $globalUkuran,
        ];
    }
}
