<?php

namespace Tests\Feature;

use App\Models\HasilProduksiPercobaan;
use App\Models\LogMasuk;
use App\Models\HasilProduksi;
use App\Models\LogMasukPercobaan;
use App\Services\ProduksiService;
use App\Services\ProduksiServicePercobaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduksiSiklusTestPercobaan extends TestCase
{
    use RefreshDatabase; // Ini akan mengosongkan DB setiap kali test dijalankan

    /** @test */
    public function test_siklus_produksi_berhenti_saat_saldo_nol()
    {
        // 1. Prepare Data (Sama dengan skenario analogi kamu)
        LogMasukPercobaan::create(['tgl_masuk' => '2026-02-01', 'qty' => 100]);
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-02', 'qty_keluar' => 80]); // Sisa 20
        LogMasukPercobaan::create(['tgl_masuk' => '2026-02-03', 'qty' => 50]);   // Sisa 70
        HasilProduksiPercobaan::create(['tgl_produksi' => '2026-02-04', 'qty_keluar' => 70]); // HABIS!

        // 2. Execute Service
        $service = new ProduksiServicePercobaan();
        $hasil = $service->getLaporanSiklus();

        dd($hasil);

        // 3. Assert (Memastikan hasil sesuai ekspektasi)
        $this->assertCount(1, $hasil);
        $this->assertEquals('HABIS', $hasil[0]['status']);
        $this->assertEquals(150, $hasil[0]['masuk']);
        $this->assertCount(2, $hasil[0]['data_kayu_masuk']); // Ada 2 record kayu masuk
        $this->assertCount(2, $hasil[0]['data_hasil_produksi']); // Ada 2 record produksi
    }
}