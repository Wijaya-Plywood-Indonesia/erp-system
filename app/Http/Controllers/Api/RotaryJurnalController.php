<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Akuntansi\RotaryJurnalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * RotaryJurnalController
 *
 * Endpoint untuk generate & preview payload jurnal rotary.
 *
 * ROUTES (tambahkan di routes/api.php):
 * ─────────────────────────────────────────────────────────────────────────
 *   // Preview payload jurnal (untuk testing webhook)
 *   Route::get('/jurnal/rotary/preview', [RotaryJurnalController::class, 'preview']);
 *   Route::post('/jurnal/rotary/preview', [RotaryJurnalController::class, 'preview']);
 *
 *   // Trigger dari validasi (dipanggil otomatis saat mesin divalidasi)
 *   Route::post('/jurnal/rotary/trigger', [RotaryJurnalController::class, 'trigger']);
 *
 *   // Check status validasi semua mesin di tanggal tertentu
 *   Route::get('/jurnal/rotary/status/{tanggal}', [RotaryJurnalController::class, 'statusValidasi']);
 * ─────────────────────────────────────────────────────────────────────────
 */
class RotaryJurnalController extends Controller
{
    public function __construct(private readonly RotaryJurnalService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    //  GET/POST /api/jurnal/rotary/preview?tanggal=2025-08-01
    //  Body: { "tanggal": "2025-08-01" }
    //
    //  → Return payload jurnal lengkap untuk testing webhook
    // ─────────────────────────────────────────────────────────────────────
    public function preview(Request $request): JsonResponse
    {
        $tanggal = $request->input('tanggal') ?? $request->query('tanggal');

        if (!$tanggal) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tanggal wajib diisi. Format: Y-m-d (contoh: 2025-08-01)',
            ], 422);
        }

        $validator = Validator::make(['tanggal' => $tanggal], [
            'tanggal' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid. Gunakan format: Y-m-d',
                'errors'  => $validator->errors(),
            ], 422);
        }

        Log::info("RotaryJurnalAPI: Preview jurnal tanggal {$tanggal}");

        $payload = $this->service->buildJurnalPayload($tanggal);

        if ($payload === null) {
            // Ambil status detail untuk info lebih lengkap
            $statusDetail = $this->getStatusValidasiDetail($tanggal);

            return response()->json([
                'success'        => false,
                'message'        => 'Jurnal belum dapat dibuat. Masih ada mesin yang belum divalidasi.',
                'tanggal'        => $tanggal,
                'status_mesin'   => $statusDetail,
            ], 200);
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Payload jurnal berhasil digenerate.',
            'tanggal'   => $tanggal,
            'payload'   => $payload,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  POST /api/jurnal/rotary/trigger
    //  Body: { "id_produksi": 123 }
    //
    //  → Dipanggil otomatis setelah validasi disimpan.
    //    Sistem akan cek apakah semua mesin di tanggal ybs sudah valid,
    //    lalu return payload jurnal siap kirim ke web akuntansi.
    // ─────────────────────────────────────────────────────────────────────
    public function trigger(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_produksi' => 'required|integer|exists:produksi_rotaries,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi input gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $idProduksi = $request->input('id_produksi');

        // Ambil tanggal dari produksi yang ditrigger
        $produksi = \App\Models\ProduksiRotary::findOrFail($idProduksi);
        $tanggal  = $produksi->tgl_produksi instanceof \Carbon\Carbon
            ? $produksi->tgl_produksi->format('Y-m-d')
            : $produksi->tgl_produksi;

        Log::info("RotaryJurnalAPI: Trigger dari id_produksi={$idProduksi}, tanggal={$tanggal}");

        $payload = $this->service->buildJurnalPayload($tanggal);

        if ($payload === null) {
            $statusDetail = $this->getStatusValidasiDetail($tanggal);

            return response()->json([
                'success'       => false,
                'message'       => 'Belum semua mesin divalidasi. Jurnal ditunda.',
                'tanggal'       => $tanggal,
                'id_produksi'   => $idProduksi,
                'status_mesin'  => $statusDetail,
            ], 200);
        }

        Log::info("RotaryJurnalAPI: Jurnal berhasil digenerate untuk tanggal {$tanggal}");

        return response()->json([
            'success'       => true,
            'message'       => 'Semua mesin sudah divalidasi. Payload jurnal siap dikirim ke akuntansi.',
            'tanggal'       => $tanggal,
            'id_produksi'   => $idProduksi,
            'payload'       => $payload,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GET /api/jurnal/rotary/status/{tanggal}
    //
    //  → Cek status validasi semua mesin di tanggal tertentu
    // ─────────────────────────────────────────────────────────────────────
    public function statusValidasi(string $tanggal): JsonResponse
    {
        $statusDetail = $this->getStatusValidasiDetail($tanggal);

        $allValidated = collect($statusDetail)->every(fn($m) => $m['sudah_divalidasi']);

        return response()->json([
            'success'         => true,
            'tanggal'         => $tanggal,
            'all_validated'   => $allValidated,
            'total_mesin'     => count($statusDetail),
            'sudah_validasi'  => collect($statusDetail)->where('sudah_divalidasi', true)->count(),
            'belum_validasi'  => collect($statusDetail)->where('sudah_divalidasi', false)->count(),
            'detail_mesin'    => $statusDetail,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helper: ambil detail status validasi per mesin
    // ─────────────────────────────────────────────────────────────────────
    private function getStatusValidasiDetail(string $tanggal): array
    {
        $produksiList = \App\Models\ProduksiRotary::with([
            'mesin:id,nama_mesin,jenis_hasil',
            'detailValidasiHasilRotary',
        ])
            ->whereDate('tgl_produksi', $tanggal)
            ->get();

        return $produksiList->map(function ($produksi) {
            $validasiList  = $produksi->detailValidasiHasilRotary;
            $sudahValidasi = $validasiList->whereIn('status', ['divalidasi', 'disetujui'])->count() > 0;

            return [
                'id_produksi'      => $produksi->id,
                'nama_mesin'       => $produksi->mesin->nama_mesin ?? '-',
                'jenis_hasil'      => $produksi->mesin->jenis_hasil ?? '-',
                'sudah_divalidasi' => $sudahValidasi,
                'validasi_records' => $validasiList->map(fn($v) => [
                    'role'   => $v->role,
                    'status' => $v->status,
                ])->toArray(),
            ];
        })->toArray();
    }
}