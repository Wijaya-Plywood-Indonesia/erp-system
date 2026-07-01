<?php

namespace App\Filament\Pages\Absen\Transformers;

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

            // Calculate actual production (sum of jumlah_bagus)
            $totalActual = 0;
            if ($produksi->hasilPilihPlywood) {
                foreach ($produksi->hasilPilihPlywood as $hasil) {
                    $totalActual += $hasil->jumlah_bagus ?? 0;
                }
            }

            // Determine target based on the dominant item
            $target = 450;
            $maxQty = -1;
            if ($produksi->hasilPilihPlywood) {
                foreach ($produksi->hasilPilihPlywood as $hasil) {
                    $qty = $hasil->jumlah_bagus ?? 0;
                    if ($qty > $maxQty) {
                        $maxQty = $qty;

                        $b = $hasil->barangSetengahJadiHp;
                        if ($b) {
                            $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);

                            if ($isSengon) {
                                $kategoriId = $b->grade?->id_kategori_barang ?? 0;
                                $kategoriNama = $b->grade?->kategoriBarang?->nama_kategori ?? '';

                                $isNonSanding = ($kategoriId == 2 || stripos($kategoriNama, 'mentah') !== false || stripos($kategoriNama, 'non') !== false);
                                $isSanding = ($kategoriId == 1 || (stripos($kategoriNama, 'plywood') !== false && stripos($kategoriNama, 'mentah') === false));

                                if ($isNonSanding) {
                                    $target = 2200; // Sengon Non Sanding
                                } elseif ($isSanding) {
                                    $target = 1950; // Sengon Sanding
                                } else {
                                    $target = 450; // Selain 2 itu
                                }
                            } else {
                                $target = 450; // Selain 2 itu
                            }
                        }
                    }
                }
            }

            // Calculate worker target deduction using old formula (target is per 2 people: groupTarget = N/2 * baseTarget)
            $pekerjaList = $produksi->pegawaiPilihPlywood ?? [];
            $N = count($pekerjaList);
            $potonganPerOrang = 0;

            if ($N > 0) {
                $groupTarget = ($N / 2.0) * $target;
                $deficit = $groupTarget - $totalActual;
                if ($deficit > 0) {
                    // Rumus: (deficit * Gaji) / (groupTarget * N)
                    $potonganRaw = ($deficit * 115000) / ($groupTarget * $N);

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

            // 2. Looping Pegawai Pilih Plywood
            if ($produksi->pegawaiPilihPlywood) {
                foreach ($produksi->pegawaiPilihPlywood as $pp) {
                    if (!$pp->pegawai) continue;

                    $jamMasuk = $pp->masuk ? Carbon::parse($pp->masuk)->format('H:i:s') : '-';
                    $jamPulang = $pp->pulang ? Carbon::parse($pp->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $pp->pegawai->kode_pegawai ?? '-',
                        'nama' => $pp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pp->ijin ?? '-',
                        'potongan_targ' => (int) $potonganPerOrang,
                        'keterangan' => $pp->ket  ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
