<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKayuMasukController extends Controller
{
    /**
     * Prefix status "sudah lunas" di kolom nota_kayus.status_pelunasan.
     * Format aslinya gabungan: "Lunas - 30/05/2026 14:52 (via)" — jadi
     * dicocokkan pakai LIKE prefix, bukan pencocokan persis (=).
     */
    private const STATUS_LUNAS_PREFIX = 'Lunas%';

    /**
     * Ambil baris MENTAH per detail_turusan_kayus (tidak diagregasi di SQL).
     * Semua penjumlahan & pembulatan sengaja dilakukan di PHP (lihat
     * buildLaporanData()) supaya identik dengan cara NotaKayuController
     * menghitung, dan tidak ada lagi selisih akibat perbedaan presisi
     * antara MySQL (DECIMAL) dan PHP (float/round()).
     */
    private function rawQuery(Request $request)
    {
        $query = DB::table('detail_turusan_kayus')
            ->join('kayu_masuks', 'kayu_masuks.id', '=', 'detail_turusan_kayus.id_kayu_masuk')
            ->join('supplier_kayus', 'supplier_kayus.id', '=', 'kayu_masuks.id_supplier_kayus')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_turusan_kayus.jenis_kayu_id')
            ->leftJoin('lahans', 'lahans.id', '=', 'detail_turusan_kayus.lahan_id');

        // FILTER TANGGAL
        $query->when($request->dari, function ($q, $dari) {
            return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '>=', $dari);
        })
            ->when($request->sampai, function ($q, $sampai) {
                return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '<=', $sampai);
            });

        // FILTER PELUNASAN: hanya kayu masuk yang notanya sudah lunas
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('nota_kayus')
                ->whereColumn('nota_kayus.id_kayu_masuk', 'kayu_masuks.id')
                ->where('nota_kayus.status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX);
        });

        return $query->select([
            DB::raw('DATE(kayu_masuks.tgl_kayu_masuk) AS tanggal'),
            'supplier_kayus.nama_supplier AS nama',
            'kayu_masuks.seri',
            'detail_turusan_kayus.panjang',
            'jenis_kayus.nama_kayu AS jenis',
            'lahans.kode_lahan AS lahan',
            'detail_turusan_kayus.diameter',
            'detail_turusan_kayus.kuantitas',
            'detail_turusan_kayus.harga',
        ])
            ->orderByDesc('kayu_masuks.tgl_kayu_masuk')
            ->orderBy('kayu_masuks.seri')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('lahans.kode_lahan');
    }

    /**
     * Hitung kubikasi per baris, PERSIS meniru formula & titik pembulatan
     * yang dipakai NotaKayuController / accessor kubikasi:
     *   panjang * diameter * diameter * kuantitas * 0.785 / 1.000.000
     * lalu dibulatkan 4 desimal.
     *
     * CATATAN: kalau accessor kubikasi di model DetailTurusanKayu ternyata
     * pakai rumus lain, cukup ganti isi method ini saja.
     */
    private function hitungKubikasi($panjang, $diameter, $kuantitas): float
    {
        $raw = $panjang * $diameter * $diameter * $kuantitas * 0.785 / 1000000;

        return round($raw, 4);
    }

    /**
     * Ambil data mentah lalu agregasi & bulatkan di PHP — persis meniru
     * urutan operasi NotaKayuController:
     *   $kubikasi = round($item->kubikasi, 4);
     *   $grandTotal += round($harga * $kubikasi * 1000);
     * yaitu: bulatkan poin PER BARIS dulu (ke bilangan bulat), baru dijumlah.
     */
    private function buildLaporanData(Request $request): Collection
    {
        $rows = $this->rawQuery($request)->get();

        $grouped = $rows->groupBy(function ($row) {
            // Kunci grup: tanggal + nama + seri + panjang + jenis + lahan
            return implode('|', [
                $row->tanggal,
                $row->nama,
                $row->seri,
                $row->panjang,
                $row->jenis,
                $row->lahan,
            ]);
        });

        $hasil = $grouped->map(function ($items) {
            $first = $items->first();

            $banyak = 0;
            $totalM3 = 0.0;
            $totalPoin = 0;

            foreach ($items as $item) {
                $harga = $item->harga ?? 0;
                $kubikasi = $this->hitungKubikasi($item->panjang, $item->diameter, $item->kuantitas);

                $banyak += $item->kuantitas;
                $totalM3 += $kubikasi;
                // Bulatkan poin PER BARIS dulu, baru dijumlah — sama seperti nota.
                $totalPoin += round($harga * $kubikasi * 1000);
            }

            return (object) [
                'tanggal' => $first->tanggal,
                'nama' => $first->nama,
                'seri' => $first->seri,
                'panjang' => $first->panjang,
                'jenis' => $first->jenis,
                'lahan' => $first->lahan,
                'banyak' => $banyak,
                'm3' => round($totalM3, 4),
                'poin' => (int) round($totalPoin),
            ];
        })->values();

        return $hasil;
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
            ['label' => 'Nama', 'field' => 'nama'],
            ['label' => 'Seri', 'field' => 'seri'],
            ['label' => 'Panjang', 'field' => 'panjang'],
            ['label' => 'Jenis', 'field' => 'jenis'],
            ['label' => 'Lahan', 'field' => 'lahan'],
            ['label' => 'Banyak', 'field' => 'banyak'],
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

        // PENTING: baseQuery() lama mengembalikan Query Builder (untuk
        // FromQuery). Sekarang data sudah berupa Collection hasil agregasi
        // PHP. Kalau class LaporanKayu mengimplementasikan FromQuery,
        // constructor ini perlu diubah di sisi class Export-nya untuk
        // menerima Collection (mis. implements FromCollection) — lihat
        // catatan di bawah.
        $data = $this->buildLaporanData($request);

        return Excel::download(
            new LaporanKayu($data, $columns),
            $fileName
        );
    }
}
