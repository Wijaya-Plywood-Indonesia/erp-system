<?php

namespace App\Observers;

use App\Models\ValidasiPilihPlywood;
use App\Models\HasilPilihPlywood;
use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use App\Services\StokTriplekJadiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ValidasiPilihPlywoodObserver
{
    protected StokTriplekJadiService $stokService;

    public function __construct(StokTriplekJadiService $stokService)
    {
        $this->stokService = $stokService;
    }

    public function saved(ValidasiPilihPlywood $validasi): void
    {
        Log::info("ValidasiPilihPlywoodObserver: status={$validasi->status} id_produksi={$validasi->id_produksi_pilih_plywood}");
        
        if ($validasi->status !== 'divalidasi') {
            return;
        }

        $produksi = $validasi->produksiPilihPlywood;
        if (!$produksi) {
            Log::warning("ValidasiPilihPlywoodObserver: Produksi tidak ditemukan untuk Validasi ID {$validasi->id}");
            return;
        }

        // Ambil data hasil pilih plywood
        $hasilList = $produksi->hasilPilihPlywood()
            ->with(['barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade', 'barangSetengahJadiHp.jenisBarang'])
            ->get();

        if ($hasilList->isEmpty()) {
            Log::info("ValidasiPilihPlywoodObserver: Tidak ada hasil pilih plywood untuk Produksi ID {$produksi->id}");
            return;
        }

        DB::transaction(function () use ($hasilList, $produksi) {
            foreach ($hasilList as $hasil) {
                $lembar = (float) $hasil->jumlah_bagus;
                if ($lembar <= 0) {
                    continue;
                }

                $bsj = $hasil->barangSetengahJadiHp;
                if (!$bsj) {
                    Log::warning("ValidasiPilihPlywoodObserver: barangSetengahJadiHp tidak ditemukan pada Hasil ID {$hasil->id}");
                    continue;
                }

                $ukuran = $bsj->ukuran;
                $grade = $bsj->grade;
                $jenisBarang = $bsj->jenisBarang;

                if (!$ukuran || !$grade || !$jenisBarang) {
                    Log::warning("ValidasiPilihPlywoodObserver: Data ukuran, grade, atau jenis barang tidak lengkap pada Hasil ID {$hasil->id}");
                    continue;
                }

                $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();
                if (!$jenisKayu) {
                    Log::warning("ValidasiPilihPlywoodObserver: Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di tabel Jenis Kayu.");
                    continue;
                }

                // Cek apakah sudah pernah dipotong stoknya untuk menghindari double deduction
                $exists = HppTriplekJadiLog::where('referensi_type', HasilPilihPlywood::class)
                    ->where('referensi_id', $hasil->id)
                    ->exists();

                if ($exists) {
                    Log::info("ValidasiPilihPlywoodObserver: Stok untuk Hasil ID {$hasil->id} sudah pernah dikurangi. Lewati.");
                    continue;
                }

                $kubikasi = ($lembar * (float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal) / 10000000;

                $keterangan = "Kurangi stok (bagus) dari Pilih Plywood ID: {$produksi->id} tgl {$produksi->tanggal_produksi}";

                $this->stokService->kurang(
                    idJenisKayu: $jenisKayu->id,
                    panjang: (float) $ukuran->panjang,
                    lebar: (float) $ukuran->lebar,
                    tebal: (float) $ukuran->tebal,
                    kwGrade: $grade->nama_grade,
                    lembar: $lembar,
                    kubikasi: $kubikasi,
                    keterangan: $keterangan,
                    referensi: $hasil
                );

                Log::info("ValidasiPilihPlywoodObserver: Berhasil kurangi stok triplek jadi {$lembar} lembar untuk Hasil ID {$hasil->id}");
            }
        });
    }
}
