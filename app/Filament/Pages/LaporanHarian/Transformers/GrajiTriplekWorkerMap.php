<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class GrajiTriplekWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan detail barang dari relasi hasilGrajiTriplek
            $detailProduksi = [];
            if ($produksi->hasilGrajiTriplek) {
                foreach ($produksi->hasilGrajiTriplek as $detail) {
                    $b = $detail->barangSetengahJadiHp;

                    if ($b) {
                        // Format: Kategori | Ukuran | Grade | Jenis
                        $namaLengkapBarang =
                            ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                            ($b->grade?->nama_grade ?? '-') . ' | ' .
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    } else {
                        $namaLengkapBarang = 'Barang Tidak Diketahui';
                    }

                    $jumlah = $detail->isi ?? 0;
                    $detailProduksi[] = "{$namaLengkapBarang} ({$jumlah} Pcs)";
                }
            }

            $labelHasil = "GRAJI TRIPLEK: " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 2. Looping Pegawai Graji Triplek
            if ($produksi->pegawaiGrajiTriplek) {
                foreach ($produksi->pegawaiGrajiTriplek as $pg) {
                    // DISESUAIKAN: Nama relasi di model Anda adalah pegawaiGrajiTriplek
                    if (!$pg->pegawaiGrajiTriplek) continue;

                    $jamMasuk = $pg->masuk ? Carbon::parse($pg->masuk)->format('H:i') : '-';
                    $jamPulang = $pg->pulang ? Carbon::parse($pg->pulang)->format('H:i') : '-';

                    $results[] = [
                        'kodep' => $pg->pegawaiGrajiTriplek->kode_pegawai ?? '-',
                        'nama' => $pg->pegawaiGrajiTriplek->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pg->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $pg->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
