<?php

namespace App\Filament\Pages\LaporanProduksi\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class ProduksiStikDataMap
{
    public static function make($collection): array
    {
        $result = [];
        $summary = [
            'total_hasil' => 0,
            'total_pekerja' => 0,
            'total_potongan' => 0,
        ];

        // Ambil target STIK
        $targetRef = Target::where('kode_ukuran', 'STIK')->first();

        $stdTarget = $targetRef->target ?? 7000;
        $stdJam = $targetRef->jam ?? 10;
        $stdPotHarga = $targetRef->potongan ?? 0;

        foreach ($collection as $produksi) {

            $tanggalFormat = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            $hasil = $produksi->hasil_produksi ?? 0;
            $selisih = $stdTarget - $hasil;

            $jumlahPekerja = $produksi->detailPegawaiStik->count();

            $potPerOrang = 0;

            if ($selisih > 0) {
                $totalPot = $selisih * $stdPotHarga;
                $potPerOrang = $jumlahPekerja > 0
                    ? $totalPot / $jumlahPekerja : 0;
            }

            $pekerja = $produksi->detailPegawaiStik->map(function ($det) use ($potPerOrang) {
                return [
                    'id' => $det->pegawai->kode_pegawai ?? '-',
                    'nama' => $det->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $det->jam_masuk ? Carbon::parse($det->jam_masuk)->format('H:i') : '-',
                    'jam_pulang' => $det->jam_pulang ? Carbon::parse($det->jam_pulang)->format('H:i') : '-',
                    'ijin' => $det->ijin ?? '-',
                    'pot_target' => $potPerOrang > 0 ? round($potPerOrang) : '-',
                    'keterangan' => $det->keterangan ?? '-',
                ];
            })->toArray();

            // Summary
            $summary['total_hasil'] += $hasil;
            $summary['total_pekerja'] += $jumlahPekerja;
            $summary['total_potongan'] += max(0, $selisih * $stdPotHarga);

            $result[] = [
                'tanggal' => $tanggalFormat,
                'kode_ukuran' => 'STIK',
                'pekerja' => $pekerja,
                'kendala' => $produksi->kendala ?? '-',
                'target_harian' => $stdTarget,
                'hasil_harian' => $hasil,
                'selisih' => $selisih,
                'jam_kerja' => $stdJam,
                'summary' => [
                    'jumlah_pekerja' => count($pekerja),
                ],
            ];
        }

        return [$result, $summary];
    }
}
