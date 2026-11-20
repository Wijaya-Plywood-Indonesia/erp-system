<?php

use App\Filament\Pages\LeaderBoardSupplier;
use App\Http\Controllers\KontrakController;
use App\Http\Controllers\LaporanKayuMasukController;
use App\Http\Controllers\NotaBKController;
use App\Http\Controllers\NotaBMController;
// use App\Models\ProduksiRotary;
use App\Http\Controllers\NotaKayuController;
use App\Http\Controllers\NotaKayuTurusController;
use App\Http\Controllers\OfflineDetailKayuController;
use App\Http\Controllers\OfflineDetailTurusanController;
use App\Http\Controllers\PenjualanCetakController;
use App\Http\Controllers\PreviewPersentaseKayu;
use App\Http\Controllers\ReferensiHargaProduksiController;
use App\Http\Controllers\SuratJalanController;
use App\Models\KontrakKerja;
use App\Models\NotaKayu;
use App\Models\SupplierKayu;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/offline/detail-turusan-kayu/sync', [OfflineDetailTurusanController::class, 'sync'])
    ->name('offline.detail-turusan.sync');

Route::match(['get', 'post'], '/offline/detail-kayu-masuk/sync', [OfflineDetailKayuController::class, 'sync'])
    ->name('offline.detail-kayu.sync');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    // Route spesifik untuk preview excel
    Route::get('/persentase-kayu/preview-excel', [PreviewPersentaseKayu::class, 'index'])
        ->name('filament.admin.pages.persentase-kayu.preview');

    Route::get('/persentase-kayu/export-excel', [PreviewPersentaseKayu::class, 'exportExcel'])->name('produksi.export-excel');

    Route::resource('referensi-harga-produksi', ReferensiHargaProduksiController::class);
});

Route::middleware(['auth'])->group(function () {
    // Surat Jalan - Nota Barang Keluar
    Route::get('/surat-jalan/bk/{nota}/{jenis?}', [SuratJalanController::class, 'printBk'])
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

// Barang Masuk
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

Route::get('/nota-barang-keluar/{record}/preview/{jenis}', [NotaBKController::class, 'preview'])
    ->name('nota-bk.preview');
Route::post('/nota-barang-keluar/{record}/update-items', [NotaBKController::class, 'updateItems'])
    ->name('nota-bk.update-items');
Route::post('/nota-barang-keluar/{record}/reset-items', [NotaBKController::class, 'resetItems'])
    ->name('nota-bk.reset-items');

Route::post('/nota-barang-keluar/{record}/save-payment', [NotaBKController::class, 'savePayment'])
    ->name('nota-bk.save-payment');

Route::get('/nota-barang-keluar/{record}/nota-kantor', [NotaBKController::class, 'printNotaKantor'])
    ->name('nota-bk.nota-kantor');

Route::get('/nota-barang-keluar/{record}/nota-sales', [NotaBKController::class, 'printNotaSales'])
    ->name('nota-bk.nota-sales');

Route::get('/nota-barang-keluar/{record}/cetak-semua/{jenis}', [NotaBKController::class, 'cetakSemua'])
    ->name('nota-bk.cetak-semua');

// Rute Cetak & Penanganan Menu Penjualan (Terpisah dari Nota Barang Keluar)
Route::middleware(['auth'])->group(function () {
    Route::get('/penjualan/{record}/preview/{jenis}', [PenjualanCetakController::class, 'preview'])
        ->name('penjualan.preview');
    Route::post('/penjualan/{record}/update-items', [PenjualanCetakController::class, 'updateItems'])
        ->name('penjualan.update-items');
    Route::post('/penjualan/{record}/reset-items', [PenjualanCetakController::class, 'resetItems'])
        ->name('penjualan.reset-items');
    Route::post('/penjualan/{record}/save-payment', [PenjualanCetakController::class, 'savePayment'])
        ->name('penjualan.save-payment');
    Route::get('/penjualan/{record}/nota-kantor', [PenjualanCetakController::class, 'printNotaKantor'])
        ->name('penjualan.nota-kantor');
    Route::get('/penjualan/{record}/nota-sales', [PenjualanCetakController::class, 'printNotaSales'])
        ->name('penjualan.nota-sales');
    Route::get('/penjualan/{record}/invoice-resmi', [PenjualanCetakController::class, 'printInvoiceResmi'])
        ->name('penjualan.invoice-resmi');
    Route::get('/penjualan/{record}/surat-jalan/{jenis?}', [PenjualanCetakController::class, 'printSuratJalan'])
        ->name('penjualan.surat-jalan');
    Route::get('/penjualan/{record}/cetak-semua/{jenis}', [PenjualanCetakController::class, 'cetakSemua'])
        ->name('penjualan.cetak-semua');
});

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
    if (! $supplier) {
        return response()->json(['error' => 'Not found'], 404);
    }

    $notas = NotaKayu::query()
        ->with(['kayuMasuk.detailTurusanKayus'])
        ->whereHas('kayuMasuk', fn ($q) => $q->where('id_supplier_kayus', $id))
        ->whereRaw("LOWER(TRIM(status_pelunasan)) LIKE 'lunas%'")
        ->get();

    $page = new LeaderBoardSupplier;

    $invoices = $notas->map(function ($nota) use ($page) {
        $calc = $page->calculateNotaTotals($nota);

        return [
            'id' => $nota->id,
            'nomor_nota' => $nota->no_nota,
            'tanggal' => $nota->kayuMasuk?->tgl_kayu_masuk,
            'grand_total' => $calc['totalAkhir'],
            'kubikasi' => $calc['totalKubikasi'],
            'status' => 'lunas',
        ];
    })->values();

    return response()->json([
        'name' => $supplier->nama_supplier ?? $supplier->nama ?? 'Supplier #'.$id,
        'total_pembelian' => $invoices->sum('grand_total'),
        'total_kubikasi' => round($invoices->sum('kubikasi'), 4),
        'nota_dicetak' => $invoices->count(),
        'invoices' => $invoices,
    ]);
})->middleware(['web', 'auth'])->name('internal.supplier-detail');

require __DIR__.'/bkbm_web.php';
