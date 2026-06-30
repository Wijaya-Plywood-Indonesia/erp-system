<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PotJelekWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {

            // Primary Loop: Pegawai Pot Jelek
            foreach ($produksi->pegawaiPotJelek as $pj) {
                if (!$pj->pegawai) continue;

                $daftarPekerjaan = [];

                // Ambil detail pekerjaan berdasarkan ID pegawai di tabel transaksi ini
                $details = $produksi->detailBarangDikerjakanPotJelek
                    ->where('id_pegawai_pot_jelek', $pj->id);

                foreach ($details as $detail) {
                    $namaUkuran = $detail->ukuran->nama_ukuran ?? '-';
                    $namaKayu = $detail->jenisKayu->nama_kayu ?? '-';
                    $tinggi = $detail->tinggi ?? 0;

                    // Format Hasil: id_ukuran - id_jenis_kayu - (tinggi)
                    $daftarPekerjaan[] = "{$namaUkuran} - {$namaKayu} - ({$tinggi})";
                }

                // Label Divisi: POT JELEK (Hanya muncul jika ada detail pekerjaan)
                $labelHasil = empty($daftarPekerjaan) ? 'POT JELEK' : "POT JELEK: " . implode(', ', $daftarPekerjaan);

                // Format Waktu: HH:mm:ss (Sesuai permintaan sebelumnya)
                $jamMasuk = $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i:s') : '-';
                $jamPulang = $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i:s') : '-';

                $results[] = [
                    'kodep' => $pj->pegawai->kode_pegawai ?? '-',
                    'nama' => $pj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $jamMasuk,
                    'pulang' => $jamPulang,
                    'hasil' => $labelHasil,
                    'ijin' => $pj->ijin ?? '',
                    'potongan_targ' => 0,
                    'keterangan' => $pj->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
