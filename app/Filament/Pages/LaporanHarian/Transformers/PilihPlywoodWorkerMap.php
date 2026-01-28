<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class PilihPlywoodWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan detail barang dari relasi hasilPilihPlywood
            $detailProduksi = [];
            if ($produksi->hasilPilihPlywood) {
                foreach ($produksi->hasilPilihPlywood as $hasil) {
                    $b = $hasil->barangSetengahJadiHp;

                    if ($b) {
                        // Format Panjang: Kategori | Ukuran | Grade | Jenis
                        $namaLengkapBarang =
                            ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                            ($b->grade?->nama_grade ?? '-') . ' | ' .
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    } else {
                        $namaLengkapBarang = 'Barang Tidak Diketahui';
                    }

                    // Mengambil jumlah bagus (atau total jumlah sesuai kebutuhan Anda)
                    $jumlah = $hasil->jumlah_bagus ?? 0;
                    $detailProduksi[] = "{$namaLengkapBarang} ({$jumlah} Pcs)";
                }
            }

            $labelHasil = "PILIH PLYWOOD: " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 2. Looping Pegawai Pilih Plywood
            if ($produksi->pegawaiPilihPlywood) {
                foreach ($produksi->pegawaiPilihPlywood as $pp) {
                    if (!$pp->pegawai) continue;

                    $jamMasuk = $pp->masuk ? Carbon::parse($pp->masuk)->format('H:i') : '-';
                    $jamPulang = $pp->pulang ? Carbon::parse($pp->pulang)->format('H:i') : '-';

                    $results[] = [
                        'kodep' => $pp->pegawai->kode_pegawai ?? '-',
                        'nama' => $pp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pp->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $pp->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
