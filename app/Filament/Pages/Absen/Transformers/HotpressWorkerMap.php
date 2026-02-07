<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;

class HotpressWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // Label hasil sekarang dibuat statis tanpa detail barang
            $labelHasil = "HOT PRESS";

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
                        'hasil' => $labelHasil, // Mengirim teks "HOT PRESS" saja
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
