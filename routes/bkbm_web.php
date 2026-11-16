<?php

use App\Http\Controllers\BkbmCetak\BkCetakController;
use App\Http\Controllers\BkbmCetak\BkSuratJalanController;
use App\Http\Controllers\BkbmCetak\BkbmExportController;
use App\Http\Controllers\BkbmCetak\BmCetakController;
use Illuminate\Support\Facades\Route;

// Cetakan menu BK/BM baru. Dimuat HANYA dari routes/web.php (grup web sudah otomatis). Jangan dimuat dari file lain.
Route::middleware(['auth'])->prefix('bkbm')->name('bkbm.')->group(function () {
    Route::get('/surat-jalan/bk/{nota}/{jenis?}', [BkSuratJalanController::class, 'printBk'])->name('surat-jalan.bk');

    // Export Excel sederhana
    Route::get('/export/bk', [BkbmExportController::class, 'rekapBk'])->name('export.bk');
    Route::get('/export/bm', [BkbmExportController::class, 'rekapBm'])->name('export.bm');
    Route::get('/export/belum-kembali', [BkbmExportController::class, 'belumKembali'])->name('export.belum-kembali');

    Route::get('/bk/rekap', [BkCetakController::class, 'rekap'])->name('nota-bk.rekap');
    Route::get('/bk/rekap/export', [BkCetakController::class, 'exportExcel'])->name('nota-bk.export');
    Route::get('/bk/{record}/print', [BkCetakController::class, 'show'])->name('nota-bk.print');
    Route::get('/bk/{record}/barang-keluar', [BkCetakController::class, 'printBarangKeluar'])->name('nota-bk.barang-keluar');
    Route::get('/bk/{record}/preview/{jenis}', [BkCetakController::class, 'preview'])->name('nota-bk.preview');
    Route::post('/bk/{record}/update-items', [BkCetakController::class, 'updateItems'])->name('nota-bk.update-items');
    Route::post('/bk/{record}/reset-items', [BkCetakController::class, 'resetItems'])->name('nota-bk.reset-items');
    Route::post('/bk/{record}/save-payment', [BkCetakController::class, 'savePayment'])->name('nota-bk.save-payment');
    Route::get('/bk/{record}/nota-kantor', [BkCetakController::class, 'printNotaKantor'])->name('nota-bk.nota-kantor');
    Route::get('/bk/{record}/nota-sales', [BkCetakController::class, 'printNotaSales'])->name('nota-bk.nota-sales');
    Route::get('/bk/{record}/cetak-semua/{jenis}', [BkCetakController::class, 'cetakSemua'])->name('nota-bk.cetak-semua');

    Route::get('/bm/rekap', [BmCetakController::class, 'rekap'])->name('nota-bm.rekap');
    Route::get('/bm/rekap/export', [BmCetakController::class, 'exportExcel'])->name('nota-bm.export');
    Route::get('/bm/{record}/print', [BmCetakController::class, 'show'])->name('nota-bm.print');
});