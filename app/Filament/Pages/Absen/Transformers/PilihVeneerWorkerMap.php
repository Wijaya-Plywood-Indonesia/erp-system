<?php

namespace App\Filament\Pages\Absen\Transformers;

use Illuminate\Support\Collection;

class PilihVeneerWorkerMap
{
    public static function make(Collection $data): array
    {
        $results = [];

        foreach ($data as $produksi) {
            foreach ($produksi->pegawaiPilihVeneer as $detail) {
                $pegawai = $detail->pegawai;
                if (!$pegawai) continue;

                $results[] = [
                    'kodep'      => ltrim($pegawai->kode_pegawai, '0'),
                    'nama'       => $pegawai->nama_pegawai,
                    'masuk'      => $detail->masuk ?? '-',
                    'pulang'     => $detail->pulang ?? '-',
                    'hasil'      => 'PILIH VENEER', // Label divisi
                    'ijin'       => $detail->ijin ?? '',
                    'keterangan' => $detail->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
