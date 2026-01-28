<?php

namespace App\Filament\Pages\LaporanRepairs\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class RepairDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {

            $tanggal = Carbon::parse($produksi->tanggal)->format('d/m/Y');

            foreach ($produksi->modalRepairs as $modal) {

                $ukuranModel = $modal->ukuran;
                $jenisKayuModel = $modal->jenisKayu;
                $kw = $modal->kw ?? $modal->kualitas ?? 1;

                // =============================
                // BUILD KODE UKURAN (SAMA DENGAN DB)
                // =============================
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran =
                        'REPAIR' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kw .
                        strtolower($jenisKayuModel->kode_kayu);
                } else {
                    $kodeUkuran = 'REPAIR-NOT-FOUND';
                }

                // =============================
                // AMBIL TARGET (PRIORITAS)
                // =============================
                $targetLv1 = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin)
                    ->first();

                $targetLv2 = Target::where('kode_ukuran', $kodeUkuran)->first();

                $targetLv3 = Target::where([
                    'id_mesin' => $produksi->id_mesin,
                    'id_ukuran' => $modal->id_ukuran,
                    'id_jenis_kayu' => $modal->id_jenis_kayu,
                ])->first();

                $targetModel = $targetLv1 ?? $targetLv2 ?? $targetLv3;

                if (!$targetModel) {
                    Log::warning('Target repair tidak ditemukan', [
                        'kode_ukuran' => $kodeUkuran,
                        'id_mesin' => $produksi->id_mesin,
                        'id_ukuran' => $modal->id_ukuran,
                        'id_jenis_kayu' => $modal->id_jenis_kayu,
                    ]);
                }

                $targetHarian = (int) ($targetModel->target ?? 0);
                $jamProduksi = (int) ($targetModel->jam ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);
                $jumlahOrangTarget = (int) ($targetModel->orang ?? 1);

                // =============================
                // LOOP PEKERJA → KELOMPOK PER MEJA
                // =============================
                foreach ($produksi->rencanaPegawais as $rp) {

                    if (!$rp->pegawai)
                        continue;

                    // --- FILTER PERBAIKAN: CEK HASIL REPAIR DULU ---
                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    // Jika tidak ada hasil produksi, jangan masukkan ke laporan
                    if ($hasilIndividu <= 0) {
                        continue;
                    }

                    $nomorMeja = $rp->nomor_meja ?? '-';
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
                            'jam_kerja' => $jamProduksi,
                            'jumlah_orang_target' => $jumlahOrangTarget,
                            'selisih' => 0,
                            'tanggal' => $tanggal,
                            'potongan_per_lembar' => $potonganPerLembar,
                        ];
                    }

                    $result[$key]['hasil'] += $hasilIndividu;

                    $result[$key]['pekerja'][] = [
                        'id' => $rp->pegawai->kode_pegawai ?? '-',
                        'nama' => $rp->pegawai->nama_pegawai ?? '-',
                        'jam_masuk' => $rp->jam_masuk
                            ? Carbon::parse($rp->jam_masuk)->format('H:i')
                            : '-',
                        'jam_pulang' => $rp->jam_pulang
                            ? Carbon::parse($rp->jam_pulang)->format('H:i')
                            : '-',
                        'ijin' => $rp->ijin ?? '-',
                        'keterangan' => $rp->keterangan ?? '-',
                        'nomor_meja' => $nomorMeja,
                        'hasil' => $hasilIndividu,
                        'pot_target' => 0,
                    ];
                }
            }
        }

        // =============================
        // HITUNG SELISIH & POTONGAN PER ORANG
        // =============================
        foreach ($result as &$row) {
            $row['selisih'] = $row['hasil'] - $row['target'];

            if ($row['selisih'] < 0 && $row['potongan_per_lembar'] > 0) {
                $totalDendaMeja = abs($row['selisih']) * $row['potongan_per_lembar'];
                $jumlahPekerja = count($row['pekerja']);

                if ($jumlahPekerja > 0) {
                    $rawPotongan = $totalDendaMeja / $jumlahPekerja;
                    $potonganFinal = self::roundToNearest500($rawPotongan);

                    foreach ($row['pekerja'] as &$p) {
                        $p['pot_target'] = $potonganFinal;
                    }
                }
            }
        }

        return array_values($result);
    }

    private static function roundToNearest500(float $value): int
    {
        $base = floor($value / 1000) * 1000;
        $rest = $value - $base;

        if ($rest < 300)
            return (int) $base;
        if ($rest < 800)
            return (int) ($base + 500);

        return (int) ($base + 1000);
    }
}
