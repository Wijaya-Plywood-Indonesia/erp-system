<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKayuMasukController extends Controller
{
    private function baseQuery(Request $request)
    {
        $m3Formula = "
            CAST(
                detail_turusan_kayus.panjang
              * detail_turusan_kayus.diameter
              * detail_turusan_kayus.diameter
              * detail_turusan_kayus.kuantitas
              * 0.785 / 1000000
            AS DECIMAL(18,8))
        ";

        $query = DB::table('detail_turusan_kayus')
            ->join('kayu_masuks', 'kayu_masuks.id', '=', 'detail_turusan_kayus.id_kayu_masuk')
            ->join('supplier_kayus', 'supplier_kayus.id', '=', 'kayu_masuks.id_supplier_kayus')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_turusan_kayus.jenis_kayu_id')
            ->leftJoin('lahans', 'lahans.id', '=', 'detail_turusan_kayus.lahan_id')
            ->leftJoin('harga_kayus AS hk', function ($join) {
                $join->on('hk.id', '=', DB::raw("(SELECT hx.id FROM harga_kayus hx WHERE hx.id_jenis_kayu = detail_turusan_kayus.jenis_kayu_id AND hx.grade = detail_turusan_kayus.grade AND hx.panjang = detail_turusan_kayus.panjang AND detail_turusan_kayus.diameter BETWEEN hx.diameter_terkecil AND hx.diameter_terbesar ORDER BY hx.diameter_terkecil DESC LIMIT 1)"));
            });

        // FILTER TANGGAL: Menggunakan when agar fleksibel jika salah satu kosong
        $query->when($request->dari, function ($q, $dari) {
            return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '>=', $dari);
        })
            ->when($request->sampai, function ($q, $sampai) {
                return $q->whereDate('kayu_masuks.tgl_kayu_masuk', '<=', $sampai);
            });

        return $query->select([
            DB::raw('DATE(kayu_masuks.tgl_kayu_masuk) AS tanggal'),
            'supplier_kayus.nama_supplier AS nama',
            'kayu_masuks.seri',
            'detail_turusan_kayus.panjang',
            'jenis_kayus.nama_kayu AS jenis',
            'lahans.kode_lahan AS lahan',
            DB::raw('SUM(detail_turusan_kayus.kuantitas) AS banyak'),
            DB::raw("ROUND(SUM(ROUND($m3Formula, 4)), 4) AS m3"),
            DB::raw("ROUND(SUM(ROUND($m3Formula, 4) * CAST( COALESCE(hk.harga_beli,0) AS DECIMAL(12,2) ) * 1000), 2) AS poin"),
        ])
            ->groupBy([
                DB::raw('DATE(kayu_masuks.tgl_kayu_masuk)'),
                'supplier_kayus.nama_supplier',
                'kayu_masuks.seri',
                'detail_turusan_kayus.panjang',
                'jenis_kayus.nama_kayu',
                'lahans.kode_lahan',
            ])
            ->orderByDesc('kayu_masuks.seri');
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

        $fileName = 'laporan_kayu_' . ($request->dari ?? 'all') . '_sd_' . ($request->sampai ?? 'now') . '.xlsx';

        return Excel::download(
            new LaporanKayu($this->baseQuery($request), $columns),
            $fileName
        );
    }
}
