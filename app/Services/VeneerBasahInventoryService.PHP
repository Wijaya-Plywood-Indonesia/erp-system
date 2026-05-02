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

                $summary = HppVeneerBasahSummary::where([
                    'id_jenis_kayu' => $detail->id_jenis_kayu,
                    'panjang'       => $ukuran->panjang,
                    'lebar'         => $ukuran->lebar,
                    'tebal'         => $ukuran->tebal,
                    'kw'            => $detail->kw,
                ])->lockForUpdate()->first();

                if (!$summary) {
                    Log::warning("STOK TIDAK DITEMUKAN: Jenis Kayu ID {$detail->id_jenis_kayu}, Ukuran {$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal}, KW {$detail->kw}");
                    continue;
                }

                $kubikasiKeluar = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $detail->isi) / 1000000000;
                $nilaiKeluar    = $kubikasiKeluar * $summary->hpp_average;

                // Cek apakah stok cukup
                $stokTidakCukup = $summary->stok_lembar <= 0 || $summary->stok_lembar < $detail->isi;

                $keterangan = "Produksi $tipeProduksi Tgl " . \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y') . ": Palet " . ($detail->no_palet ?? 'AF');
                if ($stokTidakCukup) {
                    $keterangan .= " [SKIP: stok tidak cukup, kemungkinan sudah tercatat via opname]";
                }

                $log = HppVeneerBasahLog::create([
                    'id_jenis_kayu'        => $detail->id_jenis_kayu,
                    'panjang'              => $ukuran->panjang,
                    'lebar'                => $ukuran->lebar,
                    'tebal'                => $ukuran->tebal,
                    'kw'                   => $detail->kw,
                    'tanggal'              => $tanggalProduksi,
                    'tipe_transaksi'       => 'keluar',
                    'keterangan'           => $keterangan,
                    'referensi_type'       => get_class($detail),
                    'referensi_id'         => $detail->id,
                    'total_lembar'         => $detail->isi,
                    'total_kubikasi'       => $kubikasiKeluar,
                    'hpp_average'          => $summary->hpp_average,
                    'nilai_stok'           => $nilaiKeluar,
                    'stok_lembar_before'   => $summary->stok_lembar,
                    'stok_kubikasi_before' => $summary->stok_kubikasi,
                    'nilai_stok_before'    => $summary->nilai_stok,
                    // Jika skip, after = before (stok tidak berubah)
                    'stok_lembar_after'    => $stokTidakCukup ? $summary->stok_lembar : $summary->stok_lembar - $detail->isi,
                    'stok_kubikasi_after'  => $stokTidakCukup ? $summary->stok_kubikasi : $summary->stok_kubikasi - $kubikasiKeluar,
                    'nilai_stok_after'     => $stokTidakCukup ? $summary->nilai_stok : $summary->nilai_stok - $nilaiKeluar,
                ]);

                if ($stokTidakCukup) {
                    // Hanya update last log, stok tidak diubah
                    $summary->update(['id_last_log' => $log->id]);
                    Log::warning("SKIP decrement {$tipeProduksi}: stok tidak cukup (stok: {$summary->stok_lembar}, dibutuhkan: {$detail->isi}) - Palet " . ($detail->no_palet ?? 'AF'));
                    continue;
                }

                $summary->decrement('stok_lembar', $detail->isi);
                $summary->decrement('stok_kubikasi', $kubikasiKeluar);
                $summary->decrement('nilai_stok', $nilaiKeluar);
                $summary->update(['id_last_log' => $log->id]);

                Log::info("Berhasil potong stok: {$tipeProduksi} - Palet " . ($detail->no_palet ?? 'AF') . " sebanyak {$detail->isi} lembar.");
            }
        });
    }

    public function kurangiStokDariBongkarKedi($details, $tanggalProduksi)
    {
        DB::transaction(function () use ($details, $tanggalProduksi) {
            foreach ($details as $detail) {
                $ukuran = $detail->ukuran;
                if (!$ukuran) continue;

                $summary = HppVeneerBasahSummary::where([
                    'id_jenis_kayu' => $detail->id_jenis_kayu,
                    'panjang'       => $ukuran->panjang,
                    'lebar'         => $ukuran->lebar,
                    'tebal'         => $ukuran->tebal,
                    'kw'            => $detail->kw,
                ])->lockForUpdate()->first();

                if (!$summary) {
                    Log::warning("STOK TIDAK DITEMUKAN untuk bongkar kedi: Detail ID {$detail->id}");
                    continue;
                }

                $kubikasiKeluar = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $detail->jumlah) / 1_000_000_000;
                $nilaiKeluar    = $kubikasiKeluar * $summary->hpp_average;

                // Cek apakah stok cukup
                $stokTidakCukup = $summary->stok_lembar <= 0 || $summary->stok_lembar < $detail->jumlah;

                $keterangan = "Bongkar Kedi Tgl " . \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y') . " - Palet {$detail->no_palet}";
                if ($stokTidakCukup) {
                    $keterangan .= " [SKIP: stok tidak cukup, kemungkinan sudah tercatat via opname]";
                }

                $log = HppVeneerBasahLog::create([
                    'id_jenis_kayu'        => $detail->id_jenis_kayu,
                    'panjang'              => $ukuran->panjang,
                    'lebar'                => $ukuran->lebar,
                    'tebal'                => $ukuran->tebal,
                    'kw'                   => $detail->kw,
                    'tanggal'              => $tanggalProduksi,
                    'tipe_transaksi'       => 'keluar',
                    'keterangan'           => $keterangan,
                    'referensi_type'       => get_class($detail),
                    'referensi_id'         => $detail->id,
                    'total_lembar'         => $detail->jumlah,
                    'total_kubikasi'       => $kubikasiKeluar,
                    'hpp_average'          => $summary->hpp_average,
                    'nilai_stok'           => $nilaiKeluar,
                    'stok_lembar_before'   => $summary->stok_lembar,
                    'stok_kubikasi_before' => $summary->stok_kubikasi,
                    'nilai_stok_before'    => $summary->nilai_stok,
                    // Jika skip, after = before (stok tidak berubah)
                    'stok_lembar_after'    => $stokTidakCukup ? $summary->stok_lembar : $summary->stok_lembar - $detail->jumlah,
                    'stok_kubikasi_after'  => $stokTidakCukup ? $summary->stok_kubikasi : $summary->stok_kubikasi - $kubikasiKeluar,
                    'nilai_stok_after'     => $stokTidakCukup ? $summary->nilai_stok : $summary->nilai_stok - $nilaiKeluar,
                ]);

                if ($stokTidakCukup) {
                    // Hanya update last log, stok tidak diubah
                    $summary->update(['id_last_log' => $log->id]);
                    Log::warning("SKIP decrement bongkar kedi: stok tidak cukup (stok: {$summary->stok_lembar}, dibutuhkan: {$detail->jumlah}) - Detail ID {$detail->id}");
                    continue;
                }

                $summary->decrement('stok_lembar', $detail->jumlah);
                $summary->decrement('stok_kubikasi', $kubikasiKeluar);
                $summary->decrement('nilai_stok', $nilaiKeluar);
                $summary->update(['id_last_log' => $log->id]);

                Log::info("Berhasil potong stok bongkar kedi - Palet {$detail->no_palet} sebanyak {$detail->jumlah} lembar.");
            }
        });
    }
}
