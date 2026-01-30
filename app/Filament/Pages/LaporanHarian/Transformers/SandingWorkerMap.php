<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class SandingWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // 1. Kumpulkan detail barang dari relasi hasilSandings
            $detailProduksi = [];
            if ($produksi->hasilSandings) {
                foreach ($produksi->hasilSandings as $hasil) {
                    $b = $hasil->barangSetengahJadi;

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

                    // Menggunakan field 'kuantitas' sesuai model HasilSanding Anda
                    $jumlah = $hasil->kuantitas ?? 0;
                    $detailProduksi[] = "{$namaLengkapBarang} ({$jumlah} Pcs)";
                }
            }
            $shift = $produksi->shift->nama_shift ?? $produksi->shift ?? '-';

            $labelHasil = "SANDING - {$shift} " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 2. Looping Pegawai Sanding
            if ($produksi->pegawaiSandings) {
                foreach ($produksi->pegawaiSandings as $ps) {
                    if (!$ps->pegawai)
                        continue;

                    $jamMasuk = $ps->masuk ? Carbon::parse($ps->masuk)->format('H:i') : '-';
                    $jamPulang = $ps->pulang ? Carbon::parse($ps->pulang)->format('H:i') : '-';

                    $results[] = [
                        'kodep' => $ps->pegawai->kode_pegawai ?? '-',
                        'nama' => $ps->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $ps->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $ps->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
