<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\HasilPilihPlywood;
use App\Models\PegawaiPilihPlywood;

class ProduksiPilihPlywoodSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-pilih-plywood.widgets.hasil-pilih-plywood-summary';

    public ?Model $record = null;
    public array $summary = []; // Simpan data di sini agar bisa diakses Blade
    protected int | string | array $columnSpan = 'full';

    /**
     * LANGKAH 1: Aktifkan Listener Pusher
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.pilih_plywood.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * LANGKAH 2: Masukkan Logika Perhitungan di sini
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // Hitung Total Hasil (jumlah_bagus)
        $totalAll = HasilPilihPlywood::where('id_produksi_pilih_plywood', $produksiId)->sum('jumlah_bagus');

        // Hitung Headcount Pegawai
        $totalPegawai = PegawaiPilihPlywood::where('id_produksi_pilih_plywood', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // Determine target based on the dominant item
        $baseTarget = 450;
        $maxQty = -1;
        $dominantHasil = HasilPilihPlywood::where('id_produksi_pilih_plywood', $produksiId)
            ->with(['barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade.kategoriBarang', 'barangSetengahJadiHp.jenisBarang'])
            ->get();

        $dominantSizeName = '';

        foreach ($dominantHasil as $hasil) {
            $qty = $hasil->jumlah_bagus ?? 0;
            if ($qty > $maxQty) {
                $maxQty = $qty;
                $b = $hasil->barangSetengahJadiHp;
                if ($b) {
                    $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);
                    
                    if ($isSengon) {
                        $kategoriId = $b->grade?->id_kategori_barang ?? 0;
                        $kategoriNama = $b->grade?->kategoriBarang?->nama_kategori ?? '';
                        
                        $isNonSanding = ($kategoriId == 2 || stripos($kategoriNama, 'mentah') !== false || stripos($kategoriNama, 'non') !== false);
                        $isSanding = ($kategoriId == 1 || (stripos($kategoriNama, 'plywood') !== false && stripos($kategoriNama, 'mentah') === false));
                        
                        if ($isNonSanding) {
                            $baseTarget = 2200; // Sengon Non Sanding
                        } elseif ($isSanding) {
                            $baseTarget = 1950; // Sengon Sanding
                        } else {
                            $baseTarget = 450; // Selain 2 itu
                        }
                    } else {
                        $baseTarget = 450; // Selain 2 itu
                    }
                    $dominantSizeName = $b->ukuran?->nama_ukuran ?? '';
                }
            }
        }

        $target = $totalPegawai > 0 ? ($totalPegawai / 2.0) * $baseTarget : $baseTarget;

        $globalProgress = $target > 0 ? ($totalAll / $target) * 100 : 0;
        $globalProgress = round($globalProgress, 1);

        // Detail per Ukuran & Grade
        $listHasil = HasilPilihPlywood::query()
            ->where('id_produksi_pilih_plywood', $produksiId)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'hasil_pilih_plywood.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.panjang AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.lebar AS CHAR))), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade AS kw,
                SUM(hasil_pilih_plywood.jumlah) AS total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->get();

        $this->summary = [
            'totalAll' => $totalAll,
            'totalPegawai' => $totalPegawai,
            'listHasil' => $listHasil,
            'target' => $target,
            'globalProgress' => $globalProgress,
            'dominantSizeName' => $dominantSizeName,
        ];
    }
}
