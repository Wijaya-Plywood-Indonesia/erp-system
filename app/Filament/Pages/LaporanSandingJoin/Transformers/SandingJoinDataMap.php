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

            // Hitung jumlah pekerja di baris produksi ini (sebagai pembagi beban denda)
            $jumlahPekerja = $produksi->pegawaiSandingJoint->count();

            foreach ($produksi->hasilSandingJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;
                $kwRaw = $hasil->kw ?? '';
                $kwLower = strtolower($kwRaw);

                // 1. Build Kode Ukuran (Format: SANDING JOINT + P + L + T + Suffix)
                if ($ukuranModel && $jenisKayuModel) {
                    $kwSuffix = in_array($kwLower, ['afs', 'afm']) ? $kwRaw : '';

                    // Menjaga spasi hanya setelah kata SANDING
                    $kodeUkuran = 'SANDING JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kwSuffix;
                } else {
                    $kodeUkuran = 'SANDING-JOINT-NOT-FOUND';
                }

                // 2. Ambil Target & Jam Standar
                $targetModel = Target::where('kode_ukuran', $kodeUkuran)->first();
                $targetHarian = (int) ($targetModel->target ?? 0);
                $jamStandarTarget = (float) ($targetModel->jam ?? 0);
                $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

                // 3. Loop Pegawai
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
                            'kw' => $kwRaw ?: '1',
                            'pekerja' => [],
                            'hasil' => 0,
                            'target' => $targetHarian,
                            'jam_standar' => $jamStandarTarget,
                            'selisih' => 0,
                            'tanggal' => $tanggal,
                        ];
                    }

                    // Hasil grup (berdasarkan ukuran dan kw yang sama)
                    $totalHasilGrup = $produksi->hasilSandingJoint
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $result[$key]['hasil'] = $totalHasilGrup;

                    // 4. LOGIKA POTONGAN HASIL (DIBAGI JUMLAH PEKERJA)
                    $kekurangan = $targetHarian - $totalHasilGrup;
                    $potTargetIndividu = 0;

                    if ($kekurangan > 0 && $targetHarian > 0 && $nilaiPotonganPerLembar > 0) {
                        // Rumus: (Kekurangan x Nilai) / Jumlah Orang di Meja tersebut
                        $totalDendaMeja = $kekurangan * $nilaiPotonganPerLembar;

                        if ($jumlahPekerja > 0) {
                            $rawPotonganIndividu = $totalDendaMeja / $jumlahPekerja;
                            $potTargetIndividu = self::roundToNearest500($rawPotonganIndividu);
                        }
                    }

                    // Durasi kerja realita
                    $durasiRealita = 0;
                    if ($pj->masuk && $pj->pulang) {
                        $durasiRealita = round(Carbon::parse($pj->masuk)->diffInMinutes(Carbon::parse($pj->pulang)) / 60, 1);
                    }

                    $result[$key]['pekerja'][] = [
                        'id' => $pj->pegawai->kode_pegawai ?? '-',
                        'nama' => $pj->pegawai->nama_pegawai ?? '-',
                        'jam_masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'total_jam' => $durasiRealita,
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

        if ($ratusan < 300) {
            return (int) ($ribuan * 1000);
        } elseif ($ratusan >= 300 && $ratusan < 800) {
            return (int) (($ribuan * 1000) + 500);
        } else {
            return (int) (($ribuan + 1) * 1000);
        }
    }
}
