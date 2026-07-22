<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class LaporanKayuMasukController extends Controller
{
    private const STATUS_LUNAS_PREFIX = 'Lunas%';

    /**
     * Ekspresi SQL untuk parse tanggal lunas dari string status_pelunasan.
     * Format string: "Lunas - 30/06/2026 15:48 (nia)"
     */
    private const SQL_TGL_LUNAS = "STR_TO_DATE(SUBSTRING_INDEX(SUBSTRING_INDEX(status_pelunasan, ' - ', -1), ' (', 1), '%d/%m/%Y %H:%i')";

    /**
     * Menyimpan seluruh data master harga kayu agar tidak query berulang di dalam looping.
     */
    private Collection $masterHarga;

    public function __construct()
    {
        // OPTIMASI: Ambil data harga kayu HANYA 1 KALI saat controller dipanggil
        $this->masterHarga = HargaKayu::all();
    }

    /**
     * Ambil semua NotaKayu berstatus lunas, difilter & diurutkan
     * berdasarkan TANGGAL LUNAS (diparse dari string status_pelunasan).
     */
    private function ambilNota(Request $request): Collection
    {
        // OPTIMASI: Set default tanggal ke bulan berjalan jika filter kosong
        $dari = $request->dari ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $sampai = $request->sampai ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        return NotaKayu::query()
            ->where('status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX)
            ->whereRaw('DATE('.self::SQL_TGL_LUNAS.') >= ?', [$dari])
            ->whereRaw('DATE('.self::SQL_TGL_LUNAS.') <= ?', [$sampai])
            ->with([
                'kayuMasuk.detailTurusanKayus.jenisKayu',
                'kayuMasuk.detailTurusanKayus.lahan',
                'kayuMasuk.penggunaanSupplier', 
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
     * Menghitung poin dengan metode grouping rentang diameter.
     */
    private function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        // OPTIMASI: Menggunakan collection di memori ($this->masterHarga)
        $rentangList = $this->masterHarga
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->sortBy('diameter_terkecil')
            ->values();

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
     */
    private function buildLaporanData(Request $request): Collection
    {
        $notas = $this->ambilNota($request);
        $hasil = collect();

        foreach ($notas as $nota) {
            $kayuMasuk = $nota->kayuMasuk;
            
            if (!$kayuMasuk) {
                continue;
            }

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
        // 1. Ambil seluruh data hasil perhitungan (Masih berupa Collection mentah)
        $allData = $this->buildLaporanData($request);

        // 2. Konfigurasi Pagination (misal: 50 data per halaman)
        $perPage = 50; 
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage('page') ?: 1;

        // 3. Potong collection dan ubah menjadi Paginator agar fungsi ->links() bisa bekerja
        $data = new \Illuminate\Pagination\LengthAwarePaginator(
            $allData->forPage($page, $perPage),
            $allData->count(),
            $perPage,
            $page,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'query' => $request->query() 
            ]
        );

        // 4. Kirim data yang sudah di-paginate ke view
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
        
        // Export menggunakan semua data (bukan yang dipotong pagination)
        $data = $this->buildLaporanData($request);

        return Excel::download(new LaporanKayu($data, $columns), $fileName);
    }
}