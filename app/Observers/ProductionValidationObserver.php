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

        if ($validasi->status !== 'divalidasi') {
            return;
        }

        // ─────────────────────────────────────────────────────────────
        // REVISI (dibalik dari sebelumnya): stok veneer basah SEKARANG
        // dipotong DI SINI, saat produksi (Press Dryer / Kedi) divalidasi
        // — BUKAN lagi saat "Terima" dari Gudang Veneer Basah.
        //
        // Guard anti-potong-ganda: pada event 'updated', kalau field
        // 'status' TIDAK ikut berubah di penyimpanan ini (record memang
        // sudah 'divalidasi' sebelumnya dan cuma disimpan ulang karena
        // field lain), maka JANGAN potong stok lagi.
        // ─────────────────────────────────────────────────────────────
        if ($eventType === 'UPDATED' && ! $validasi->wasChanged('status')) {
            Log::info("Validasi #{$validasi->id}: status tidak berubah pada UPDATE ini, stok tidak dipotong ulang.");

            return;
        }

        // ── Press Dryer ──────────────────────────────────────────────
        if (isset($validasi->id_produksi_dryer)) {
            $produksi = $validasi->produksi;
            $details = $produksi?->detailMasuks;

            Log::info("Memotong Stok Veneer Basah - Press Dryer. Produksi ID: {$validasi->id_produksi_dryer}");

            if ($details && $details->count() > 0) {
                $this->inventoryService->kurangiStokDariProduksi(
                    $details,
                    'Press Dryer',
                    $produksi->tanggal_produksi,
                    $produksi->shift ?? null
                );
            } else {
                Log::warning("Gagal potong stok: Detail Masuk Press Dryer tidak ditemukan (Produksi ID: {$validasi->id_produksi_dryer}).");
            }
        }

        // ── Kedi ──────────────────────────────────────────────────────
        // Validasi Kedi HANYA BISA dibuat setelah produksi sudah di tahap
        // 'bongkar' (tab Validasi baru muncul kalau status produksi bukan
        // 'masuk' lagi — lihat YesRelationManager::canViewForRecord()),
        // dan tipe validasinya SELALU 'bongkar' (lihat ValidasiKediForm,
        // field 'tipe' di-hardcode 'bongkar'). Jadi cuma ada SATU kali
        // validasi per produksi Kedi, dan potong stok di titik itu.
        if (isset($validasi->id_produksi_kedi)) {
            $produksi = $validasi->produksi;
            $details = $produksi?->detailBongkarKedi;

            Log::info("Memotong Stok Veneer Basah - Kedi. Produksi ID: {$validasi->id_produksi_kedi}");

            if ($details && $details->count() > 0) {
                // DetailBongkarKedi pakai field 'jumlah' (bukan 'isi'),
                // jadi pakai method yang field-nya cocok.
                $this->inventoryService->kurangiStokDariBongkarKedi(
                    $details,
                    $produksi->tanggal,
                    $produksi->tanggal_actual_bongkar ?? $produksi->tanggal_bongkar ?? $produksi->tanggal
                );
            } else {
                Log::warning("Gagal potong stok: Detail Bongkar Kedi tidak ditemukan (Produksi ID: {$validasi->id_produksi_kedi}).");
            }
        }

        // Logika untuk Produksi Stik — TIDAK DIAKTIFKAN.
        // Stik sekarang manual total (tanpa serah terima dari Gudang),
        // jadi tidak ada data yang bisa dipakai untuk memotong stok di sini.
        /*
        if (isset($validasi->id_produksi_stik)) {
            $produksi = $validasi->produksi;
            $details = $produksi->detailMasukStik;

            if ($details && $details->count() > 0) {
                $this->inventoryService->kurangiStokDariProduksi($details, 'Stik', $produksi->tanggal_produksi);
            }
        }
        */
    }
}