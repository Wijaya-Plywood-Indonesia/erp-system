<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKayuMasukController extends Controller
{
    /**
     * Prefix status "sudah lunas" di kolom nota_kayus.status_pelunasan.
     * Format aslinya gabungan: "Lunas - 30/05/2026 14:52 (via)" — jadi
     * dicocokkan pakai LIKE prefix, bukan pencocokan persis (=).
     * Yang BELUM lunas kemungkinan diawali teks lain (mis. "Belum Lunas"
     * atau kosong) sehingga otomatis tidak lolos filter ini.
     */
    private const STATUS_LUNAS_PREFIX = 'Lunas%';

    private function baseQuery(Request $request)
    {
        $m3Formula = '
            CAST(
                detail_turusan_kayus.panjang
              * detail_turusan_kayus.diameter
              * detail_turusan_kayus.diameter
              * detail_turusan_kayus.kuantitas
              * 0.785 / 1000000
            AS DECIMAL(18,8))
        ';

        // PENTING: Harga TIDAK diambil dari master harga_kayus / harga_kayu_logs.
        // Sumber kebenaran harga adalah SNAPSHOT yang sudah dikunci per baris
        // di kolom detail_turusan_kayus.harga, persis seperti yang dipakai
        // NotaKayuController saat mencetak nota. Ini memastikan angka di
        // laporan SELALU sama dengan angka yang tertera di nota cetak,
        // berapa pun kali harga master berubah setelah transaksi terjadi.
        $query = DB::table('detail_turusan_kayus')
            ->join('kayu_masuks', 'kayu_masuks.id', '=', 'detail_turusan_kayus.id_kayu_masuk')
            ->join('supplier_kayus', 'supplier_kayus.id', '=', 'kayu_masuks.id_supplier_kayus')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_turusan_kayus.jenis_kayu_id')
            ->leftJoin('lahans', 'lahans.id', '=', 'detail_turusan_kayus.lahan_id');

        // FILTER TANGGAL: Menggunakan when agar fleksibel jika salah satu kosong
        $query->when($request->dari, function ($q, $dari) {
            return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '>=', $dari);
        })
            ->when($request->sampai, function ($q, $sampai) {
                return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '<=', $sampai);
            });

        // FILTER PELUNASAN: kayu masuk yang notanya BELUM lunas tidak ikut
        // dilaporkan. Pakai whereExists (bukan join biasa) supaya tidak
        // menggandakan baris kalau suatu saat satu kayu_masuk punya lebih
        // dari satu nota_kayus.
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('nota_kayus')
                ->whereColumn('nota_kayus.id_kayu_masuk', 'kayu_masuks.id')
                ->where('nota_kayus.status_pelunasan', 'LIKE', self::STATUS_LUNAS_PREFIX);
        });

        // Harga = snapshot kolom detail_turusan_kayus.harga (sama seperti NotaKayuController).
        // Kalau kolomnya NULL/belum diisi, dianggap 0 — konsisten dengan aturan bisnis
        // "kalau harganya 0 di database, di nota juga muncul 0".
        $hargaEfektifSql = 'COALESCE(detail_turusan_kayus.harga, 0)';

        return $query->select([
            DB::raw('DATE(kayu_masuks.tgl_kayu_masuk) AS tanggal'),
            'supplier_kayus.nama_supplier AS nama',
            'kayu_masuks.seri',
            'detail_turusan_kayus.panjang',
            'jenis_kayus.nama_kayu AS jenis',
            'lahans.kode_lahan AS lahan',
            DB::raw('SUM(detail_turusan_kayus.kuantitas) AS banyak'),
            DB::raw("ROUND(SUM(ROUND($m3Formula, 4)), 4) AS m3"),
            // PENTING: pembulatan per baris (ke bilangan bulat) dilakukan DULU,
            // baru dijumlah — persis seperti NotaKayuController:
            //   round($harga * $kubikasi * 1000)  →  dijumlahkan
            // Kalau dibalik (jumlah dulu baru dibulatkan), hasilnya bisa
            // selisih ±1 rupiah dibanding nota karena titik pembulatan beda.
            DB::raw("SUM(ROUND(ROUND($m3Formula, 4) * CAST( $hargaEfektifSql AS DECIMAL(12,2) ) * 1000, 0)) AS poin"),
        ])
            ->groupBy([
                DB::raw('DATE(kayu_masuks.tgl_kayu_masuk)'),
                'supplier_kayus.nama_supplier',
                'kayu_masuks.seri',
                'detail_turusan_kayus.panjang',
                'jenis_kayus.nama_kayu',
                'lahans.kode_lahan',
            ])
            ->orderByDesc('kayu_masuks.tgl_kayu_masuk')
            ->orderBy('kayu_masuks.seri')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('lahans.kode_lahan');
    }

    public function index(Request $request)
    {
        $data = $this->baseQuery($request)->get();

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

        // Logika Pengondisian Nama File
        if ($request->filled('dari') && $request->filled('sampai')) {
            // Jika filter tanggal diisi keduanya
            $labelTanggal = $request->dari.'_sd_'.$request->sampai;
        } elseif ($request->filled('dari')) {
            // Jika hanya tanggal 'dari' yang diisi
            $labelTanggal = 'dari_'.$request->dari;
        } elseif ($request->filled('sampai')) {
            // Jika hanya tanggal 'sampai' yang diisi
            $labelTanggal = 'sampai_'.$request->sampai;
        } else {
            // Jika tidak ada filter sama sekali (Default: Tanggal Hari Ini)
            $labelTanggal = now()->format('Y-m-d');
        }

        $fileName = 'laporan_kayu_'.$labelTanggal.'.xlsx';

        return Excel::download(
            new LaporanKayu($this->baseQuery($request), $columns),
            $fileName
        );
    }
}
