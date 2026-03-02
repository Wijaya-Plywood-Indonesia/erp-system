<?php 

namespace App\Http\Controllers;

use App\Services\ProduksiInflowService;

class PreviewPersentaseKayu extends Controller
{
    public function index()
    {
        $service = new ProduksiInflowService();
        
        $laporan = $service->getLaporanBatch(); 

        return view('exports.preview-produksi', [
            'laporan' => $laporan
        ]);
    }
}