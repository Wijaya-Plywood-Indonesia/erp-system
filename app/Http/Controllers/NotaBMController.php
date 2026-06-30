<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\DetailNotaBarangMasuk;
use App\Models\NotaBarangMasuk;
use Excel;
use Illuminate\Support\Facades\DB;

class NotaBMController extends Controller
{
    public function show(NotaBarangMasuk $record)
    {
        // Muat relasi yang diperlukan
        $record->load([
            'dibuatOleh',
            'divalidasiOleh',
            'detail',
        ]);

        return view('nota-barang.bm-print', [
            'record' => $record,
            'details' => $record->detail,
        ]);
    }

    // ✅ REKAP NOTA MASUK
    public function rekap()
    {
        $details = DetailNotaBarangMasuk::query()
            ->join('nota_barang_masuks as n', 'n.id', '=', 'detail_nota_barang_masuks.id_nota_bm')

            ->leftJoin('users as u1', 'u1.id', '=', 'n.dibuat_oleh')
            ->leftJoin('users as u2', 'u2.id', '=', 'n.divalidasi_oleh')

            ->whereNotNull('n.divalidasi_oleh')

            ->orderByDesc('n.tanggal')   // tanggal nota terbaru
            ->orderByDesc('n.id')
            ->orderByDesc('detail_nota_barang_masuks.id')

            ->select([
                'n.tanggal',
                'n.no_nota',
                'n.tujuan_nota',
                DB::raw('u1.name as dibuat_oleh'),
                DB::raw('u2.name as divalidasi_oleh'),
                'detail_nota_barang_masuks.nama_barang',
                'detail_nota_barang_masuks.jumlah',
                'detail_nota_barang_masuks.satuan',
                'detail_nota_barang_masuks.keterangan',
            ])
            ->get();

        return view('nota-barang.bm-rekap', [
            'details' => $details,
        ]);
    }

    public function exportExcel()
    {
        $query = DB::table('detail_nota_barang_masuks as d')
            ->join('nota_barang_masuks as n', 'n.id', '=', 'd.id_nota_bm')

            ->leftJoin('users as u1', 'u1.id', '=', 'n.dibuat_oleh')
            ->leftJoin('users as u2', 'u2.id', '=', 'n.divalidasi_oleh')

            ->whereNotNull('n.divalidasi_oleh')

            ->orderByDesc('n.tanggal')
            ->orderByDesc('n.id')
            ->orderByDesc('d.id')

            ->select([
                'n.tanggal',
                'n.no_nota',
                'n.tujuan_nota',
                DB::raw('u1.name as dibuat_oleh'),
                DB::raw('u2.name as divalidasi_oleh'),
                'd.nama_barang',
                'd.jumlah',
                'd.satuan',
                'd.keterangan',
            ]);

        $columns = [
            ['field' => 'tanggal', 'label' => 'Tanggal'],
            ['field' => 'no_nota', 'label' => 'No Nota'],
            ['field' => 'tujuan_nota', 'label' => 'Tujuan Nota'],
            ['field' => 'dibuat_oleh', 'label' => 'Dibuat Oleh'],
            ['field' => 'divalidasi_oleh', 'label' => 'Divalidasi Oleh'],
            ['field' => 'nama_barang', 'label' => 'Nama Barang'],
            ['field' => 'jumlah', 'label' => 'Jumlah'],
            ['field' => 'satuan', 'label' => 'Satuan'],
            ['field' => 'keterangan', 'label' => 'Keterangan'],
        ];

        return Excel::download(
            new LaporanKayu($query, $columns),
            'rekap-nota-barang-masuk.xlsx'
        );
    }

}