<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use App\Models\HargaPegawai;
use App\Models\HargaSolasi;
use App\Models\TotalSolasi;
use Carbon\Carbon;

class OngkosPekerja260DataMap
{
    public static function make($collection): array
    {
        $results = [];

        // 1. Ambil Data Master Harga
        $masterHargaPkj = HargaPegawai::first()->harga ?? 0;
        $masterTotalSolasi = TotalSolasi::first()->total ?? 0;
        $masterHargaSolasi = HargaSolasi::first()->harga ?? 0;

        foreach ($collection as $produksi) {
            // ============================================================
            // LOGIKA FILTER MESIN
            // ============================================================
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '');

            // Tentukan kategori mesin
            if (str_contains($namaMesin, 'SPINDLESS') || str_contains($namaMesin, 'MERANTI')) {
                $kategoriLaporan = "KUPASAN 260 - " . $namaMesin;
            } else {
                $kategoriLaporan = "KUPASAN 130 - " . $namaMesin;
            }

            /**
             * Grouping berdasarkan Ukuran (P, L, T) dan Jenis Kayu 
             */
            $groupedDetails = $produksi->detailPaletRotary->groupBy(function ($item) {
                $u = $item->ukuran;
                $jenis = $item->penggunaanLahan?->lahan?->jenisKayu?->nama_jenis_barang ?? 'S';
                return ($u->panjang ?? 0) . '-' . ($u->lebar ?? 0) . '-' . ($u->tebal ?? 0) . '-' . $jenis;
            });

            foreach ($groupedDetails as $key => $items) {
                $first = $items->first();
                $u = $first->ukuran;

                $p = (float) ($u->panjang ?? 0);
                $l = (float) ($u->lebar ?? 0);
                $t = (float) ($u->tebal ?? 0);
                $jenis = $first->penggunaanLahan?->lahan?->jenisKayu?->nama_jenis_barang ?? 'S';

                // 2. Akumulasi Banyak (byk) per kategori KW
                $kw1 = $items->where('kw', '1')->sum('total_lembar');
                $kw2 = $items->where('kw', '2')->sum('total_lembar');
                $kw3 = $items->where('kw', '3')->sum('total_lembar');
                $kw4 = $items->where('kw', '4')->sum('total_lembar');
                $kw5 = $items->where('kw', '5')->sum('total_lembar');
                $totalBanyak = $kw1 + $kw2 + $kw3 + $kw4 + $kw5;

                // 3. Perhitungan Kubikasi (m3) -> P x L x T x Byk / 10.000.000
                $m3 = ($p * $l * $t * $totalBanyak) / 10000000;

                // 4. Perhitungan Pegawai & Harga
                $totalPekerja = $produksi->detailPegawaiRotary->count();
                $totalHargaPekerja = $masterHargaPkj * $totalPekerja;

                // 5. Perhitungan Solasi
                $totalSolasi = $totalBanyak / $masterTotalSolasi;
                $hargaSolasiTotal =  $totalSolasi * $masterHargaSolasi;
                $solasiPerM3 = $m3 > 0 ? $hargaSolasiTotal / $m3 : 0;
                $solasiPerLbr = $totalBanyak > 0 ? $hargaSolasiTotal / $totalBanyak : 0;

                // 6. Perhitungan Ongkos
                $ongkosPerM3 = $m3 > 0 ? $totalHargaPekerja / $m3 : 0;
                $ongkosMesin = (float) ($produksi->mesin->ongkos_mesin ?? 0);

                // Ongkos Per M3 + Mesin
                $ongkosM3PlusMesin = $m3 > 0 ? ($totalHargaPekerja + $ongkosMesin + $solasiPerM3) / $m3 : 0;

                // Ongkos Per Lembar
                $ongkosPerLb = $totalBanyak > 0 ? ($totalHargaPekerja + $ongkosMesin) / $totalBanyak : 0;

                $results[] = [
                    'kategori_mesin' => $kategoriLaporan, // Label Filter Baru
                    'tanggal' => Carbon::parse($produksi->tgl_produksi)->format('d-M'),
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => strtoupper(substr($jenis, 0, 1)),
                    'kw1' => $kw1,
                    'kw2' => $kw2,
                    'kw3' => $kw3,
                    'kw4' => $kw4,
                    'kw5' => $kw5,
                    'byk' => $totalBanyak,
                    'm3' => $m3,
                    'ttl_pkj' => $totalPekerja,
                    'harga' => $totalHargaPekerja,
                    'total_solasi' => $totalSolasi,
                    'harga_solasi' => $hargaSolasiTotal,
                    'solasi_m3' => $solasiPerM3,
                    'solasi_lbr' => $solasiPerLbr,
                    'ongkos_per_m3' => $ongkosPerM3,
                    'ongkos_mesin' => $ongkosMesin,
                    'ongkos_m3_mesin' => $ongkosM3PlusMesin,
                    'ongkos_per_lb' => $ongkosPerLb,
                    'ket' => $produksi->kendala ?? '-',
                ];
            }
        }

        return $results;
    }
}
