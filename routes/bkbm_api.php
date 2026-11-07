<?php

use App\Http\Controllers\Api\BkbmPesanController;
use Illuminate\Support\Facades\Route;

// Pesan BK/BM antar pabrik. Dimuat dari routes/api.php (prefix /api otomatis).
Route::post('/bkbm/pesan', [BkbmPesanController::class, 'terima'])->middleware('throttle:120,1');