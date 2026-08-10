<?php

namespace App\Http\Controllers;

use App\Services\MultiExportExcelPKayuService;
use App\Services\ProduksiInflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PreviewPersentaseKayu extends Controller
{
    protected $lahans = [];

    public function index(Request $request)
    {
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));

        $service = new ProduksiInflowService;
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);
        $this->lahans = $sheets;
        $lahanPertama = $sheets[0] ?? null;
        $activeSheet = request('sheet', $lahanPertama); // Default sheet

        // --- LAZY LOAD ---
        // Kalau ini request AJAX (dipanggil dari JS, baik saat first load
        // maupun saat ganti filter/sheet), kita HANYA proses & kirim data
        // laporan + rekap, tanpa perlu render ulang shell halaman
        // (filter form, tab sheet, dsb). Ini yang bikin first load & filter
        // ganti sama-sama "lazy" lewat jalur yang identik.
        if ($request->ajax()) {
            $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $activeSheet);
            $summaryLahan = $service->getSummaryLaporanLahan($laporan);

            return view('exports.preview-produksi', [
                'laporan' => $laporan,
                'selectedBulan' => $bulan,
                'selectedTahun' => $tahun,
                'sheets' => $sheets,
                'activeSheet' => $activeSheet,
                'rekap' => $summaryLahan,
            ])->fragment('table-content');
        }

        // --- FIRST LOAD (non-AJAX) ---
        // Render shell halaman saja. $laporan & $rekap dikosongkan/null
        // supaya query berat TIDAK dijalankan di request pertama ini.
        // Tabel akan diisi lewat fetch() begitu halaman selesai render (lihat script).
        return view('exports.preview-produksi', [
            'laporan' => collect(), // kosong dulu, diisi via AJAX
            'selectedBulan' => $bulan,
            'selectedTahun' => $tahun,
            'sheets' => $sheets,
            'activeSheet' => $activeSheet,
            'rekap' => null, // ditandai null supaya blade tahu ini belum diisi
        ]);
    }

    public function exportExcel(Request $request)
    {
        $allData = [];
        $bulan = request('bulan', date('m'));
        $tahun = request('tahun', date('Y'));
        $service = new ProduksiInflowService;
        $sheets = $service->getActiveLahanSheets($bulan, $tahun);

        if (empty($sheets)) {
            return;
        }

        $namaBulan = Carbon::createFromFormat('m', $bulan)->translatedFormat('F');
        $tanggal = now()->format('d-m-Y');

        foreach ($sheets as $value) {
            $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $value);
            $summaryLahan = $service->getSummaryLaporanLahan($laporan);
            $allData[$value] = [
                'laporan' => $laporan->toArray(),
                'rekap' => $summaryLahan,
                'date' => $namaBulan.' -- diexport pada tanggal --'.$tanggal,
            ];
        }

        $fileName = "Persentase_Kayu_{$namaBulan}_{$tanggal}.xlsx";

        return Excel::download(new MultiExportExcelPKayuService($allData), $fileName);
    }
}
