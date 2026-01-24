<?php

namespace App\Filament\Pages\LaporanPotJelek\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class PotJelekDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $jumlahPekerja = $produksi->pegawaiPotJelek->count();

            $kodeUkuranTarget = 'POT JELEK';

            $targetModel = Target::where('kode_ukuran', $kodeUkuranTarget)->first();
            $targetHarian = (int) ($targetModel->target ?? 0);
            $jamStandarTarget = (float) ($targetModel->jam ?? 0); // Ambil jam standar
            $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

            $totalHasilSemuaUkuran = $produksi->detailBarangDikerjakanPotJelek->sum('jumlah');

            foreach ($produksi->pegawaiPotJelek as $pj) {
                if (!$pj->pegawai) continue;

                $nomorMeja = $pj->nomor_meja ?? '-';
                $key = $nomorMeja . '|' . $kodeUkuranTarget;

                if (!isset($result[$key])) {
                    $result[$key] = [
                        'nomor_meja' => $nomorMeja,
                        'kode_ukuran' => $kodeUkuranTarget,
                        'pekerja' => [],
                        'hasil' => $totalHasilSemuaUkuran,
                        'target' => $targetHarian,
                        'jam_standar' => $jamStandarTarget, // INI YANG TADI KURANG
                        'selisih' => 0,
                        'tanggal' => $tanggal,
                    ];
                }

                // ... sisa logika potongan sama seperti sebelumnya ...
                $kekurangan = $targetHarian - $totalHasilSemuaUkuran;
                $potTargetIndividu = 0;

                if ($kekurangan > 0 && $targetHarian > 0 && $nilaiPotonganPerLembar > 0) {
                    $totalDendaMeja = $kekurangan * $nilaiPotonganPerLembar;
                    if ($jumlahPekerja > 0) {
                        $rawPotonganIndividu = $totalDendaMeja / $jumlahPekerja;
                        $potTargetIndividu = self::roundToNearest500($rawPotonganIndividu);
                    }
                }

                $result[$key]['pekerja'][] = [
                    'id' => $pj->pegawai->kode_pegawai ?? '-',
                    'nama' => $pj->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                    'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                    'hasil' => $totalHasilSemuaUkuran,
                    'pot_target' => $potTargetIndividu,
                    'keterangan' => $produksi->kendala ?? '-',
                ];
            }
        }

        foreach ($result as &$row) {
            $row['selisih'] = $row['hasil'] - $row['target'];
        }

        return array_values($result);
    }

    private static function roundToNearest500(float $value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) return (int) ($ribuan * 1000);
        if ($ratusan < 800) return (int) ($ribuan * 1000 + 500);
        return (int) (($ribuan + 1) * 1000);
    }
}
