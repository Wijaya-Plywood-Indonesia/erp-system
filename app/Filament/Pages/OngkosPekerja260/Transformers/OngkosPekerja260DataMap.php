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

        // 1. Ambil Data Master Harga dengan Fallback 0
        $masterHargaPkj = HargaPegawai::first()->harga ?? 0;
        $masterTotalSolasi = TotalSolasi::first()->total ?? 0;
        $masterHargaSolasi = HargaSolasi::first()->harga ?? 0;

        // Proteksi awal untuk pembagi Master Solasi
        $safeMasterSolasi = $masterTotalSolasi ?: 1;

        foreach ($collection as $produksi) {
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '');
            $kategoriLaporan = (str_contains($namaMesin, 'SPINDLESS') || str_contains($namaMesin, 'MERANTI'))
                ? "KUPASAN 260 - " . $namaMesin
                : "KUPASAN 130 - " . $namaMesin;

            $groupedDetails = $produksi->detailPaletRotary->groupBy(function ($item) {
                $u = $item->ukuran;
                $jenis = $item->penggunaanLahan?->lahan?->jenisKayu?->nama_jenis_barang ?? 'S';
                return ($u->panjang ?? 0) . '-' . ($u->lebar ?? 0) . '-' . ($u->tebal ?? 0) . '-' . $jenis;
            });

            // ============================================================
            // LOGIKA HITUNG AKUMULASI HARIAN (KOLEKTIF)
            // ============================================================
            $totalM3Harian = 0;
            $totalBykHarian = 0;
            $sumSolasiPerM3Harian = 0;

            foreach ($groupedDetails as $items) {
                $u = $items->first()->ukuran;
                $byk = $items->sum('total_lembar');

                // Rumus M3: (P * L * T * Qty) / 10^7
                $m3Baris = (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $byk) / 10000000;

                $totalM3Harian += $m3Baris;
                $totalBykHarian += $byk;

                // Hitung Solasi/m3 Baris ini (Aman dari Division by Zero)
                $hargaSolasiBaris = ($byk / $safeMasterSolasi) * $masterHargaSolasi;
                $solasiPerM3Baris = $m3Baris > 0 ? $hargaSolasiBaris / $m3Baris : 0;

                $sumSolasiPerM3Harian += $solasiPerM3Baris;
            }

            $totalPekerja = $produksi->detailPegawaiRotary->count();
            $totalHargaPekerja = $masterHargaPkj * $totalPekerja;
            $ongkosMesin = (float) ($produksi->mesin->ongkos_mesin ?? 0);

            // ============================================================
            // PROSES MAPPING HASIL PER BARIS
            // ============================================================
            foreach ($groupedDetails as $items) {
                $first = $items->first();
                $u = $first->ukuran;
                $totalBanyak = $items->sum('total_lembar');
                $m3 = (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $totalBanyak) / 10000000;

                // Solasi Individu per Baris menggunakan safe divisor
                $totalSolasi = $totalBanyak / $safeMasterSolasi;
                $hargaSolasiTotal = $totalSolasi * $masterHargaSolasi;
                $solasiPerM3 = $m3 > 0 ? $hargaSolasiTotal / $m3 : 0;
                $solasiPerLbr = $totalBanyak > 0 ? $hargaSolasiTotal / $totalBanyak : 0;

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
                    'byk' => $totalBanyak,
                    'm3' => $m3,
                    'ttl_pkj' => $totalPekerja,
                    'harga' => $totalHargaPekerja,
                    'total_solasi' => $totalSolasi,
                    'harga_solasi' => $hargaSolasiTotal,
                    'solasi_m3' => $solasiPerM3,
                    'solasi_lbr' => $solasiPerLbr,

                    // Logika Kolektif Aman
                    'ongkos_per_m3' => $totalM3Harian > 0 ? $totalHargaPekerja / $totalM3Harian : 0,
                    'ongkos_mesin' => $ongkosMesin,

                    // Rumus: (Gaji + Mesin + SUM Solasi/m3) / Total M3 Harian
                    'ongkos_m3_mesin' => $totalM3Harian > 0
                        ? ($totalHargaPekerja + $ongkosMesin + $sumSolasiPerM3Harian) / $totalM3Harian
                        : 0,

                    'ongkos_per_lb' => $totalBykHarian > 0 ? ($totalHargaPekerja + $ongkosMesin) / $totalBykHarian : 0,
                    'ket' => $produksi->kendala ?? '-',
                ];
            }
        }
        return $results;
    }
}
