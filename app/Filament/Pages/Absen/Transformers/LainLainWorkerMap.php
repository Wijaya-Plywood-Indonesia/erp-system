<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;

class LainLainWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $detailLain) {
            // Kita melakukan looping dari DetailLainLain yang memiliki banyak LainLain (pegawai)
            if ($detailLain->lainLains) {
                foreach ($detailLain->lainLains as $item) {
                    if (!$item->pegawai) continue;

                    $jamMasuk = $item->masuk ? Carbon::parse($item->masuk)->format('H:i:s') : '-';
                    $jamPulang = $item->pulang ? Carbon::parse($item->pulang)->format('H:i:s') : '-';

                    $results[] = [
                        'kodep' => $item->pegawai->kode_pegawai ?? '-',
                        'nama' => $item->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => "LAIN-LAIN: " . ($item->hasil ?? '-'), // Memberikan label Lain-lain
                        'ijin' => $item->ijin ?? '',
                        'potongan_targ' => 0, // Sesuai permintaan: tanpa potongan
                        'keterangan' => $item->ket ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
