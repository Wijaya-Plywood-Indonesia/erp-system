<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class PotAfalanJoinWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            $daftarHasil = $produksi->hasilPotAfJoint ?? [];
            $daftarPegawai = $produksi->pegawaiPotAfJoint ?? [];
            $jumlahPekerja = count($daftarPegawai);

            foreach ($daftarHasil as $hasil) {
                $ukuranModel = $hasil->ukuran;
                if (!$ukuranModel) continue;

                $kodeUkuran = 'POT AFALAN JOINT' . $ukuranModel->panjang . $ukuranModel->lebar;

                $targetModel = Target::where('kode_ukuran', $kodeUkuran)->first();
                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (float) ($targetModel->potongan ?? 0);

                foreach ($daftarPegawai as $pj) {
                    if (!$pj->pegawai) continue;

                    $hasilGrup = collect($daftarHasil)
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->sum('jumlah');

                    $selisih = $hasilGrup - $targetWajib;
                    $potonganPerOrang = 0;

                    if ($targetWajib > 0 && $selisih < 0 && $potonganPerLembar > 0) {
                        $totalDendaMeja = abs($selisih) * $potonganPerLembar;

                        if ($jumlahPekerja > 0) {
                            $rawPot = $totalDendaMeja / $jumlahPekerja;
                            $potonganPerOrang = self::pembulatanTigaTingkat($rawPot);
                        }
                    }

                    $results[] = [
                        'kodep' => $pj->pegawai->kode_pegawai ?? '-',
                        'nama' => $pj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i:s') : '',
                        'pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i:s') : '',
                        'hasil' => 'POT AFALAN',
                        'ijin' => $pj->ijin ?? '',
                        'potongan_targ' => (int) ($pj->potongan ?? $potonganPerOrang),
                        'keterangan' => $pj->ket ?? '',
                    ];
                }
            }
        }
        return $results;
    }

    private static function pembulatanTigaTingkat($value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) return (int) ($ribuan * 1000);
        if ($ratusan < 800) return (int) ($ribuan * 1000 + 500);
        return (int) (($ribuan + 1) * 1000);
    }
}
