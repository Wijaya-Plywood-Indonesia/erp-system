<?php

use App\Http\Controllers\Api\BkbmPesanController;
use Illuminate\Support\Facades\Route;

// Pesan BK/BM antar pabrik. Dimuat dari routes/api.php (prefix /api otomatis).
Route::post('/bkbm/pesan', [BkbmPesanController::class, 'terima'])->middleware('throttle:120,1');

// Rute cetakan BK/BM (halaman web, butuh login). Dimuat dari sini supaya routes/web.php tidak perlu diubah.
// Karena file ini dimuat lewat api.php, alamatnya berawalan /api (contoh: /api/bkbm/bk/1/print).
require __DIR__ . '/bkbm_web.php';