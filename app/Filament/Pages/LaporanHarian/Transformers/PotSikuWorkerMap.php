<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PotSikuWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {

            // Kita loop pegawainya dulu (Primary Loop) agar satu orang satu baris
            foreach ($produksi->pegawaiPotSiku as $pp) {
                if (!$pp->pegawai) continue;

                $daftarPekerjaan = [];

                // Ambil semua detail barang yang dikerjakan oleh pegawai ini
                // Relasi: Produksi -> PegawaiPotSiku -> detailBarangDikerjakanPotSiku (melalui ID Pegawai Pot Siku)
                $details = $produksi->detailBarangDikerjakanPotSiku
                    ->where('id_pegawai_pot_siku', $pp->id);

                foreach ($details as $detail) {
                    $namaUkuran = $detail->ukuran->nama_ukuran ?? '-';
                    $namaKayu = $detail->jenisKayu->nama_kayu ?? '-';
                    $tinggi = $detail->tinggi ?? 0;

                    // Format Hasil sesuai request: id_ukuran - id_jenis_kayu - (tinggi)
                    // Disini saya gunakan NAMA agar manusiawi, jika ingin ID tinggal ganti ke ->id
                    $daftarPekerjaan[] = "{$namaUkuran} - {$namaKayu} - ({$tinggi})";
                }

                // Jika mengerjakan banyak barang, gabungkan dengan koma
                $labelHasil = empty($daftarPekerjaan) ? 'POT SIKU' : "POT SIKU: " . implode(', ', $daftarPekerjaan);

                // Format Waktu HH:mm:ss
                $jamMasuk = $pp->masuk ? Carbon::parse($pp->masuk)->format('H:i:s') : '-';
                $jamPulang = $pp->pulang ? Carbon::parse($pp->pulang)->format('H:i:s') : '-';

                $results[] = [
                    'kodep' => $pp->pegawai->kode_pegawai ?? '-',
                    'nama' => $pp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $jamMasuk,
                    'pulang' => $jamPulang,
                    'hasil' => $labelHasil,
                    'ijin' => $pp->ijin ?? '',
                    'potongan_targ' => 0, // Pot Siku biasanya belum ada potongan target otomatis jika tidak diminta
                    'keterangan' => $pp->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
