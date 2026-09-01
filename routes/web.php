<?php

use App\Exports\ExportExcelPersentaseKayuService;
use App\Filament\Pages\LeaderBoardSupplier;
use App\Http\Controllers\PreviewPersentaseKayu;
use App\Services\ProduksiInflowService;
use App\Services\ProduksiOutflowService;
use App\Http\Controllers\SuratJalanController;
use App\Http\Controllers\KontrakController;
use App\Models\KontrakKerja;
// use App\Models\ProduksiRotary;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotaKayuController;
use App\Http\Controllers\NotaBKController;
use App\Http\Controllers\NotaBMController;
use App\Http\Controllers\LaporanKayuMasukController;
use App\Http\Controllers\NotaKayuTurusController;
use App\Models\NotaKayu;
use App\Models\SupplierKayu;

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    // Route spesifik untuk preview excel
    Route::get('/persentase-kayu/preview-excel', [PreviewPersentaseKayu::class, 'index'])
        ->name('filament.admin.pages.persentase-kayu.preview');

    Route::get('/persentase-kayu/export-excel', [PreviewPersentaseKayu::class, 'exportExcel'])->name('produksi.export-excel');

    Route::resource('referensi-harga-produksi', \App\Http\Controllers\ReferensiHargaProduksiController::class);
});

Route::middleware(['auth'])->group(function () {
    // Surat Jalan - Nota Barang Keluar
    Route::get('/surat-jalan/bk/{nota}', [SuratJalanController::class, 'printBk'])
        ->name('surat-jalan.bk');
});


Route::get('/kontrak/bulk-print', [KontrakController::class, 'bulkPrint'])
    ->name('kontrak.bulk.print');

Route::get('/kontrak/{record}/print', function (KontrakKerja $record) {
    return view('contracts.pkwt', compact('record'));
})->name('kontrak.print');


Route::get('/laporan-kayu-masuk', [LaporanKayuMasukController::class, 'index'])
    ->name('laporan.kayu-masuk');

Route::get('/laporan-kayu-masuk/export', [LaporanKayuMasukController::class, 'export'])
    ->name('laporan.kayu-masuk.export');

//Barang Masuk
Route::get('/nota-barang-masuk/{record}/print', [NotaBMController::class, 'show'])
    ->name('nota-bm.print');

Route::get('/nota-barang-masuk/rekap', [NotaBMController::class, 'rekap'])
    ->name('nota-bm.rekap');

Route::get('/nota-barang-masuk/rekap/export', [NotaBMController::class, 'exportExcel'])
    ->name('nota-bm.export');

Route::get('/nota-barang-keluar/{record}/print', [NotaBKController::class, 'show'])
    ->name('nota-bk.print');

Route::get('/nota-barang-keluar/{record}/barang-keluar', [NotaBKController::class, 'printBarangKeluar'])
    ->name('nota-bk.barang-keluar');

Route::get('/nota-barang-keluar/rekap', [NotaBKController::class, 'rekap'])
    ->name('nota-bk.rekap');

Route::get('/nota-barang-keluar/rekap/export', [NotaBKController::class, 'exportExcel'])
    ->name('nota-bk.export');

Route::get('/nota-kayu/{record}', [NotaKayuController::class, 'show'])
    ->name('nota-kayu.show');

Route::get('/nota-kayu/{record}/turus', [NotaKayuTurusController::class, 'show'])
    ->name('nota-kayu.turus');

Route::get('/nota-kayu/{record}/turus2', [NotaKayuTurusController::class, 'show2'])
    ->name('nota-kayu.turus2');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/internal/supplier-detail/{id}', function ($id) {
    $supplier = SupplierKayu::find($id);
    if (!$supplier) {
        return response()->json(['error' => 'Not found'], 404);
    }

    $notas = NotaKayu::query()
        ->with(['kayuMasuk.detailTurusanKayus'])
        ->whereHas('kayuMasuk', fn($q) => $q->where('id_supplier_kayus', $id))
        ->whereRaw("LOWER(TRIM(status_pelunasan)) LIKE 'lunas%'")
        ->get();

    $page = new LeaderBoardSupplier();

    $invoices = $notas->map(function ($nota) use ($page) {
        $calc = $page->calculateNotaTotals($nota);
        return [
            'id'          => $nota->id,
            'nomor_nota'  => $nota->no_nota,
            'tanggal'     => $nota->kayuMasuk?->tgl_kayu_masuk,
            'grand_total' => $calc['totalAkhir'],
            'kubikasi'    => $calc['totalKubikasi'],
            'status'      => 'lunas',
        ];
    })->values();

    return response()->json([
        'name'            => $supplier->nama_supplier ?? $supplier->nama ?? 'Supplier #' . $id,
        'total_pembelian' => $invoices->sum('grand_total'),
        'total_kubikasi'  => round($invoices->sum('kubikasi'), 4),
        'nota_dicetak'    => $invoices->count(),
        'invoices'        => $invoices,
    ]);
})->middleware(['web', 'auth'])->name('internal.supplier-detail');
