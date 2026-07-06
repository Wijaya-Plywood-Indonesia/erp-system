<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKayuMasukController extends Controller
{
    private const STATUS_LUNAS_PREFIX = 'Lunas%';

    /**
     * Ekspresi SQL untuk parse tanggal lunas dari string status_pelunasan.
     * Format string: "Lunas - 30/06/2026 15:48 (nia)"
     */
    private const SQL_TGL_LUNAS = "STR_TO_DATE(SUBSTRING_INDEX(SUBSTRING_INDEX(status_pelunasan, ' - ', -1), ' (', 1), '%d/%m/%Y %H:%i')";

    /**
     * Ambil semua NotaKayu berstatus lunas, difilter & diurutkan
     * berdasarkan TANGGAL LUNAS (diparse dari string status_pelunasan).
     */
    private function ambilNota(Request $request): Collection
    {
        return NotaKayu::query()
            ->where('status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX)
            ->when($request->dari, function ($q, $dari) {
                return $q->whereRaw('DATE('.self::SQL_TGL_LUNAS.') >= ?', [$dari]);
            })
            ->when($request->sampai, function ($q, $sampai) {
                return $q->whereRaw('DATE('.self::SQL_TGL_LUNAS.') <= ?', [$sampai]);
            })
            ->with([
                'kayuMasuk.detailTurusanKayus.jenisKayu',
                'kayuMasuk.detailTurusanKayus.lahan',
                'kayuMasuk.penggunaanSupplier', // TODO: sesuaikan nama relasi supplier kalau beda
            ])
            ->orderByRaw(self::SQL_TGL_LUNAS.' ASC')
            ->get();
    }

    /**
     * Ambil tanggal lunas (format Y-m-d) dari string status_pelunasan,
     * contoh: "Lunas - 30/06/2026 15:48 (nia)" -> "2026-06-30".
     */
    private function tglLunas(?string $statusPelunasan): ?string
    {
        if (! $statusPelunasan) {
            return null;
        }

        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $statusPelunasan, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}"; // Y-m-d
        }

        return null;
    }

    /**
     * COPY PERSIS dari NotaKayuController::groupByRentangDiameter().
     * Poin dihitung per rentang diameter master (harga snapshot dari baris
     * PERTAMA di rentang), supaya totalnya identik dengan grand total nota.
     */
    private function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        $rentangList = HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->orderBy('diameter_terkecil')
            ->get();

        $hasil = collect();
        $terpakaiIds = collect();

        foreach ($rentangList as $rentang) {
            $kelompok = $details->filter(function ($item) use ($rentang) {
                return $item->diameter >= $rentang->diameter_terkecil
                    && $item->diameter <= $rentang->diameter_terbesar;
            });

            if ($kelompok->isNotEmpty()) {
                $totalBatang = $kelompok->sum('kuantitas');
                $totalKubikasi = $kelompok->sum(fn ($item) => round($item->kubikasi, 4));

                $harga = $kelompok->first()->harga ?? 0;

                $hasil->push([
                    'batang' => $totalBatang,
                    'kubikasi' => round($totalKubikasi, 4),
                    'total_harga' => round($harga * $totalKubikasi * 1000),
                ]);

                $terpakaiIds = $terpakaiIds->merge($kelompok->pluck('id'));
            }
        }

        // Item sisa di luar rentang master (manual)
        $sisa = $details->whereNotIn('id', $terpakaiIds);
        foreach ($sisa as $item) {
            $hasil->push([
                'batang' => $item->kuantitas,
                'kubikasi' => round($item->kubikasi, 4),
                'total_harga' => round(($item->harga ?? 0) * round($item->kubikasi, 4) * 1000),
            ]);
        }

        return $hasil;
    }

    /**
     * Bangun baris laporan: SATU BARIS per (nota + lahan + jenis + panjang).
     * Poin dihitung per grade + rentang diameter dulu (biar sama dengan nota),
     * baru dijumlahkan jadi satu baris per lahan.
     */
    private function buildLaporanData(Request $request): Collection
    {
        $notas = $this->ambilNota($request);
        $hasil = collect();

        foreach ($notas as $nota) {
            $kayuMasuk = $nota->kayuMasuk;
            $details = $kayuMasuk->detailTurusanKayus ?? collect();

            if ($details->isEmpty()) {
                continue;
            }

            $tanggalLunas = $this->tglLunas($nota->status_pelunasan);

            // Grup PENYAJIAN: per lahan + jenis + panjang (tanpa grade)
            $grupLahan = $details->groupBy(function ($item) {
                return implode('|', [
                    $item->lahan_id,
                    $item->jenis_kayu_id,
                    $item->panjang,
                ]);
            });

            foreach ($grupLahan as $itemsLahan) {
                $first = $itemsLahan->first();

                $totalBatang = 0;
                $totalM3 = 0;
                $totalPoin = 0;

                // Grup PERHITUNGAN: tetap per grade, karena master harga per grade
                foreach ($itemsLahan->groupBy('grade') as $grade => $itemsGrade) {
                    $rentangRows = $this->groupByRentangDiameter(
                        $itemsGrade,
                        $first->jenis_kayu_id,
                        $grade,
                        $first->panjang
                    );

                    $totalBatang += $rentangRows->sum('batang');
                    $totalM3 += $rentangRows->sum('kubikasi');
                    $totalPoin += $rentangRows->sum('total_harga');
                }

                $hasil->push((object) [
                    'tanggal' => $tanggalLunas,
                    'nama' => trim($kayuMasuk->penggunaanSupplier->nama_supplier ?? '-'),
                    'seri' => $kayuMasuk->seri,
                    'panjang' => $first->panjang,
                    'jenis' => $first->jenisKayu?->nama_kayu,
                    'lahan' => $first->lahan?->kode_lahan,
                    'banyak' => $totalBatang,
                    'm3' => round($totalM3, 4),
                    'poin' => $totalPoin,
                ]);
            }
        }

        return $hasil->values();
    }

    public function index(Request $request)
    {
        $data = $this->buildLaporanData($request);

        return view('nota-kayu.laporan-kayu', compact('data'));
    }

    public function export(Request $request)
    {
        $columns = [
            ['label' => 'Tanggal', 'field' => 'tanggal'],
            ['label' => 'Nama Supplier', 'field' => 'nama'],
            ['label' => 'Seri', 'field' => 'seri'],
            ['label' => 'Panjang', 'field' => 'panjang'],
            ['label' => 'Jenis', 'field' => 'jenis'],
            ['label' => 'Lahan', 'field' => 'lahan'],
            ['label' => 'Batang', 'field' => 'banyak'],
            ['label' => 'M3', 'field' => 'm3'],
            ['label' => 'Poin', 'field' => 'poin'],
        ];

        if ($request->filled('dari') && $request->filled('sampai')) {
            $labelTanggal = $request->dari.'_sd_'.$request->sampai;
        } elseif ($request->filled('dari')) {
            $labelTanggal = 'dari_'.$request->dari;
        } elseif ($request->filled('sampai')) {
            $labelTanggal = 'sampai_'.$request->sampai;
        } else {
            $labelTanggal = now()->format('Y-m-d');
        }

        $fileName = 'laporan_kayu_'.$labelTanggal.'.xlsx';
        $data = $this->buildLaporanData($request);

        return Excel::download(new LaporanKayu($data, $columns), $fileName);
    }
}
