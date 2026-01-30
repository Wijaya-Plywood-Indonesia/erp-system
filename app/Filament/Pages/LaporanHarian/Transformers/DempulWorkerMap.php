<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class DempulWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan semua detail barang dengan format panjang
            $detailProduksi = [];
            if ($produksi->detailDempuls) {
                foreach ($produksi->detailDempuls as $detail) {
                    $b = $detail->barangSetengahJadi;

                    if ($b) {
                        // Mengikuti format DetailDempulsTable: Kategori | Ukuran | Grade | Jenis
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

            // Gabungkan semua detail menjadi satu string untuk kolom hasil
            $labelHasil = "DEMPUL: " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 2. Looping Pegawai yang terdaftar
            if ($produksi->rencanaPegawaiDempuls) {
                foreach ($produksi->rencanaPegawaiDempuls as $rp) {
                    if (!$rp->pegawai) continue;

                    // --- PENYESUAIAN FORMAT JAM: HH:mm:ss ---
                    $jamMasuk = $rp->masuk ? Carbon::parse($rp->masuk)->format('H:i:s') : '-';
                    $jamPulang = $rp->pulang ? Carbon::parse($rp->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $rp->pegawai->kode_pegawai ?? '-',
                        'nama' => $rp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $rp->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $rp->keterangan ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
