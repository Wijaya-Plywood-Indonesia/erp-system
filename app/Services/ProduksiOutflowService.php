<?php

namespace App\Services;

use App\Models\ProduksiRotary;

class ProduksiOutflowService
{
    public function getDataKayuKeluar()
    {
        $productions = ProduksiRotary::with([
            'mesin',
            'detailPegawaiRotary',
            'detailPaletRotary.penggunaanLahan.lahan',
            'detailPaletRotary.setoranPaletUkuran'
        ])
        ->latest('tgl_produksi')
        ->get();

        $allData = collect();

        foreach ($productions as $produksi) {
            foreach ($produksi->detailPaletRotary as $hasil) {
                $ukuran = $hasil->setoranPaletUkuran;
                $lahan = $hasil->penggunaanLahan->lahan ?? null;
                
                $totalLembar = (int) ($hasil->total_lembar ?? 0);
                $m3 = $ukuran 
                    ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 1000000000 
                    : 0;

                $allData->push([
                    'tgl' => $produksi->tgl_produksi,
                    'lahan_id' => $lahan->id ?? 0,
                    'nama_lahan' => $lahan ? "{$lahan->kode_lahan} - {$lahan->nama_lahan}" : 'Tanpa Lahan',
                    'mesin' => $produksi->mesin->nama_mesin ?? 'Unknown',
                    'jam_kerja' => "06:00 - 16:00",
                    'ukuran' => $ukuran ? $ukuran->dimensi : '-', // Menggunakan accessor dimensi p x l x t
                    'banyak' => $totalLembar,
                    'kubikasi' => round($m3, 4),
                    'pekerja' => $produksi->detailPegawaiRotary->count() . " Orang",
                    'ongkos_pekerja' => 0,
                    'penyusutan' => 0,
                ]);
            }
        }

        // PROSES GROUPING
        return $allData->groupBy(function ($item) {
            // Kita gabungkan semua kriteria grouping menjadi satu string key
            return $item['tgl'] . '___' . 
                   $item['lahan_id'] . '___' . 
                   $item['mesin'] . '___' . 
                   $item['ukuran'];
        })->map(function ($group) {
            // Kita ambil baris pertama untuk informasi header group
            $first = $group->first();
            
            return [
                'tgl' => $first['tgl'],
                'lahan_id' => $first['lahan_id'],
                'nama_lahan' => $first['nama_lahan'],
                'mesin' => $first['mesin'],
                'jam_kerja' => $first['jam_kerja'],
                'ukuran' => $first['ukuran'],
                // Kita jumlahkan (sum) data yang bersifat angka
                'total_banyak' => $group->sum('banyak'),
                'total_kubikasi' => round($group->sum('kubikasi'), 4),
                'pekerja' => $first['pekerja'], // Asumsi jumlah pekerja sama dalam satu sesi mesin
                // 'detail_transaksi' => $group // Menyimpan data asli jika sewaktu-waktu ingin dilihat
            ];
        })->values(); // Reset key array agar urut 0, 1, 2...
    }
}