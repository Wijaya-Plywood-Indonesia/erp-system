<?php

namespace App\Observers;

use App\Services\VeneerBasahInventoryService;
use Illuminate\Support\Facades\Log;

class ProductionValidationObserver
{
    protected $inventoryService;

    public function __construct(VeneerBasahInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function created($validasi)
    {
        $this->handleValidation($validasi, 'CREATED');
    }

    public function updated($validasi)
    {
        $this->handleValidation($validasi, 'UPDATED');
    }

    protected function handleValidation($validasi, $eventType)
    {
        Log::info("Observer Validasi Terpanggil [$eventType]: ID Validasi {$validasi->id}, Status: {$validasi->status}");

        if ($validasi->status === 'divalidasi') {

            // ─────────────────────────────────────────────────────────────
            // REVISI Gudang Veneer Basah (lihat GudangVeneerBasahService):
            // Stok veneer basah SEKARANG dikurangi lebih awal, yaitu saat
            // Dryer/Kedi menekan tombol "Terima" pada serah terima veneer
            // basah dari Gudang — BUKAN lagi di sini (saat validasi produksi).
            // Pengurangan di titik ini DIHAPUS agar stok tidak terpotong dua kali.
            // ─────────────────────────────────────────────────────────────

            if (isset($validasi->id_produksi_dryer)) {
                Log::info("Validasi Dryer #{$validasi->id_produksi_dryer}: stok veneer basah tidak dipotong di sini (sudah dipotong saat 'Terima' dari Gudang).");
            }

            if (isset($validasi->id_produksi_kedi)) {
                Log::info("Validasi Kedi #{$validasi->id_produksi_kedi}: stok veneer basah tidak dipotong di sini (sudah dipotong saat 'Terima' dari Gudang).");
            }

            // Logika untuk Produksi Stik
            /*
            if (isset($validasi->id_produksi_stik)) {
                $produksi = $validasi->produksi;
                $details = $produksi->detailMasukStik;

                Log::info("Memproses Stok Stik. Produksi ID: {$validasi->id_produksi_stik}, Tanggal: {$produksi->tanggal_produksi}");

                if ($details && $details->count() > 0) {
                    $this->inventoryService->kurangiStokDariProduksi($details, 'Stik', $produksi->tanggal_produksi);
                } else {
                    Log::warning("Gagal potong stok: Detail Masuk Stik tidak ditemukan.");
                }
            }
            */
        }
    }
}