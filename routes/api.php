<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\DetailKayuMasuk;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Route ini menangani sinkronisasi data Detail Kayu Masuk dari mode offline.
| URL Endpoint: /api/offline/sync-detail-kayu-masuk
|
*/

// Middleware ['web', 'auth'] digunakan agar API bisa membaca sesi login user dari browser.
// Ini penting agar 'created_by' & 'updated_by' di Model otomatis terisi nama user yang login.
Route::middleware(['web', 'auth'])->group(function () {

    Route::post('/offline/sync-detail-kayu-masuk', function (Request $request) {

        // =========================================================
        // 1. VALIDASI INPUT (KETAT)
        // =========================================================
        // Kita validasi array 'items' dan pastikan ID referensi (lahan & jenis kayu) valid.

        $validator = Validator::make($request->all(), [
            'parent_id'             => 'required|exists:kayu_masuks,id', // ID Parent (Kayu Masuk) Wajib Ada
            'items'                 => 'required|array',                 // Data harus berupa List/Array
            'items.*.id_lahan'      => 'required|exists:lahans,id',      // Cek apakah ID Lahan ada di DB
            'items.*.id_jenis_kayu' => 'required|exists:jenis_kayus,id', // Cek apakah ID Jenis Kayu ada di DB
            'items.*.panjang'       => 'required|numeric',
            'items.*.grade'         => 'required',
            'items.*.diameter'      => 'required|numeric',
            'items.*.jumlah_batang' => 'required|numeric|min:1',
        ]);

        // Jika validasi gagal, kembalikan detail error ke Frontend (AlpineJS)
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal. Mohon cek data inputan.',
                'errors' => $validator->errors()
            ], 422);
        }

        // =========================================================
        // 2. PROSES PENYIMPANAN (DATABASE TRANSACTION)
        // =========================================================
        // Kita gunakan Transaction agar data aman. Jika 1 item gagal, semua dibatalkan.

        DB::beginTransaction();

        try {
            $parentId = $request->parent_id;
            $items = $request->items;
            $count = 0;

            foreach ($items as $item) {
                // Simpan data ke tabel detail_kayu_masuks
                DetailKayuMasuk::create([
                    'id_kayu_masuk' => $parentId,
                    'id_lahan'      => $item['id_lahan'],
                    'id_jenis_kayu' => $item['id_jenis_kayu'],
                    'panjang'       => $item['panjang'],
                    'grade'         => $item['grade'],
                    'diameter'      => $item['diameter'],
                    'jumlah_batang' => $item['jumlah_batang'],

                    // Field 'keterangan' sebagai penanda bahwa data ini diinput saat offline
                    'keterangan'    => 'Input via Mode Offline',

                    // Catatan: 
                    // Field 'created_by' dan 'updated_by' akan otomatis diisi 
                    // oleh boot method di Model Anda karena route ini menggunakan middleware 'auth'
                ]);
                $count++;
            }

            // Jika semua loop berhasil tanpa error, simpan permanen ke database
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Berhasil menyinkronkan {$count} data kayu.",
            ], 200);
        } catch (\Exception $e) {
            // Jika ada error (misal koneksi DB putus di tengah jalan), batalkan semua
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server saat menyimpan data.',
                'debug_error' => $e->getMessage() // Hapus baris ini saat production
            ], 500);
        }
    });

    Route::post('/offline/sync-detail-turusan-kayu', function (Illuminate\Http\Request $request) {

        $validator = Illuminate\Support\Facades\Validator::make($request->all(), [
            'parent_id'             => 'required|exists:kayu_masuks,id', // Parentnya tetap Kayu Masuk kan? Sesuaikan jika beda
            'items'                 => 'required|array',
            'items.*.lahan_id'      => 'required|exists:lahans,id',
            'items.*.jenis_kayu_id' => 'required|exists:jenis_kayus,id',
            'items.*.panjang'       => 'required|numeric',
            'items.*.grade'         => 'required',
            'items.*.diameter'      => 'required|numeric', // Sesuai validasi form Anda
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $parentId = $request->parent_id;
            $items = $request->items;
            $count = 0;

            foreach ($items as $item) {
                // LOGIKA NOMOR URUT OTOMATIS (Server Side)
                // Kita ambil max nomor urut terakhir untuk parent & lahan ini
                $lastNo = \App\Models\DetailTurusanKayu::where('id_kayu_masuk', $parentId)
                    ->max('nomer_urut') ?? 0;

                \App\Models\DetailTurusanKayu::create([
                    'id_kayu_masuk' => $parentId,
                    'nomer_urut'    => $lastNo + 1, // Auto Increment
                    'lahan_id'      => $item['lahan_id'],
                    'jenis_kayu_id' => $item['jenis_kayu_id'],
                    'panjang'       => $item['panjang'],
                    'grade'         => $item['grade'],
                    'diameter'      => $item['diameter'],
                    'kuantitas'     => 1, // Default 1 batang per inputan turus
                    'keterangan'    => 'Offline Input',
                ]);
                $count++;
            }

            Illuminate\Support\Facades\DB::commit();
            return response()->json(['status' => 'success', 'message' => "Berhasil simpan {$count} data turusan."]);
        } catch (\Exception $e) {
            Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });
});
