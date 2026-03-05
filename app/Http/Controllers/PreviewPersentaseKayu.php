<?php

namespace App\Http\Controllers;

use App\Services\ExportExcelPersentaseKayuService;
use App\Services\MultiExportExcelPKayuService;
use App\Services\ProduksiInflowService;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
class PreviewPersentaseKayu extends Controller
{
    protected $lahans = [];
    public function index()
    {
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));

        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);
        $this->lahans = $sheets;
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
        $allData = [];
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));
        $service = new ProduksiInflowService();
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);

        $namaBulan = Carbon::createFromFormat('m', $bulan)->translatedFormat('F');
        $tanggal = now()->format('d-m-Y');


        foreach ($sheets as $value) {
            $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $value);
            $summaryLahan = $service->getSummaryLaporanLahan($laporan);
            $allData[$value] = [
                'laporan' => $laporan->toArray(),
                'rekap' => $summaryLahan,
                'date' => $namaBulan . ' -- diexport pada tanggal --' . $tanggal 
            ];
        }


        $fileName = "Persentase_Kayu_{$namaBulan}_{$tanggal}.xlsx";

        return Excel::download(new MultiExportExcelPKayuService($allData), $fileName);
    }
}