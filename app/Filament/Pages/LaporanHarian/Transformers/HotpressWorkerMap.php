<?php

namespace App\Filament\Resources\ProduksiHps\Transformers; // Pastikan namespace sesuai folder Anda

use Carbon\Carbon;

class HotpressWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            /** * 1. DETEKSI SHIFT
             * Asumsi: tabel produksi_hps memiliki kolom 'shift' (isi: pagi/malam).
             * Jika nama kolomnya berbeda (misal: 'is_malam'), silakan sesuaikan logikanya.
             */
            $shift = (strtoupper($produksi->shift ?? '') === 'MALAM') ? 'MALAM' : 'PAGI';

            // Gabungkan menjadi "HOT PRESS PAGI" atau "HOT PRESS MALAM"
            $labelHasil = "HOTPRESS {$shift}";

            // Mapping Pegawai
            if ($produksi->detailPegawaiHp) {
                foreach ($produksi->detailPegawaiHp as $dp) {
                    if (!$dp->pegawaiHp) continue;

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '-';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $dp->pegawaiHp->kode_pegawai ?? '-',
                        'nama' => $dp->pegawaiHp->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelHasil, // Sekarang berisi "HOT PRESS PAGI" atau "HOT PRESS MALAM"
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
