<?php

namespace App\Filament\Pages\Absen\Transformers;

use Illuminate\Support\Collection;

class GuellotineWorkerMap
{
    public static function make(Collection $data): array
    {
        $results = [];

        foreach ($data as $produksi) {
            // Menggunakan relasi pegawaiGuellotine sesuai definisi di model produksi_guellotine
            foreach ($produksi->pegawaiGuellotine as $detail) {
                $pegawai = $detail->pegawai;

                if (!$pegawai) continue;

                $results[] = [
                    'kodep'      => ltrim($pegawai->kode_pegawai, '0'),
                    'nama'       => $pegawai->nama_pegawai,
                    'masuk'      => $detail->masuk ?? '-',
                    'pulang'     => $detail->pulang ?? '-',
                    'hasil'      => 'GUELLOTINE', // Label divisi dalam array agar konsisten dengan merge logic
                    'ijin'       => $detail->ijin ?? '',
                    'keterangan' => $detail->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
