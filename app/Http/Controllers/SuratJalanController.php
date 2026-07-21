<?php

namespace App\Http\Controllers;

use App\Models\NotaBarangKeluar;

class SuratJalanController extends Controller
{
    /**
     * Cetak Surat Jalan untuk Nota Barang Keluar.
     */
    public function printBk(NotaBarangKeluar $nota)
    {
        $nota->load(['detail', 'pembuat']);

        return view('surat-jalan.cetak', [
            'nota'    => $nota,
            'details' => $nota->detail,
        ]);
    }
}