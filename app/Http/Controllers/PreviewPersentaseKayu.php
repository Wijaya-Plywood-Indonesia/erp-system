<?php

namespace App\Http\Controllers;

use App\Services\ExportExcelPersentaseKayuService;
use App\Services\ProduksiInflowService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
class PreviewPersentaseKayu extends Controller
{
    public function index()
    {
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));

        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);
        $lahanPertama = $sheets[0] ?? null;
        $activeSheet = request('sheet', $lahanPertama); // Default sheet

        $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $activeSheet);
        
        $summaryLahan = $service->getSummaryLaporanLahan($laporan);


        return view('exports.preview-produksi', [
            'laporan' => $laporan,
            'selectedBulan' => $bulan,
            'selectedTahun' => $tahun,
            'sheets' => $sheets,
            'activeSheet' => $activeSheet,
            'rekap' => $summaryLahan
        ]);
    }

    public function exportExcel(Request $request)
    {
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));

        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);
        $lahanPertama = $sheets[0] ?? null;
        $activeSheet = request('sheet', $lahanPertama); // Default sheet

        $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $activeSheet);
        
        $summaryLahan = $service->getSummaryLaporanLahan($laporan);

        $fileName = 'Laporan_Persentase_Kayu_' . now()->format('Y-m-d_His') . '.xlsx';

        // 3. Return unduhan
        return Excel::download(
            new ExportExcelPersentaseKayuService($laporan->toArray(), $summaryLahan, $activeSheet), 
            $fileName
        );
    }
}