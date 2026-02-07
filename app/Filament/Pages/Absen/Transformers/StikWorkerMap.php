<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class StikWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        // 1. Ambil Referensi Target STIK (Sekali saja di luar loop agar efisien)
        $targetRef = Target::where('kode_ukuran', 'STIK')->first();

        // Default value sesuai referensi Anda: 7000 target, 0 potongan
        $stdTarget = $targetRef->target ?? 7000;
        $stdPotHarga = $targetRef->potongan ?? 0;

        foreach ($collection as $item) {

            $labelDivisi = "STIK";

            // 2. Ambil Hasil Produksi
            // Sesuai referensi Anda, nilainya ada di kolom 'hasil_produksi' tabel produksi_stiks
            $totalHasil = $item->hasil_produksi ?? 0;

            // 3. Hitung Selisih & Potongan
            $selisih = $stdTarget - $totalHasil;
            $potonganPerOrang = 0;

            // Jika hasil kurang dari target (selisih positif)
            if ($selisih > 0 && $stdPotHarga > 0) {
                $jumlahPekerja = $item->detailPegawaiStik->count();

                if ($jumlahPekerja > 0) {
                    $totalPot = $selisih * $stdPotHarga;
                    $potonganRaw = $totalPot / $jumlahPekerja;

                    // --- RUMUS PEMBULATAN KHUSUS (0, 500, 1000) ---
                    // Agar format rupiah di Laporan Harian seragam dengan Rotary/Repair
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            // 4. Mapping Pegawai
            if ($item->detailPegawaiStik) {
                foreach ($item->detailPegawaiStik as $dp) {

                    // Skip jika data master pegawai hilang
                    if (!$dp->pegawai)
                        continue;

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '';

                    // Prioritas: Input Manual > Rumus
                    $potonganFinal = $dp->potongan ?? $potonganPerOrang;

                    $results[] = [
                        'kodep' => $dp->pegawai->kode_pegawai ?? '-',
                        'nama' => $dp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelDivisi,
                        'ijin' => $dp->ijin ?? '',
                        'potongan_targ' => (int) $potonganFinal,
                        'keterangan' => $dp->keterangan ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
