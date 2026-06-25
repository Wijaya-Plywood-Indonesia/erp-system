<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;

class GrajiTriplekWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            $shift = (strtoupper($produksi->shift ?? '') === 'MALAM') ? 'MALAM' : 'PAGI';

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

            $teksDetail = empty($detailProduksi) ? '-' : implode('; ', $detailProduksi);
            $labelHasil = "GRAJI TRIPLEK {$shift}: " . $teksDetail;

            // Calculate actual production (sum of isi from hasilGrajiTriplek)
            $totalActual = 0;
            if ($produksi->hasilGrajiTriplek) {
                foreach ($produksi->hasilGrajiTriplek as $detail) {
                    $totalActual += $detail->isi ?? 0;
                }
            }

            // Calculate worker salary deduction using the old formula
            // Target is dynamic based on worker count: N * 750
            $pekerjaList = [];
            if ($produksi->pegawaiGrajiTriplek) {
                foreach ($produksi->pegawaiGrajiTriplek as $pg) {
                    if ($pg->pegawaiGrajiTriplek) {
                        $pekerjaList[] = $pg;
                    }
                }
            }
            $N = count($pekerjaList);
            $potonganPerOrang = 0;

            if ($N > 0) {
                $target = $N * 750;
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

            // 2. Looping Pegawai Graji Triplek
            if ($produksi->pegawaiGrajiTriplek) {
                foreach ($produksi->pegawaiGrajiTriplek as $pg) {
                    // DISESUAIKAN: Nama relasi di model Anda adalah pegawaiGrajiTriplek
                    if (!$pg->pegawaiGrajiTriplek) continue;

                    $jamMasuk = $pg->masuk ? Carbon::parse($pg->masuk)->format('H:i:s') : '-';
                    $jamPulang = $pg->pulang ? Carbon::parse($pg->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $pg->pegawaiGrajiTriplek->kode_pegawai ?? '-',
                        'nama' => $pg->pegawaiGrajiTriplek->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil,
                        'ijin' => $pg->ijin ?? '-',
                        'potongan_targ' => (int) $potonganPerOrang,
                        'keterangan' => $pg->ket  ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
