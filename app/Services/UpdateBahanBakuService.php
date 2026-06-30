<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use App\Models\RencanaKerjaHp;

// class UpdateBahanBakuService
// {
//     /**
//      * Mengambil hasil produksi dan MEREVISI kolom 'isi' pada Bahan Baku.
//      * @param Model $hasilModel (Bisa TriplekHasilHp atau PlatformHasilHp)
//      */
//     public function handle(Model $hasilModel)
//     {
//         // 1. Ambil Nilai Isi (Jumlah Lembar Hasil)
//         if (!isset($hasilModel->isi)) {
//             return; 
//         }

//         $jumlahHasil = $hasilModel->isi;

//         // 2. Cari Rencana Kerja terkait
//         $rencanaKerja = $hasilModel->productionHp?->rencanaKerjaHp;
        
//         if (!$rencanaKerja) {
//             return; 
//         }

//         // 3. Ambil semua Bahan Baku (Veneer/Platform) dari Rencana Kerja terkait
//         $bahanBakuRecords = $rencanaKerja->laminasiBahanHp; 

//         // 4. Update setiap Bahan Baku
//         foreach ($bahanBakuRecords as $bahan) {
            
//             // --- REVISI UTAMA DI SINI ---
//             // Mengisi kolom 'isi' dengan jumlah hasil (realisasi konsumsi)
//             $bahan->isi = $jumlahHasil; 
            
//             $bahan->save(); 
//         }
//     }
// }