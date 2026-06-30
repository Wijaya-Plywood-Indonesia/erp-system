<?php

namespace App\Http\Controllers;

use App\Exports\LaporanKayu;
use App\Models\DetailNotaBarangKeluar;
use App\Models\NotaBarangKeluar;
use Excel;
use Illuminate\Support\Facades\DB;

class NotaBKController extends Controller
{
    public function show(NotaBarangKeluar $record)
    {
        // Muat relasi yang diperlukan


        return view('nota-barang.bk-print', [
            'record' => $record,
            'details' => $record->detail,
        ]);
    }

    // ✅ REKAP NOTA MASUK
    public function rekap()
    {
        $details = DetailNotaBarangKeluar::query()
            ->join('nota_barang_keluar as n', 'n.id', '=', 'detail_nota_barang_keluar.id_nota_bk')

            ->leftJoin('users as u1', 'u1.id', '=', 'n.dibuat_oleh')
            ->leftJoin('users as u2', 'u2.id', '=', 'n.divalidasi_oleh')

            ->whereNotNull('n.divalidasi_oleh')

            ->orderByDesc('n.tanggal')   // tanggal nota terbaru
            ->orderByDesc('n.id')
            ->orderByDesc('detail_nota_barang_keluar.id')

            ->select([
                'n.tanggal',
                'n.no_nota',
                'n.tujuan_nota',
                DB::raw('u1.name as dibuat_oleh'),
                DB::raw('u2.name as divalidasi_oleh'),
                'detail_nota_barang_keluar.nama_barang',
                'detail_nota_barang_keluar.jumlah',
                'detail_nota_barang_keluar.satuan',
                'detail_nota_barang_keluar.keterangan',
            ])
            ->get();

        return view('nota-barang.bk-rekap', [
            'details' => $details,
        ]);
    }

    public function exportExcel()
    {
        $query = DB::table('detail_nota_barang_keluar as d')
            ->join('nota_barang_keluar as n', 'n.id', '=', 'd.id_nota_bk')

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
            'rekap-nota-barang-keluar.xlsx'
        );
    }

}