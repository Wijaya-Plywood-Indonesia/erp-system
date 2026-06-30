<?php

namespace App\Filament\Resources\ProduksiSandings\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiSanding;
use App\Models\HasilSanding;

class ProduksiSandingSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-sanding.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiSanding $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Sanding
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.sanding.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiSanding $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi Refresh Data Real-time
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. TOTAL KUANTITAS
        $totalAll = HasilSanding::where('id_produksi_sanding', $produksiId)
            ->sum('kuantitas');

        // 2. TOTAL PEGAWAI
        $totalPegawai = $this->record->pegawaiSandings()
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Query Dasar Ukuran
        $baseQuery = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran
            ');

        // 3. GLOBAL UKURAN + KATEGORI + GRADE
        $globalUkuranKw = (clone $baseQuery)
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL UKURAN SAJA
        $globalUkuran = (clone $baseQuery)
            ->selectRaw('SUM(hasil_sandings.kuantitas) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // 5. GLOBAL JENIS KAYU & UKURAN
        $globalJenisKayuUkuran = HasilSanding::query()
            ->where('hasil_sandings.id_produksi_sanding', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'hasil_sandings.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'barang_setengah_jadi_hp.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                jenis_barang.nama_jenis_barang as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) as kw,
                SUM(hasil_sandings.kuantitas) AS total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('jenis_barang.nama_jenis_barang')
            ->orderBy('ukuran')
            ->get();

        // Determine dominant item in Sanding
        $idMesin = $this->record->id_mesin;
        $namaMesin = $this->record->mesin?->nama_mesin ?? '';
        
        $isSengon = true;
        $maxQty = -1;
        $dominantHasil = HasilSanding::where('id_produksi_sanding', $produksiId)
            ->with(['barangSetengahJadi.jenisBarang'])
            ->get();
        foreach ($dominantHasil as $hasil) {
            $qty = $hasil->kuantitas ?? 0;
            if ($qty > $maxQty) {
                $maxQty = $qty;
                $b = $hasil->barangSetengahJadi;
                if ($b) {
                    $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);
                }
            }
        }

        if (!$isSengon) {
            $target = 450;
        } else {
            $target = 250;
            if ($idMesin == 24 || stripos($namaMesin, 'besar') !== false) {
                $target = 800;
            }
        }

        $globalProgress = $target > 0 ? ($totalAll / $target) * 100 : 0;
        $globalProgress = round($globalProgress, 1);

        $deficit = $target - $totalAll;
        $potonganPerOrang = 0;
        if ($deficit > 0 && $totalPegawai > 0) {
            $potonganRaw = ($deficit * 115000) / ($target * $totalPegawai);

            // --- RUMUS PEMBULATAN KHUSUS (0, 500, 1000) ---
            $ribuan = floor($potonganRaw / 1000);
            $ratusan = $potonganRaw % 1000;

            if ($ratusan < 300) {
                $potonganPerOrang = $ribuan * 1000;
            } elseif ($ratusan < 800) {
                $potonganPerOrang = ($ribuan * 1000) + 500;
            } else {
                $potonganPerOrang = ($ribuan + 1) * 1000;
            }
        }

        $this->summary = [
            'totalAll'              => $totalAll,
            'totalPegawai'          => $totalPegawai,
            'globalUkuranKw'        => $globalUkuranKw,
            'globalUkuran'          => $globalUkuran,
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'target'                => $target,
            'globalProgress'        => $globalProgress,
            'potonganPerOrang'      => $potonganPerOrang,
            'deficit'               => $deficit,
        ];
    }
}
