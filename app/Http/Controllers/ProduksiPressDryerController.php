<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProduksiDryerApiService;

class ProduksiPressDryerController extends Controller
{
    protected ProduksiDryerApiService $apiService;

    public function __construct(ProduksiDryerApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    // Preview data — test di Postman tanpa kirim ke webhook
    public function previewPayload(int $id)
    {
        $payload = $this->apiService->getPayload($id);

        return response()->json($payload);
    }

    // Kirim data ke webhook / web tujuan
    public function kirimKeWebLain(int $id)
    {
        // DINONAKTIFKAN: jurnal ke akuntansi hanya lewat menu "Kirim Jurnal Akuntansi".
        return response()->json([
            'message' => 'Pengiriman langsung dinonaktifkan. Gunakan menu Kirim Jurnal Akuntansi.',
        ], 410);

        /*
        $result = $this->apiService->kirimData($id);

        if ($result['success']) {
            return response()->json([
                'message' => 'Data berhasil dikirim!',
                'detail' => $result,
            ]);
        }

        return response()->json([
            'message' => 'Gagal mengirim data.',
            'detail' => $result,
        ], 500);
        */
    }
}