<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TurunKayuWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $item) {
            $labelDivisi = "TURUN KAYU";
            foreach ($item->pegawaiTurunKayu as $ptk) {
                if (!$ptk->pegawai) continue;

                // Format Waktu dengan Detik: 06:00:00
                $jamMasuk = $ptk->jam_masuk ? Carbon::parse($ptk->jam_masuk)->format('H:i:s') : '-';
                $jamPulang = $ptk->jam_pulang ? Carbon::parse($ptk->jam_pulang)->format('H:i:s') : '-';

                $results[] = [
                    'kodep' => $ptk->pegawai->kode_pegawai ?? '-',
                    'nama' => $ptk->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $jamMasuk,
                    'pulang' => $jamPulang,
                    'hasil' => $labelDivisi,
                    'ijin' => $ptk->izin ?? '',
                    'potongan_targ' => 0, // Belum ada logika potongan untuk Turun Kayu
                    'keterangan' => $ptk->keterangan ?? '',
                ];
            }
        }

        return $results;
    }
}
