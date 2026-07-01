<?php

namespace App\Filament\Pages\Absen\Transformers;

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

            // Calculate actual production (sum of kuantitas from hasilSandings)
            $totalActual = 0;
            if ($produksi->hasilSandings) {
                foreach ($produksi->hasilSandings as $hasil) {
                    $totalActual += $hasil->kuantitas ?? 0;
                }
            }

            // Determine dominant item in Sanding
            $isSengon = true;
            $maxQty = -1;
            if ($produksi->hasilSandings) {
                foreach ($produksi->hasilSandings as $hasil) {
                    $qty = $hasil->kuantitas ?? 0;
                    if ($qty > $maxQty) {
                        $maxQty = $qty;
                        $b = $hasil->barangSetengahJadi;
                        if ($b) {
                            $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);
                        }
                    }
                }
            }

            if (!$isSengon) {
                $target = 450;
            } else {
                $target = 250;
                if ($produksi->id_mesin == 24 || ($produksi->mesin && stripos($produksi->mesin->nama_mesin, 'besar') !== false)) {
                    $target = 800;
                }
            }

            // Calculate worker salary deduction using the old formula
            $pekerjaList = $produksi->pegawaiSandings ?? [];
            $N = count($pekerjaList);
            $potonganPerOrang = 0;

            if ($N > 0) {
                $deficit = $target - $totalActual;
                if ($deficit > 0) {
                    // Rumus lama: (deficit * (Gaji / Target)) / N
                    $potonganRaw = ($deficit * 115000) / ($target * $N);

                    // --- RUMUS PEMBULATAN KHUSUS (0, 500, 1000) ---
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            // 2. Looping Pegawai Sanding
            if ($produksi->pegawaiSandings) {
                foreach ($produksi->pegawaiSandings as $ps) {
                    if (!$ps->pegawai)
                        continue;

                    $jamMasuk = $ps->masuk ? Carbon::parse($ps->masuk)->format('H:i:s') : '-';
                    $jamPulang = $ps->pulang ? Carbon::parse($ps->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $ps->pegawai->kode_pegawai ?? '-',
                        'nama' => $ps->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $ps->ijin ?? '-',
                        'potongan_targ' => (int) $potonganPerOrang,
                        'keterangan' => $ps->ket  ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
