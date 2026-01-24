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
                if (!$targetModel && $ukuranModel) {
                    $targetModel = Target::where(['id_ukuran' => $ukuranModel->id])->first();
                }

                $targetHarian = (int) ($targetModel->target ?? 0);
                $nilaiPotonganPerLembar = (float) ($targetModel->potongan ?? 0);

                // Hitung total pegawai dalam satu produksi ini untuk pembagi beban
                $jumlahPekerja = $produksi->pegawaiJoint->count();

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

                    // 3. Hitung Hasil Grup
                    $hasilGrup = $produksi->hasilJoint->where('id_ukuran', $modal->id_ukuran)->sum('jumlah');
                    $result[$key]['hasil'] = $hasilGrup;

                    $kekurangan = $targetHarian - $hasilGrup;
                    $potTargetIndividu = 0;

                    // 4. LOGIKA BARU: Potongan dibagi jumlah pekerja sebelum dibulatkan
                    if ($kekurangan > 0 && $targetHarian > 0 && $nilaiPotonganPerLembar > 0) {

                        // Total denda satu meja/grup
                        $totalDendaMeja = $kekurangan * $nilaiPotonganPerLembar;

                        if ($jumlahPekerja > 0) {
                            // Denda dibagi rata ke tiap orang
                            $potPerOrangRaw = $totalDendaMeja / $jumlahPekerja;

                            // Gunakan pembulatan 3 tingkat (0-299, 300-799, 800-999)
                            $potTargetIndividu = self::roundToNearest500($potPerOrangRaw);
                        }

                        Log::info("🧩 [JOIN MAP] Meja: {$nomorMeja} | Kurang: {$kekurangan} | Total Denda Meja: {$totalDendaMeja} | Pekerja: {$jumlahPekerja} | Final Pot: {$potTargetIndividu}");
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

    /**
     * Logic Pembulatan 3 Tingkat sesuai standar payroll Anda
     */
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
