<?php

namespace Database\Seeders;

use App\Models\HasilProduksiPercobaan;
use App\Models\LogMasukPercobaan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProduksiSeederPercobaan extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Siklus 1
        LogMasukPercobaan::create(['tgl_masuk' => '2026-02-01', 'qty' => 100]); // Masuk 100
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-02', 'qty_keluar' => 80]); // Keluar 80 (Sisa 20)

        LogMasukPercobaan::create(['tgl_masuk' => '2026-02-03', 'qty' => 50]); // Masuk 50 (Sisa jadi 70)
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-04', 'qty_keluar' => 70]); // Keluar 70 (Habis! Selesai Siklus 1)

        // Siklus 2 (Mulai baru lagi)
        LogMasukPercobaan::create(['tgl_masuk' => '2026-02-05', 'qty' => 120]); // Masuk 120
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-06', 'qty_keluar' => 50]); // Keluar 50 (Sisa 70)
        
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-07', 'qty_keluar' => 70]); // Keluar 50 (Sisa 70)
        }
}
