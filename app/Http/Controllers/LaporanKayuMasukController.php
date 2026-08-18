<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\HargaKayu;
use App\Models\NotaKayu;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKayuMasukController extends Controller
{
    private const STATUS_LUNAS_PREFIX = 'Lunas%';

    /**
     * Menyimpan seluruh data master harga kayu agar tidak query berulang di dalam looping.
     */
    private Collection $masterHarga;

    /**
     * Cache hasil grouping master harga per (jenis|grade|panjang), dibangun sekali
     * saat pertama dibutuhkan lewat masterHargaGrouped().
     */
    private ?Collection $masterHargaGroupedCache = null;

    public function __construct()
    {
        // OPTIMASI: Ambil data harga kayu HANYA 1 KALI saat controller dipanggil
        $this->masterHarga = HargaKayu::all();
    }

    /**
     * Ambil semua NotaKayu berstatus lunas, difilter & diurutkan
     * berdasarkan kolom `tanggal_lunas` (generated column, lihat migration
     * add_tanggal_lunas_generated_column_to_nota_kayu_table).
     */
    private function ambilNota(Request $request): Collection
    {
        $dari = $request->dari ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $sampai = $request->sampai ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        return NotaKayu::query()
            ->where('status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX)
            ->whereBetween('tanggal_lunas', [$dari, $sampai])
            ->with([
                'kayuMasuk.detailTurusanKayus.jenisKayu',
                'kayuMasuk.detailTurusanKayus.lahan',
                'kayuMasuk.penggunaanSupplier',
            ])
            ->orderBy('tanggal_lunas')
            ->get();
    }

    /**
     * Grouping master harga per (id_jenis_kayu|grade|panjang), dihitung sekali
     * dan di-cache di $masterHargaGroupedCache — menghindari filter berulang
     * (where->where->where->sortBy) untuk setiap kombinasi yang sama.
     */
    private function masterHargaGrouped(): Collection
    {
        return $this->masterHargaGroupedCache ??= $this->masterHarga
            ->groupBy(fn ($h) => "{$h->id_jenis_kayu}|{$h->grade}|{$h->panjang}")
            ->map(fn ($group) => $group->sortBy('diameter_terkecil')->values());
    }

    /**
     * Menghitung poin dengan metode grouping rentang diameter.
     */
    private function groupByRentangDiameter($details, $idJenisKayu, $grade, $panjang)
    {
        $rentangList = $this->masterHargaGrouped()->get("{$idJenisKayu}|{$grade}|{$panjang}", collect());

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
     * Transformasi Collection NotaKayu menjadi baris-baris laporan.
     * Dipisah dari fetch data agar bisa dipakai untuk data ter-paginate (index)
     * maupun data penuh (export) tanpa duplikasi logika.
     */
    private function transformNotasToRows(Collection $notas): Collection
    {
        $hasil = collect();

        foreach ($notas as $nota) {
            $kayuMasuk = $nota->kayuMasuk;
            if (! $kayuMasuk) {
                continue;
            }

            $details = $kayuMasuk->detailTurusanKayus ?? collect();
            if ($details->isEmpty()) {
                continue;
            }

            $tanggalLunas = $nota->tanggal_lunas;

            $grupLahan = $details->groupBy(fn ($item) => implode('|', [
                $item->lahan_id,
                $item->jenis_kayu_id,
                $item->panjang,
            ]));

            foreach ($grupLahan as $itemsLahan) {
                $first = $itemsLahan->first();
                $totalBatang = 0;
                $totalM3 = 0;
                $totalPoin = 0;

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
                    'tgl_kayu_masuk' => $kayuMasuk->tgl_kayu_masuk
                        ? Carbon::parse($kayuMasuk->tgl_kayu_masuk)->format('d/m/Y')
                        : '-',
                    'tanggal' => $tanggalLunas
                        ? Carbon::parse($tanggalLunas)->format('d/m/Y')
                        : '-',
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

    /**
     * Ambil SELURUH data laporan untuk rentang tanggal (dipakai export, bukan index).
     */
    private function buildLaporanData(Request $request): Collection
    {
        $notas = $this->ambilNota($request);

        return $this->transformNotasToRows($notas);
    }

    public function index(Request $request)
    {
        $dari = $request->dari ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $sampai = $request->sampai ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        // Paginate di level SQL — hanya 50 nota yang benar-benar diambil & diproses
        $notas = NotaKayu::query()
            ->where('status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX)
            ->whereBetween('tanggal_lunas', [$dari, $sampai])
            ->with([
                'kayuMasuk.detailTurusanKayus.jenisKayu',
                'kayuMasuk.detailTurusanKayus.lahan',
                'kayuMasuk.penggunaanSupplier',
            ])
            ->orderBy('tanggal_lunas')
            ->paginate(50)
            ->withQueryString();

        // Agregasi HANYA untuk 50 nota di halaman ini, bukan seluruh rentang tanggal.
        // Catatan: karena 1 nota bisa menghasilkan >1 atau 0 baris laporan (grouping
        // lahan/jenis/panjang), jumlah baris yang tampil per halaman mendekati-50,
        // bukan pasti 50, dan total()/links() mengacu ke jumlah NOTA bukan baris laporan.
        $data = $this->transformNotasToRows($notas->getCollection());

        return view('nota-kayu.laporan-kayu', [
            'data' => $notas->setCollection($data),
        ]);
    }

    public function export(Request $request)
    {
        $columns = [
            ['label' => 'Tgl Kayu Masuk', 'field' => 'tgl_kayu_masuk'],
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
