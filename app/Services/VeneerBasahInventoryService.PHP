<?php

namespace App\Services;

use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VeneerBasahInventoryService
{
    public function kurangiStokDariProduksi($details, $tipeProduksi, $tanggalProduksi)
    {
        DB::transaction(function () use ($details, $tipeProduksi, $tanggalProduksi) {
            foreach ($details as $detail) {
                $ukuran = $detail->ukuran;

                if (!$ukuran) {
                    Log::error("Gagal potong stok: Detail ID {$detail->id} tidak memiliki relasi ukuran.");
                    continue;
                }

                // 1. Cari summary stok
                $summary = HppVeneerBasahSummary::where([
                    'id_jenis_kayu' => $detail->id_jenis_kayu,
                    'panjang'       => $ukuran->panjang,
                    'lebar'         => $ukuran->lebar,
                    'tebal'         => $ukuran->tebal,
                    'kw'            => $detail->kw,
                ])->lockForUpdate()->first();

                // JIKA STOK TIDAK DITEMUKAN
                if (!$summary) {
                    Log::warning("STOK TIDAK DITEMUKAN: Jenis Kayu ID {$detail->id_jenis_kayu}, Ukuran {$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal}, KW {$detail->kw}");
                    continue;
                }

                // 2. Kalkulasi Kubikasi
                $kubikasiKeluar = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $detail->isi) / 1000000000;
                $nilaiKeluar    = $kubikasiKeluar * $summary->hpp_average;

                // 3. Catat Log Keluar
                $log = HppVeneerBasahLog::create([
                    'id_jenis_kayu'      => $detail->id_jenis_kayu,
                    'panjang'            => $ukuran->panjang,
                    'lebar'              => $ukuran->lebar,
                    'tebal'              => $ukuran->tebal,
                    'kw'                 => $detail->kw,
                    'tanggal'            => $tanggalProduksi,
                    'tipe_transaksi'     => 'keluar',
                    'keterangan'         => "Produksi $tipeProduksi Tgl " . \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y') . ": Palet " . ($detail->no_palet ?? 'AF'),
                    'referensi_type'     => get_class($detail),
                    'referensi_id'       => $detail->id,
                    'total_lembar'       => $detail->isi,
                    'total_kubikasi'     => $kubikasiKeluar,
                    'hpp_average'        => $summary->hpp_average,
                    'nilai_stok'         => $nilaiKeluar,
                    'stok_lembar_before' => $summary->stok_lembar,
                    'stok_kubikasi_before' => $summary->stok_kubikasi,
                    'nilai_stok_before'  => $summary->nilai_stok,
                    'stok_lembar_after'  => $summary->stok_lembar - $detail->isi,
                    'stok_kubikasi_after' => $summary->stok_kubikasi - $kubikasiKeluar,
                    'nilai_stok_after'   => $summary->nilai_stok - $nilaiKeluar,
                ]);

                // 4. Update Summary
                $summary->decrement('stok_lembar', $detail->isi);
                $summary->decrement('stok_kubikasi', $kubikasiKeluar);
                $summary->decrement('nilai_stok', $nilaiKeluar);
                $summary->update(['id_last_log' => $log->id]);

                Log::info("Berhasil potong stok: {$tipeProduksi} - Palet " . ($detail->no_palet ?? 'AF') . " sebanyak {$detail->isi} lembar.");
            }
        });
    }
}