<?php

namespace App\Observers;

use App\Models\ValidasiNyusup;
use App\Models\DetailBarangDikerjakan;
use App\Models\GudangSatuLog;
use App\Models\JenisKayu;
use App\Services\StokGudangSatuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ValidasiNyusupObserver
{
    protected StokGudangSatuService $stokService;

    public function __construct(StokGudangSatuService $stokService)
    {
        $this->stokService = $stokService;
    }

    public function saved(ValidasiNyusup $validasi): void
    {
        Log::info("ValidasiNyusupObserver: status={$validasi->status} id_produksi={$validasi->id_produksi_nyusup}");
        
        if ($validasi->status !== 'divalidasi') {
            return;
        }

        $produksi = $validasi->produksi;
        if (!$produksi) {
            Log::warning("ValidasiNyusupObserver: Produksi tidak ditemukan untuk Validasi ID {$validasi->id}");
            return;
        }

        $details = $produksi->detailBarangDikerjakan;
        if ($details->isEmpty()) {
            Log::info("ValidasiNyusupObserver: Tidak ada detail barang dikerjakan untuk Produksi ID {$produksi->id}");
            return;
        }

        DB::transaction(function () use ($details, $produksi) {
            foreach ($details as $detail) {
                $lembar = (float) $detail->modal;
                if ($lembar <= 0) {
                    continue;
                }

                $b = $detail->barangSetengahJadiHp ?? $detail->serahTerima?->barangSetengahJadi;
                if (!$b) {
                    Log::warning("ValidasiNyusupObserver: Barang tidak ditemukan pada Detail ID {$detail->id}");
                    continue;
                }

                $ukuran = $b->ukuran;
                $grade = $b->grade;
                $jenisBarang = $b->jenisBarang;

                if (!$ukuran || !$grade || !$jenisBarang) {
                    Log::warning("ValidasiNyusupObserver: Data ukuran, grade, atau jenis barang tidak lengkap pada Detail ID {$detail->id}");
                    continue;
                }

                $namaBarangLengkap = $jenisBarang->nama_jenis_barang;
                $jenisKayu = JenisKayu::where('nama_kayu', $namaBarangLengkap)->first();
                if (!$jenisKayu) {
                    $jenisKayu = JenisKayu::query()
                        ->orderByRaw('LENGTH(nama_kayu) DESC')
                        ->get()
                        ->first(fn ($kayu) => str_contains(
                            strtolower($namaBarangLengkap),
                            strtolower($kayu->nama_kayu)
                        ));
                }

                if (!$jenisKayu) {
                    Log::warning("ValidasiNyusupObserver: Jenis kayu \"{$namaBarangLengkap}\" tidak ditemukan di tabel Jenis Kayu.");
                    continue;
                }

                // Cek apakah sudah pernah dipotong stoknya untuk menghindari double deduction
                $exists = GudangSatuLog::where('referensi_type', DetailBarangDikerjakan::class)
                    ->where('referensi_id', $detail->id)
                    ->exists();

                if ($exists) {
                    Log::info("ValidasiNyusupObserver: Stok untuk Detail ID {$detail->id} sudah pernah dikurangi. Lewati.");
                    continue;
                }

                $kubikasi = ($lembar * (float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal) / 10000000;

                $keterangan = "Kurangi stok (modal) dari Produksi Nyusup ID: {$produksi->id} tgl {$produksi->tanggal_produksi}";

                $this->stokService->kurang(
                    idJenisKayu: $jenisKayu->id,
                    panjang: (float) $ukuran->panjang,
                    lebar: (float) $ukuran->lebar,
                    tebal: (float) $ukuran->tebal,
                    kwGrade: $grade->nama_grade,
                    lembar: $lembar,
                    kubikasi: $kubikasi,
                    keterangan: $keterangan,
                    referensi: $detail
                );

                Log::info("ValidasiNyusupObserver: Berhasil kurangi stok gudang satu {$lembar} lembar untuk Detail ID {$detail->id}");
            }
        });
    }
}
