<?php

namespace App\Filament\Resources\ProduksiHotPresses\Widgets;

use Filament\Widgets\Widget;
use App\Models\ProduksiHp;
use App\Models\PlatformHasilHp;
use App\Models\TriplekHasilHp;

class ProduksiHotPressSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-hotpress.widgets.summary';
    protected int|string|array $columnSpan = 'full';

    public ?ProduksiHp $record = null;
    public array $summary = [];

    /**
     * Listener Pusher untuk departemen Hot Press
     */
    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.hotpress.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiHp $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    /**
     * Fungsi utama untuk memperbarui data summary secara real-time
     */
    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $record = $this->record;
        $produksiId = $record->id;

        // 1. HITUNG PEGAWAI
        $totalPegawai = $record->detailPegawaiHp()
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 2. DATA HASIL PLATFORM
        $totalPlatform = PlatformHasilHp::where('id_produksi_hp', $produksiId)->sum('isi');

        $listPlatform = PlatformHasilHp::query()
            ->where('platform_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(platform_hasil_hp.isi) as total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 3. DATA HASIL TRIPLEK
        $totalTriplek = TriplekHasilHp::where('id_produksi_hp', $produksiId)->sum('isi');

        $listTriplek = TriplekHasilHp::query()
            ->where('triplek_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(triplek_hasil_hp.isi) as total
            ')
            ->groupBy('ukuran', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // 4. GLOBAL JENIS KAYU & UKURAN (Gabungan Platform & Triplek)
        $platformJenisKayuUkuran = PlatformHasilHp::query()
            ->where('platform_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'barang_setengah_jadi_hp.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                jenis_barang.nama_jenis_barang as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(platform_hasil_hp.isi) as total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'grades.nama_grade')
            ->get();

        $triplekJenisKayuUkuran = TriplekHasilHp::query()
            ->where('triplek_hasil_hp.id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
            ->join('ukurans', 'ukurans.id', '=', 'barang_setengah_jadi_hp.id_ukuran')
            ->join('jenis_barang', 'jenis_barang.id', '=', 'barang_setengah_jadi_hp.id_jenis_barang')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->selectRaw('
                jenis_barang.nama_jenis_barang as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                grades.nama_grade as kw,
                SUM(triplek_hasil_hp.isi) as total
            ')
            ->groupBy('jenis_barang.nama_jenis_barang', 'ukuran', 'grades.nama_grade')
            ->get();

        // Merge array
        $mergedJenisKayuUkuran = [];
        foreach ($platformJenisKayuUkuran as $item) {
            $key = $item->jenis_kayu . '|' . $item->ukuran . '|' . $item->kw;
            if (!isset($mergedJenisKayuUkuran[$key])) {
                $mergedJenisKayuUkuran[$key] = (object) [
                    'jenis_kayu' => $item->jenis_kayu,
                    'ukuran' => $item->ukuran,
                    'kw' => $item->kw,
                    'total' => 0
                ];
            }
            $mergedJenisKayuUkuran[$key]->total += $item->total;
        }

        foreach ($triplekJenisKayuUkuran as $item) {
            $key = $item->jenis_kayu . '|' . $item->ukuran . '|' . $item->kw;
            if (!isset($mergedJenisKayuUkuran[$key])) {
                $mergedJenisKayuUkuran[$key] = (object) [
                    'jenis_kayu' => $item->jenis_kayu,
                    'ukuran' => $item->ukuran,
                    'kw' => $item->kw,
                    'total' => 0
                ];
            }
            $mergedJenisKayuUkuran[$key]->total += $item->total;
        }
        
        $globalJenisKayuUkuran = array_values($mergedJenisKayuUkuran);
        usort($globalJenisKayuUkuran, function($a, $b) {
            if ($a->jenis_kayu === $b->jenis_kayu) {
                if ($a->ukuran === $b->ukuran) {
                    return strcmp($a->kw, $b->kw);
                }
                return strcmp($a->ukuran, $b->ukuran);
            }
            return strcmp($a->jenis_kayu, $b->jenis_kayu);
        });

        // 5. TARGET PROGRESS (MESIN HOTPRESS)
        $hotpressMachineIds = \Illuminate\Support\Facades\DB::table('mesins')
            ->join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'HOTPRESS')
            ->pluck('mesins.id')
            ->toArray();

        if (empty($hotpressMachineIds)) {
            $hotpressMachineIds = [13, 26, 27, 28];
        }

        $targets = \Illuminate\Support\Facades\DB::table('targets')
            ->whereIn('id_mesin', $hotpressMachineIds)
            ->get();

        // Platform Hasil grouped by id_ukuran
        $platformActuals = PlatformHasilHp::where('id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
            ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(platform_hasil_hp.isi) as total_actual')
            ->groupBy('barang_setengah_jadi_hp.id_ukuran')
            ->get();

        // Triplek Hasil grouped by id_ukuran
        $triplekActuals = TriplekHasilHp::where('id_produksi_hp', $produksiId)
            ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
            ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(triplek_hasil_hp.isi) as total_actual')
            ->groupBy('barang_setengah_jadi_hp.id_ukuran')
            ->get();

        // Combine actual production by size
        $combinedActuals = [];
        foreach ($platformActuals as $act) {
            $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
        }
        foreach ($triplekActuals as $act) {
            if (isset($combinedActuals[$act->id_ukuran])) {
                $combinedActuals[$act->id_ukuran] += (int) $act->total_actual;
            } else {
                $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
            }
        }

        $globalActualAchievement = 0;
        $globalTargetAchievement = 0;
        $hasTargetGlobal = false;

        $targetProgress = [];
        foreach ($combinedActuals as $id_ukuran => $actual) {
            // Find target matching id_ukuran under HOTPRESS machines
            $tgt = $targets->first(function ($t) use ($id_ukuran) {
                return $t->id_ukuran == $id_ukuran;
            });

            // FALLBACK TO WILDCARD (size 33 / '0x0x0') IF NOT FOUND
            if (!$tgt) {
                $tgt = $targets->first(function ($t) {
                    return $t->id_ukuran == 33;
                });
            }

            $ukuranModel = \App\Models\Ukuran::find($id_ukuran);
            $tebal = $ukuranModel ? (float) $ukuranModel->tebal : 0.0;

            $ukuranStr = $ukuranModel ? "{$ukuranModel->panjang} x {$ukuranModel->lebar} x " . floatval($ukuranModel->tebal) : 'Ukuran ?';

            $globalActualAchievement += $actual;

            if ($tgt) {
                $targetVal = (float) $tgt->target;
                $globalTargetAchievement += $targetVal;
                $hasTargetGlobal = true;

                $targetProgress[] = [
                    'hasTarget' => true,
                    'ukuran' => $ukuranStr,
                    'tebal' => $tebal,
                    'actual' => $actual,
                    'target' => $targetVal,
                    'orang' => $tgt->orang,
                    'jam' => $tgt->jam,
                ];
            } else {
                $targetProgress[] = [
                    'hasTarget' => false,
                    'ukuran' => $ukuranStr,
                    'tebal' => $tebal,
                    'actual' => $actual,
                    'target' => 0,
                    'orang' => '-',
                    'jam' => '-',
                ];
            }
        }

        $globalProgress = 0;
        foreach ($targetProgress as $item) {
            if ($item['hasTarget'] && $item['target'] > 0) {
                $globalProgress += ($item['actual'] / $item['target']) * 100;
            }
        }
        $globalProgress = round($globalProgress, 1);

        $this->summary = [
            'totalPegawai'  => $totalPegawai,
            'totalPlatform' => $totalPlatform,
            'listPlatform'  => $listPlatform,
            'totalTriplek'  => $totalTriplek,
            'listTriplek'   => $listTriplek,
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'targetProgress' => $targetProgress,
            'globalActualAchievement' => $globalActualAchievement,
            'globalTargetAchievement' => $globalTargetAchievement,
            'globalProgress' => $globalProgress,
            'hasTargetGlobal' => $hasTargetGlobal,
        ];
    }
}
