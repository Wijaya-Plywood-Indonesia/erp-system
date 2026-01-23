<?php

namespace App\Filament\Pages\LaporanJoin\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class JoinDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            foreach ($produksi->modalJoint as $modal) {
                $ukuranModel = $modal->ukuran;
                $jenisKayuModel = $modal->jenisKayu;
                $kw = $modal->kw ?? '1';

                // 1. Build Kode Ukuran
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'JOINT' . $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kw .
                        strtolower($jenisKayuModel->kode_kayu ?? 'jnt');
                } else {
                    $kodeUkuran = 'JOINT-NOT-FOUND';
                }

                // 2. Ambil Target & Nilai Potongan Per Lembar
                $targetModel = Target::where('kode_ukuran', $kodeUkuran)->first();
                if (!$targetModel) {
                    $targetModel = Target::where(['id_ukuran' => $ukuranModel->id ?? 0])->first();
                }

                $targetHarian = (int) ($targetModel->target ?? 0);
                // Ini adalah nilai rupiah atau poin potongan per lembar dari tabel target
                $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

                Log::info("=== LOGIKA POTONGAN HASIL: {$kodeUkuran} ===");
                Log::info("Target: {$targetHarian}, Nilai Potongan/Lembar: {$nilaiPotonganPerLembar}");

                foreach ($produksi->pegawaiJoint as $pj) {
                    if (!$pj->pegawai) continue;

                    $nomorMeja = $pj->tugas ?? $pj->nomor_meja ?? '-';
                    $key = $nomorMeja . '|' . $kodeUkuran;

                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'nomor_meja' => $nomorMeja,
                            'kode_ukuran' => $kodeUkuran,
                            'ukuran' => $ukuranModel->nama_ukuran ?? '-',
                            'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                            'kw' => $kw,
                            'pekerja' => [],
                            'hasil' => 0,
                            'target' => $targetHarian,
                            'selisih' => 0,
                            'tanggal' => $tanggal,
                        ];
                    }

                    // 3. Hitung Hasil & Selisih
                    $hasilGrup = $produksi->hasilJoint->where('id_ukuran', $modal->id_ukuran)->sum('jumlah');
                    $result[$key]['hasil'] = $hasilGrup;

                    $kekurangan = $targetHarian - $hasilGrup;
                    $potTargetIndividu = 0;

                    // 4. Logika: Jika hasil kurang dari target, hitung potongan
                    if ($kekurangan > 0 && $nilaiPotonganPerLembar > 0) {
                        // Rumus: Kekurangan x Nilai Potongan Per Lembar
                        $rawPotongan = $kekurangan * $nilaiPotonganPerLembar;

                        // Gunakan pembulatan 500 terdekat
                        $potTargetIndividu = self::roundToNearest500($rawPotongan);

                        Log::info("Pegawai: {$pj->pegawai->nama_pegawai} | Kurang: {$kekurangan} pcs | Potongan: {$potTargetIndividu}");
                    }

                    $result[$key]['pekerja'][] = [
                        'id' => $pj->pegawai->kode_pegawai ?? '-',
                        'nama' => $pj->pegawai->nama_pegawai ?? '-',
                        'jam_masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'ijin' => $pj->ijin ?? '-',
                        'keterangan' => $pj->ket ?? '-',
                        'hasil' => $hasilGrup,
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
