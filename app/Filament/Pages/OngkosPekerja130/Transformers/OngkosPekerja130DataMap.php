<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use App\Models\HargaPegawai;
use App\Models\HargaSolasi;
use App\Models\TotalSolasi;
use Carbon\Carbon;

class OngkosPekerja130DataMap
{
    public static function make($collection): array
    {
        $results = [];
        $masterHargaPkj = HargaPegawai::first()->harga ?? 0;
        $masterTotalSolasi = TotalSolasi::first()->total ?? 0;
        $masterHargaSolasi = HargaSolasi::first()->harga ?? 0;

        foreach ($collection as $produksi) {
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '');
            $kategoriLaporan = "KUPASAN 130 - " . $namaMesin;

            // Grouping per Ukuran (P, L, T + Jenis)
            $groupedDetails = $produksi->detailPaletRotary->groupBy(function ($item) {
                $u = $item->ukuran;
                $jenis = $item->penggunaanLahan?->lahan?->jenisKayu?->nama_jenis_barang ?? 'S';
                return ($u->panjang ?? 0) . '-' . ($u->lebar ?? 0) . '-' . ($u->tebal ?? 0) . '-' . $jenis;
            });

            // Hitung Akumulasi Harian (Kolektif)
            $totalM3Tgl = 0;
            $totalBykTgl = 0;
            foreach ($groupedDetails as $items) {
                $u = $items->first()->ukuran;
                $byk = $items->sum('total_lembar');
                $totalBykTgl += $byk;
                $totalM3Tgl += (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $byk) / 10000000;
            }

            $totalPekerja = $produksi->detailPegawaiRotary->count();
            $totalHargaPkj = $masterHargaPkj * $totalPekerja;
            $ongkosMesin = (float)($produksi->mesin->ongkos_mesin ?? 0);

            // Mapping Data per Baris Ukuran
            foreach ($groupedDetails as $items) {
                $first = $items->first();
                $u = $first->ukuran;
                $byk = $items->sum('total_lembar');
                $m3 = (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $byk) / 10000000;

                // Solasi per Ukuran (Individual)
                $solasiByk = $byk / ($masterTotalSolasi ?: 1);
                $solasiHrg = $solasiByk * $masterHargaSolasi;

                $results[] = [
                    'kategori_mesin' => $kategoriLaporan,
                    'tanggal' => Carbon::parse($produksi->tgl_produksi)->format('d-M'),
                    'p' => $u->panjang,
                    'l' => $u->lebar,
                    't' => $u->tebal,
                    'jenis' => strtoupper(substr($first->penggunaanLahan?->lahan?->jenisKayu?->nama_jenis_barang ?? 'S', 0, 1)),
                    'kw1' => $items->where('kw', '1')->sum('total_lembar'),
                    'kw2' => $items->where('kw', '2')->sum('total_lembar'),
                    'kw3' => $items->where('kw', '3')->sum('total_lembar'),
                    'kw4' => $items->where('kw', '4')->sum('total_lembar'),
                    'kw5' => $items->where('kw', '5')->sum('total_lembar'),
                    'byk' => $byk,
                    'm3' => $m3,
                    // Solasi tetap individual
                    // 'total_solasi' => $solasiByk,
                    // 'harga_solasi' => $solasiHrg,
                    // 'solasi_m3' => $m3 > 0 ? $solasiHrg / $m3 : 0,
                    // 'solasi_lbr' => $byk > 0 ? $solasiHrg / $byk : 0,
                    // Kolektif (Akan di-merge: Tanggal, Pekerja, Harga, Ongkos)
                    'ttl_pkj' => $totalPekerja,
                    'harga' => $totalHargaPkj,
                    'ongkos_per_m3' => $totalM3Tgl > 0 ? $totalHargaPkj / $totalM3Tgl : 0,
                    'ongkos_mesin' => $ongkosMesin,
                    'ongkos_m3_mesin' => $totalM3Tgl > 0 ? ($totalHargaPkj + $ongkosMesin + ($totalBykTgl / $masterTotalSolasi * $masterHargaSolasi)) / $totalM3Tgl : 0,
                    'ongkos_per_lb' => $totalBykTgl > 0 ? ($totalHargaPkj + $ongkosMesin) / $totalBykTgl : 0,
                    'ket' => $produksi->kendala ?? '-',
                ];
            }
        }
        return $results;
    }
}
