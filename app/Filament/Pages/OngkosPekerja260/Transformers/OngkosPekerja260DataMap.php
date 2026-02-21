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
            // LOGIKA DI BELAKANG LAYAR: HITUNG AKUMULASI HARIAN
            // ============================================================
            $totalM3Harian = 0;
            $totalBykHarian = 0;
            $sumSolasiPerM3Harian = 0; // Perbaikan: Menampung SUM dari Solasi/m3 per baris

            foreach ($groupedDetails as $items) {
                $u = $items->first()->ukuran;
                $byk = $items->sum('total_lembar');
                $m3Baris = (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $byk) / 10000000;

                $totalM3Harian += $m3Baris;
                $totalBykHarian += $byk;

                // Hitung Solasi/m3 Baris ini terlebih dahulu
                $hargaSolasiBaris = ($byk / ($masterTotalSolasi ?: 1)) * $masterHargaSolasi;
                $solasiPerM3Baris = $m3Baris > 0 ? $hargaSolasiBaris / $m3Baris : 0;

                // Masukkan ke SUM harian
                $sumSolasiPerM3Harian += $solasiPerM3Baris;
            }

            $totalPekerja = $produksi->detailPegawaiRotary->count();
            $totalHargaPekerja = $masterHargaPkj * $totalPekerja;
            $ongkosMesin = (float) ($produksi->mesin->ongkos_mesin ?? 0);

            // Perhitungan Ongkos Berdasarkan Rumus Baru Anda
            $ongkosPerM3Kolektif = $totalM3Harian > 0 ? $totalHargaPekerja / $totalM3Harian : 0;

            // Rumus: (Gaji + Mesin + SUM Solasi/m3) / Total M3 Harian
            $ongkosM3PlusMesinKolektif = $totalM3Harian > 0
                ? ($totalHargaPekerja + $ongkosMesin + $sumSolasiPerM3Harian) / $totalM3Harian
                : 0;

            $ongkosPerLbKolektif = $totalBykHarian > 0 ? ($totalHargaPekerja + $ongkosMesin) / $totalBykHarian : 0;

            // ============================================================
            // PROSES MAPPING HASIL (Tetap Mengikuti Template Anda)
            // ============================================================
            foreach ($groupedDetails as $key => $items) {
                $first = $items->first();
                $u = $first->ukuran;
                $totalBanyak = $items->sum('total_lembar');
                $m3 = (($u->panjang ?? 0) * ($u->lebar ?? 0) * ($u->tebal ?? 0) * $totalBanyak) / 10000000;

                // Hitung Solasi Individu per Baris
                $totalSolasi = $totalBanyak / ($masterTotalSolasi ?: 1);
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
                    'ongkos_per_m3' => $ongkosPerM3Kolektif,
                    'ongkos_mesin' => $ongkosMesin,
                    'ongkos_m3_mesin' => $ongkosM3PlusMesinKolektif,
                    'ongkos_per_lb' => $ongkosPerLbKolektif,
                    'ket' => $produksi->kendala ?? '-',
                ];
            }
        }
        return $results;
    }
}
