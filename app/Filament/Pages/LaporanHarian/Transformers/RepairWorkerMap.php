<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class RepairWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {

            // Tempat penampungan untuk grouping per meja per modal
            $groupMeja = [];

            // 1. Loop Modal (Ukuran Kayu yang dikerjakan)
            foreach ($produksi->modalRepairs as $modal) {

                // --- A. KONSTRUKSI LABEL & UKURAN ---
                $ukuranModel = $modal->ukuran;
                $jenisKayuModel = $modal->jenisKayu;
                $kw = $modal->kw ?? $modal->kualitas ?? 1;

                $labelPekerjaan = 'REPAIR';
                if ($ukuranModel) {
                    $labelPekerjaan .= ' ' . $ukuranModel->panjang . 'x' . $ukuranModel->lebar;
                }

                $kodeUkuran = 'REPAIR-NOT-FOUND';
                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'REPAIR' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kw .
                        strtoupper($jenisKayuModel->kode_kayu);
                }

                // --- B. CARI TARGET (LEVEL 1, 2, 3 TIDAK DIUBAH SAMA SEKALI) ---
                $targetModel = null;
                $levelFound = 0;

                $targetLv1 = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin)
                    ->first();

                if ($targetLv1) {
                    $targetModel = $targetLv1;
                    $levelFound = 1;
                } else {
                    $targetLv2 = Target::where('kode_ukuran', $kodeUkuran)->first();
                    if ($targetLv2) {
                        $targetModel = $targetLv2;
                        $levelFound = 2;
                    } else {
                        $targetLv3 = Target::where([
                            'id_mesin' => $produksi->id_mesin,
                            'id_ukuran' => $modal->id_ukuran,
                            'id_jenis_kayu' => $modal->id_jenis_kayu,
                        ])->first();

                        if ($targetLv3) {
                            $targetModel = $targetLv3;
                            $levelFound = 3;
                        }
                    }
                }

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                // --- C. LOOP PEGAWAI UNTUK GROUPING MEJA ---
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = 0;
                    if ($rp->rencanaRepairs) {
                        $hasilIndividu = $rp->rencanaRepairs
                            ->where('id_modal_repair', $modal->id)
                            ->flatMap->hasilRepairs
                            ->sum('jumlah');
                    }

                    if ($hasilIndividu <= 0) continue;

                    $nomorMeja = $rp->nomor_meja ?? '-';
                    $keyMeja = $nomorMeja . '|' . $modal->id;

                    if (!isset($groupMeja[$keyMeja])) {
                        $groupMeja[$keyMeja] = [
                            'target' => $targetWajib,
                            'potongan_per_lembar' => $potonganPerLembar,
                            'total_hasil_meja' => 0,
                            'pekerja' => [],
                            'label' => $labelPekerjaan
                        ];
                    }

                    $groupMeja[$keyMeja]['total_hasil_meja'] += $hasilIndividu;
                    $groupMeja[$keyMeja]['pekerja'][] = [
                        'rp' => $rp,
                        'hasil_ind' => $hasilIndividu
                    ];
                }
            }

            // --- D. HITUNG POTONGAN PER MEJA & DISTRIBUSI ---
            $pegawaiFinal = [];

            foreach ($groupMeja as $meja) {
                $selisih = $meja['total_hasil_meja'] - $meja['target'];
                $potonganPerOrang = 0;

                if ($selisih < 0 && $meja['target'] > 0) {
                    $totalDendaMeja = abs($selisih) * $meja['potongan_per_lembar'];
                    $jumlahPekerja = count($meja['pekerja']);

                    if ($jumlahPekerja > 0) {
                        $rawPotongan = $totalDendaMeja / $jumlahPekerja;

                        // Pembulatan sesuai logika roundToNearest500 Anda
                        $base = floor($rawPotongan / 1000) * 1000;
                        $rest = $rawPotongan - $base;
                        if ($rest < 300) $potonganPerOrang = (int) $base;
                        elseif ($rest < 800) $potonganPerOrang = (int) ($base + 500);
                        else $potonganPerOrang = (int) ($base + 1000);
                    }
                }

                foreach ($meja['pekerja'] as $pData) {
                    $rp = $pData['rp'];
                    $kodep = $rp->pegawai->kode_pegawai;

                    if (!isset($pegawaiFinal[$kodep])) {
                        $pegawaiFinal[$kodep] = [
                            'kodep' => $kodep,
                            'nama' => $rp->pegawai->nama_pegawai,
                            'masuk' => $rp->jam_masuk ? Carbon::parse($rp->jam_masuk)->format('H:i:s') : '',
                            'pulang' => $rp->jam_pulang ? Carbon::parse($rp->jam_pulang)->format('H:i:s') : '',
                            'hasil_raw' => ["{$meja['label']} ({$pData['hasil_ind']})"],
                            'potongan_targ' => ($rp->potongan ?? $potonganPerOrang),
                            'ijin' => $rp->ijin ?? '',
                            'keterangan' => $rp->keterangan ?? '',
                        ];
                    } else {
                        $pegawaiFinal[$kodep]['hasil_raw'][] = "REPAIR";
                        $pegawaiFinal[$kodep]['potongan_targ'] += ($rp->potongan ?? $potonganPerOrang);
                    }
                }
            }

            // --- E. MASUKKAN KE HASIL AKHIR ---
            foreach ($pegawaiFinal as $row) {
                $row['hasil'] = implode(', ', $row['hasil_raw']);
                unset($row['hasil_raw']);
                $results[] = $row;
            }
        }

        return $results;
    }
}
