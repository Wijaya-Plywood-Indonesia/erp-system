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

            // Logika untuk Produksi Press Dryer
            if (isset($validasi->id_produksi_dryer)) {
                $produksi = $validasi->produksi;
                $details = $produksi->detailMasuks;

                Log::info("Memproses Stok Dryer. Produksi ID: {$validasi->id_produksi_dryer}, Tanggal: {$produksi->tanggal_produksi}");

                if ($details && $details->count() > 0) {
                    $this->inventoryService->kurangiStokDariProduksi($details, 'Press Dryer', $produksi->tanggal_produksi, $produksi->shift);
                } else {
                    Log::warning("Gagal potong stok: Detail Masuk Dryer tidak ditemukan.");
                }
            }

            // Logika untuk Produksi Kedi
            if (isset($validasi->id_produksi_kedi)) {
                $produksi = $validasi->produksi;
                $details = $produksi->detailMasukKedi;

                Log::info("Memproses Stok Kedi. Produksi ID: {$validasi->id_produksi_kedi}, Tanggal Masuk: {$produksi->tanggal}, Tanggal Bongkar: {$produksi->tanggal_actual_bongkar}");

                if ($details && $details->count() > 0) {
                    $this->inventoryService->kurangiStokDariBongkarKedi(
                        $details,
                        $produksi->tanggal,
                        $produksi->tanggal_actual_bongkar
                    );
                } else {
                    Log::warning("Gagal potong stok: Detail Masuk Kedi tidak ditemukan untuk Produksi ID {$validasi->id_produksi_kedi}.");
                }
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