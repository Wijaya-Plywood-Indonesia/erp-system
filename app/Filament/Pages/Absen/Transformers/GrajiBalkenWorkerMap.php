<?php

namespace App\Filament\Pages\Absen\Transformers;

use Illuminate\Support\Collection;

class GrajiBalkenWorkerMap
{
    public static function make(Collection $data): array
    {
        $results = [];

        foreach ($data as $produksi) {
            foreach ($produksi->pegawaiGrajiBalken as $detail) {
                $pegawai = $detail->pegawai;

                if (!$pegawai) continue;

                $results[] = [
                    'kodep'      => ltrim($pegawai->kode_pegawai, '0'),
                    'nama'       => $pegawai->nama_pegawai,
                    'masuk'      => $detail->masuk ?? '-',
                    'pulang'     => $detail->pulang ?? '-',
                    'hasil'      => 'GRAJI BALKEN', // Disimpan dalam array untuk konsistensi merge logic
                    'ijin'       => $detail->ijin ?? '',
                    'keterangan' => $detail->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
