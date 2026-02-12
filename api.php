<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\DetailKayuMasuk;
use App\Models\DetailTurusanKayu;
use App\Models\DetailAbsensi;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Middleware ['web', 'auth'] untuk fitur Offline Kayu Masuk (Satu Sesi Browser)
Route::middleware(['web', 'auth'])->group(function () {

    // 1. Sinkron Detail Kayu Masuk
    Route::post('/offline/sync-detail-kayu-masuk', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'parent_id'             => 'required|exists:kayu_masuks,id',
            'items'                 => 'required|array',
            'items.*.id_lahan'      => 'required|exists:lahans,id',
            'items.*.id_jenis_kayu' => 'required|exists:jenis_kayus,id',
            'items.*.panjang'       => 'required|numeric',
            'items.*.grade'         => 'required',
            'items.*.diameter'      => 'required|numeric',
            'items.*.jumlah_batang' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                DetailKayuMasuk::create([
                    'id_kayu_masuk' => $request->parent_id,
                    'id_lahan'      => $item['id_lahan'],
                    'id_jenis_kayu' => $item['id_jenis_kayu'],
                    'panjang'       => $item['panjang'],
                    'grade'         => $item['grade'],
                    'diameter'      => $item['diameter'],
                    'jumlah_batang' => $item['jumlah_batang'],
                    'keterangan'    => 'Input via Mode Offline',
                ]);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Data kayu berhasil sinkron.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });

    // 2. Sinkron Detail Turusan Kayu
    Route::post('/offline/sync-detail-turusan-kayu', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'parent_id'             => 'required|exists:kayu_masuks,id',
            'items'                 => 'required|array',
            'items.*.lahan_id'      => 'required|exists:lahans,id',
            'items.*.jenis_kayu_id' => 'required|exists:jenis_kayus,id',
            'items.*.panjang'       => 'required|numeric',
            'items.*.grade'         => 'required',
            'items.*.diameter'      => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                $lastNo = DetailTurusanKayu::where('id_kayu_masuk', $request->parent_id)->max('nomer_urut') ?? 0;
                DetailTurusanKayu::create([
                    'id_kayu_masuk' => $request->parent_id,
                    'nomer_urut'    => $lastNo + 1,
                    'lahan_id'      => $item['lahan_id'],
                    'jenis_kayu_id' => $item['jenis_kayu_id'],
                    'panjang'       => $item['panjang'],
                    'grade'         => $item['grade'],
                    'diameter'      => $item['diameter'],
                    'kuantitas'     => 1,
                    'keterangan'    => 'Offline Input',
                ]);
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Data turusan berhasil sinkron.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });
});

/**
 * 3. API SINKRONISASI ABSENSI ANTAR WEBSITE (EXTERNAL)
 * Endpoint: /api/external/sync-absensi
 * Tanpa Middleware Auth (Menggunakan API KEY untuk Bypass CORS Server-to-Server)
 */
Route::post('/external/sync-absensi', function (Request $request) {

    // 1. Validasi API KEY sederhana
    if ($request->header('X-API-KEY') !== 'SINKRON_SECRET_KEY_123') {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // 2. Validasi Input
    $validator = Validator::make($request->all(), [
        'tanggal' => 'required|date',
        'absensi' => 'required|array',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    DB::beginTransaction();
    try {
        $dataAbsensi = $request->input('absensi');
        $tanggal = $request->input('tanggal');

        foreach ($dataAbsensi as $item) {
            DetailAbsensi::updateOrCreate(
                [
                    'kode_pegawai' => ltrim($item['kodep'], '0'),
                    'tanggal'      => $tanggal,
                ],
                [
                    // Pastikan mengambil key yang benar sesuai data dari Absen.php
                    'jam_masuk'    => $item['f_masuk'] ?? null,
                    'jam_pulang'   => $item['f_pulang'] ?? null,
                ]
            );
        }

        DB::commit();
        return response()->json(['status' => 'success', 'message' => 'Data tersimpan.']);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});
