<?php

use App\Http\Controllers\KontrakController;
use App\Models\KontrakKerja;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotaKayuController;
use App\Http\Controllers\NotaBKController;
use App\Http\Controllers\NotaBMController;
use App\Http\Controllers\LaporanKayuMasukController;
use App\Http\Controllers\NotaKayuTurusController;



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

Route::get('/nota-barang-keluar/rekap', [NotaBKController::class, 'rekap'])
    ->name('nota-bk.rekap');

Route::get('/nota-barang-keluar/rekap/export', [NotaBKController::class, 'exportExcel'])
    ->name('nota-bk.export');

Route::get('/nota-kayu/{record}', [NotaKayuController::class, 'show'])
    ->name('nota-kayu.show');

Route::get('/nota-kayu/{record}/turus', [NotaKayuTurusController::class, 'show'])
    ->name('nota-kayu.turus');

Route::get('/', function () {
    return view('welcome');
});

