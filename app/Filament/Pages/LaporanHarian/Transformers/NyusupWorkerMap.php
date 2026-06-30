<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class NyusupWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan semua detail barang nyusup dari relasi detailBarangDikerjakan
            $detailProduksi = [];
            if ($produksi->detailBarangDikerjakan) {
                foreach ($produksi->detailBarangDikerjakan as $detail) {
                    // DISESUAIKAN: Menggunakan barangSetengahJadiHp sesuai Model Anda
                    $b = $detail->barangSetengahJadiHp;

                    if ($b) {
                        // Menggunakan format lengkap: Kategori | Ukuran | Grade | Jenis
                        $namaLengkapBarang =
                            ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                            ($b->grade?->nama_grade ?? '-') . ' | ' .
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    } else {
                        $namaLengkapBarang = 'Barang Tidak Diketahui';
                    }

                    $jumlah = $detail->hasil ?? 0;
                    $detailProduksi[] = "{$namaLengkapBarang} ({$jumlah} Pcs)";
                }
            }

            $labelHasil = "NYUSUP: " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 2. Looping Pegawai Nyusup
            if ($produksi->pegawaiNyusup) {
                foreach ($produksi->pegawaiNyusup as $pn) {
                    if (!$pn->pegawai) continue;

                    $jamMasuk = $pn->masuk ? Carbon::parse($pn->masuk)->format('H:i:s') : '-';
                    $jamPulang = $pn->pulang ? Carbon::parse($pn->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $pn->pegawai->kode_pegawai ?? '-',
                        'nama' => $pn->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pn->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $pn->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
