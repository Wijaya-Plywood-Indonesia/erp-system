<?php

namespace App\Filament\Pages\Absen\Transformers;

use Illuminate\Support\Collection;

class GrajiStikWorkerMap
{
    public static function make(Collection $data): array
    {
        $results = [];

        foreach ($data as $produksi) {
            // Menggunakan relasi pegawaiGrajiStik dari model GrajiStik
            foreach ($produksi->pegawaiGrajiStik as $detail) {
                $pegawai = $detail->pegawai;

                if (!$pegawai) continue;

                $results[] = [
                    'kodep'      => ltrim($pegawai->kode_pegawai, '0'),
                    'nama'       => $pegawai->nama_pegawai,
                    // Penyesuaian nama kolom sesuai model (jam_masuk & jam_pulang)
                    'masuk'      => $detail->jam_masuk ?? '-',
                    'pulang'     => $detail->jam_pulang ?? '-',
                    'hasil'      => 'GRAJI STIK',
                    'ijin'       => $detail->ijin ?? '',
                    'keterangan' => $detail->keterangan ?? '',
                ];
            }
        }

        return $results;
    }
}
