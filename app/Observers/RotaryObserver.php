<?php

namespace App\Observers;

use App\Models\PenggunaanLahanRotary;
use App\Services\HppAverageService;

// Penggunaan Lahan Rotary Observer
class RotaryObserver
{
    /* Fungsi untuk proses created jika terdapat lahan yang nilainya tidak sama dengan 0 maka langsung ada pemberitahuan kayu keluar */
    public function created(PenggunaanLahanRotary $penggunaan): void
    {
        if ($penggunaan->jumlah_batang != 0) {
            app(HppAverageService::class)->prosesKeluarRotary(
                lahanId: $penggunaan->id_lahan,
                jenisKayuId: $penggunaan->id_jenis_kayu,
                referensi: $penggunaan
            );
        }
    }

    /* Jika terdapat lahan pada pagi hari nilainya masih 0 dan melakukan aksi edit pada penggunaan lahan rotary dan nilainya terdanyata tidak sama dengan 0 makan akan langsung tercatat */
    public function updated(PenggunaanLahanRotary $penggunaan): void
    {
        if ($penggunaan->wasChanged('jumlah_batang') && $penggunaan->jumlah_batang != 0) {
            app(HppAverageService::class)->prosesKeluarRotary(
                lahanId: $penggunaan->id_lahan,
                jenisKayuId: $penggunaan->id_jenis_kayu,
                referensi: $penggunaan
            );
        }
    }
}
