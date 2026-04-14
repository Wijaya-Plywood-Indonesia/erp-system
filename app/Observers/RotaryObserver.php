<?php

namespace App\Observers;

use App\Models\PenggunaanLahanRotary;
use App\Services\HppAverageService;

// Penggunaan Lahan Rotary Observer
class RotaryObserver
{
    /* Fungsi untuk proses created jika terdapat lahan yang nilainya tidak sama dengan 0 maka langsung ada pemberitahuan kayu keluar */
    public function created(PenggunaanLahanRotary $penggunaan): void {}

    /* Jika terdapat lahan pada pagi hari nilainya masih 0 dan melakukan aksi edit pada penggunaan lahan rotary dan nilainya terdanyata tidak sama dengan 0 makan akan langsung tercatat */
    public function updated(PenggunaanLahanRotary $penggunaan): void {}
}
