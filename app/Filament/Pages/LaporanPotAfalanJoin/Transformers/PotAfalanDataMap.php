<?php

namespace App\Filament\Pages\LaporanPotAfalanJoin\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class PotAfalanDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            // Hitung jumlah pekerja untuk pembagi denda
            $jumlahPekerja = $produksi->pegawaiPotAfJoint->count();

            foreach ($produksi->hasilPotAfJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;
                $kwRaw = $hasil->kw ?? '1';

                // 1. Build Kode Ukuran (Format: POT AFALAN JOINT + P + L)
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'POT AFALAN JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar;
                } else {
                    $kodeUkuran = 'POT-AFALAN-NOT-FOUND';
                }

                // 2. Ambil Target & Jam Standar
                $targetModel = Target::where('kode_ukuran', $kodeUkuran)->first();
                $targetHarian = (int) ($targetModel->target ?? 0);
                $jamStandarTarget = (float) ($targetModel->jam ?? 0);
                $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

                foreach ($produksi->pegawaiPotAfJoint as $pj) {
                    if (!$pj->pegawai) continue;

                    $nomorMeja = $pj->tugas ?? $pj->nomor_meja ?? '-';
                    $key = $nomorMeja . '|' . $kodeUkuran;

                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'nomor_meja' => $nomorMeja,
                            'kode_ukuran' => $kodeUkuran,
                            'ukuran' => $ukuranModel->nama_ukuran ?? '-',
                            'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                            'kw' => $kwRaw,
                            'pekerja' => [],
                            'hasil' => 0,
                            'target' => $targetHarian,
                            'jam_standar' => $jamStandarTarget,
                            'selisih' => 0,
                            'tanggal' => $tanggal,
                        ];
                    }

                    $totalHasilGrup = $produksi->hasilPotAfJoint
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $result[$key]['hasil'] = $totalHasilGrup;

                    // 3. Logika Potongan (DIBAGI JUMLAH PEKERJA)
                    $kekurangan = $targetHarian - $totalHasilGrup;
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
                        'ijin' => $pj->ijin ?? '-',
                        'keterangan' => $pj->ket ?? '-',
                        'hasil' => $totalHasilGrup,
                        'pot_target' => $potTargetIndividu,
                    ];
                }
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
