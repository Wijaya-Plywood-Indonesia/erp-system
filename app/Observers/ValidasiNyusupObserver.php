<?php

namespace App\Observers;

use App\Models\ValidasiNyusup;
use Illuminate\Support\Facades\Log;

/**
 * Validasi Nyusup TIDAK lagi memotong stok Gudang Satu.
 *
 * Stok Gudang Satu hanya berkurang saat barang diserahkan ke Gudang
 * Plywood Siap Jual (aksi "Serah" di DetailBarangDikerjakansTable),
 * supaya tidak terjadi double cut.
 */
class ValidasiNyusupObserver
{
    public function saved(ValidasiNyusup $validasi): void
    {
        Log::info("ValidasiNyusupObserver: status={$validasi->status} id_produksi={$validasi->id_produksi_nyusup} (tanpa pemotongan stok)");
    }
}
