<?php

namespace App\Filament\Pages\LaporanSandingJoin\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class SandingJoinDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            foreach ($produksi->hasilSandingJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;
                $kwRaw = $hasil->kw ?? '';

                // Normalisasi KW ke huruf kecil untuk pengecekan
                $kwLower = strtolower($kwRaw);

                // 1. Build Kode Ukuran (Prefix SANDING JOINT)
                if ($ukuranModel && $jenisKayuModel) {
                    // Cek kondisi: Hanya afs atau afm yang dimasukkan ke kode ukuran
                    $kwSuffix = in_array($kwLower, ['afs', 'afm']) ? $kwRaw : '';

                    $kodeUkuran = 'SANDING JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kwSuffix;
                } else {
                    $kodeUkuran = 'SANDING-JOINT-NOT-FOUND';
                }

                // 2. Ambil Target
                $targetModel = Target::where('kode_ukuran', $kodeUkuran)->first();
                $targetHarian = (int) ($targetModel->target ?? 0);
                $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

                // 3. Loop Pegawai Sanding Joint
                foreach ($produksi->pegawaiSandingJoint as $pj) {
                    if (!$pj->pegawai) continue;

                    $nomorMeja = $pj->tugas ?? $pj->nomor_meja ?? '-';
                    $key = $nomorMeja . '|' . $kodeUkuran;

                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'nomor_meja' => $nomorMeja,
                            'kode_ukuran' => $kodeUkuran,
                            'ukuran' => $ukuranModel->nama_ukuran ?? '-',
                            'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                            'kw' => $kwRaw ?: '1', // Default tampilan di web tetap ada nilainya
                            'pekerja' => [],
                            'hasil' => 0,
                            'target' => $targetHarian,
                            'selisih' => 0,
                            'tanggal' => $tanggal,
                        ];
                    }

                    // Hasil per grup ukuran
                    $totalHasilGrup = $produksi->hasilSandingJoint
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $result[$key]['hasil'] = $totalHasilGrup;

                    // 4. Logika Potongan Hasil
                    $kekurangan = $targetHarian - $totalHasilGrup;
                    $potTargetIndividu = 0;

                    if ($kekurangan > 0 && $nilaiPotonganPerLembar > 0) {
                        $rawPotongan = $kekurangan * $nilaiPotonganPerLembar;
                        $potTargetIndividu = self::roundToNearest500($rawPotongan);
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
        $base = floor($value / 1000) * 1000;
        $rest = $value - $base;
        if ($rest < 300) return (int) $base;
        if ($rest < 800) return (int) ($base + 500);
        return (int) ($base + 1000);
    }
}
