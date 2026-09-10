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
        $activeSheet = request('sheet', $lahanPertama);

        if ($request->ajax()) {
            $laporan = $service->getLaporanBatchPreview($bulan, $tahun, $activeSheet);

            // --- FIX: samakan cara hitung harga_veneer/ongkos/vop dengan
            // halaman PersentaseKayu (yang sudah benar). Bug-nya: total_kubikasi
            // di outflow_detail berupa string number_format() ber-koma ribuan,
            // jadi kita parse ulang & hitung ulang total_keluar_m3 + harga_*
            // di sini, tanpa menyentuh ProduksiInflowService sama sekali.
            $laporan = $laporan->map(fn ($item) => $this->normalizeLaporanItem($item));

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

        return view('exports.preview-produksi', [
            'laporan' => collect(),
            'selectedBulan' => $bulan,
            'selectedTahun' => $tahun,
            'sheets' => $sheets,
            'activeSheet' => $activeSheet,
            'rekap' => null,
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

            // --- FIX: sama seperti index() di atas ---
            $laporan = $laporan->map(fn ($item) => $this->normalizeLaporanItem($item));

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

    /**
     * Hitung ulang total_keluar_m3, rendemen, harga_veneer, harga_v_ongkos,
     * harga_vop dari outflow_detail mentah — supaya angka di Preview & Export
     * konsisten dengan halaman PersentaseKayu (yang sudah benar).
     * Tidak mengubah ProduksiInflowService / logic inflow sama sekali.
     */
    protected function normalizeLaporanItem(array $item): array
    {
        $outflowM3 = collect($item['outflow'])
            ->sum(fn ($row) => (float) str_replace(',', '', (string) $row['total_kubikasi']));

        $ongkos = collect($item['outflow'])->sum('ongkos');
        $penyusutan = collect($item['outflow'])->sum('penyusutan');

        $poin = (float) str_replace(['.', ','], ['', '.'], $item['summary']['total_poin']);

        $item['summary']['total_keluar_m3'] = (float) number_format($outflowM3, 4, '.', '');

        $item['summary']['harga_veneer'] = $outflowM3 > 0
            ? (float) ($poin / $outflowM3)
            : 0.0;

        $item['summary']['harga_v_ongkos'] = $outflowM3 > 0
            ? (float) (($poin + $ongkos) / $outflowM3)
            : 0.0;

        $item['summary']['harga_vop'] = $outflowM3 > 0
            ? (float) (($poin + $ongkos + $penyusutan) / $outflowM3)
            : 0.0;

        $totalMasukM3 = (float) $item['summary']['total_masuk_m3'];
        $item['summary']['rendemen'] = $totalMasukM3 > 0
            ? number_format(($outflowM3 / $totalMasukM3) * 100, 2).'%'
            : '0%';

        return $item;
    }
}
