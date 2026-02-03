<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;

class HotpressWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            $detailProduksi = [];

            // 1. Ambil Hasil Platform
            if ($produksi->platformHasilHp) {
                foreach ($produksi->platformHasilHp as $item) {
                    $b = $item->barangSetengahJadi;
                    $namaBarang = $b ?
                        ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                        ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                        ($b->grade?->nama_grade ?? '-') : 'Platform';

                    $detailProduksi[] = "PLT: {$namaBarang} ({$item->isi} Pcs)";
                }
            }

            // 2. Ambil Hasil Triplek
            if ($produksi->triplekHasilHp) {
                foreach ($produksi->triplekHasilHp as $item) {
                    $b = $item->barangSetengahJadi;
                    $namaBarang = $b ?
                        ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                        ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                        ($b->grade?->nama_grade ?? '-') : 'Triplek';

                    $detailProduksi[] = "TPL: {$namaBarang} ({$item->isi} Pcs)";
                }
            }

            $labelHasil = "HOTPRESS: " . (empty($detailProduksi) ? '-' : implode('; ', $detailProduksi));

            // 3. Mapping Pegawai
            if ($produksi->detailPegawaiHp) {
                foreach ($produksi->detailPegawaiHp as $dp) {
                    // Nama relasi di model Anda adalah pegawaiHp
                    if (!$dp->pegawaiHp) continue;

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '-';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $dp->pegawaiHp->kode_pegawai ?? '-',
                        'nama' => $dp->pegawaiHp->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $dp->ijin ?? '-',
                        'potongan_targ' => 0,
                        'keterangan' => $dp->ket ?? $produksi->kendala ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
