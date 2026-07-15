<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;

class TembelTriplekWorkerMap
{
    /**
     * Transform data Produksi Tembel Triplek ke format absensi standar.
     *
     * @param \Illuminate\Support\Collection $collection
     * @return array
     */
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            // Ambil daftar pegawai yang bekerja di produksi tembel triplek pada hari tersebut
            $daftarPegawai = $produksi->pegawaiTembeltriplek ?? [];

            foreach ($daftarPegawai as $ptt) {
                // Lewati jika data pegawai tidak ditemukan/kosong
                if (!$ptt->pegawai) {
                    continue;
                }

                $results[] = [
                    'kodep' => $ptt->pegawai->kode_pegawai ?? '-',
                    'nama' => $ptt->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    // Format waktu masuk & pulang ke format H:i:s
                    'masuk' => $ptt->jam_masuk ? Carbon::parse($ptt->jam_masuk)->format('H:i:s') : '',
                    'pulang' => $ptt->jam_pulang ? Carbon::parse($ptt->jam_pulang)->format('H:i:s') : '',
                    'hasil' => 'TEMBEL TRIPLEK', // Nama divisi/hasil produksi yang akan digabungkan
                    'ijin' => $ptt->ijin ?? '',
                    'keterangan' => $ptt->keterangan ?? '',
                ];
            }
        }

        return $results;
    }
}