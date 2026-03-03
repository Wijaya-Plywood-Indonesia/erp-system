<?php

namespace App\Http\Controllers;

use App\Services\ProduksiInflowService;

class PreviewPersentaseKayu extends Controller
{
    public function index()
    {
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));

        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);
        $laporan = $service->getLaporanBatchPreview($bulan, $tahun);
        $rekap = $service->getLaporanBatchRekap($bulan, $tahun);

        $lahanPertama = $sheets[0] ?? null;

        $activeSheet = request('sheet', $lahanPertama); // Default sheet

        return view('exports.preview-produksi', [
            'laporan' => $laporan,
            'selectedBulan' => $bulan,
            'selectedTahun' => $tahun,
            'sheets' => $sheets,
            'activeSheet' => $activeSheet,
            'rekap' => $rekap
        ]);
    }
}

