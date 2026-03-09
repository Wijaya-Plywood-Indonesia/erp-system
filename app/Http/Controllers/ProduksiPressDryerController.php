<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ProduksiDryerApiService;

class ProduksiPressDryerController extends Controller
{
    //
    protected ProduksiDryerApiService $apiService;

    public function __construct(ProduksiDryerApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    // Panggil method ini setelah data produksi disimpan
    public function kirimKeWebLain(int $id)
    {
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
    }
}
