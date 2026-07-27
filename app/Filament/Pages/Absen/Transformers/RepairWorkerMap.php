<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use App\Models\Target;

class RepairWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {

            $groupMeja = [];

            // 1. Loop langsung ke DetailHasilRepair
            foreach ($produksi->detailHasilRepairs as $detail) {

                $jumlahHasil = (int) $detail->jumlah;
                if ($jumlahHasil <= 0) continue;

                // --- A. KONSTRUKSI LABEL & KODE UKURAN ---
                $ukuranModel = $detail->ukuran;
                $jenisKayuModel = $detail->modalRepair?->jenisKayu ?? $detail->jenisKayu;
                $kw = $detail->kw ?? 1;

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
                        strtoupper($jenisKayuModel->kode_kayu ?? '');
                }

                // --- B. CARI TARGET ---
                $targetLv1 = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin)
                    ->first();

                $targetLv2 = Target::where('kode_ukuran', $kodeUkuran)->first();

                $targetLv3 = Target::where([
                    'id_mesin' => $produksi->id_mesin,
                    'id_ukuran' => $detail->id_ukuran,
                ])->first();

                $targetModel = $targetLv1 ?? $targetLv2 ?? $targetLv3;

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                // --- C. GROUPING PER MEJA & DETAIL HASIL ---
                $nomorMeja = $detail->nomor_meja ?? '-';
                $keyMeja = $nomorMeja . '|' . $detail->id;

                if (!isset($groupMeja[$keyMeja])) {
                    $groupMeja[$keyMeja] = [
                        'target' => $targetWajib,
                        'potongan_per_lembar' => $potonganPerLembar,
                        'total_hasil_meja' => $jumlahHasil,
                        'pekerja' => [],
                        'label' => $labelPekerjaan
                    ];
                }

                // Ambil semua pegawai yang terikat di DetailHasilRepair ini
                foreach ($detail->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $groupMeja[$keyMeja]['pekerja'][] = [
                        'rp' => $rp,
                        'hasil_ind' => $jumlahHasil
                    ];
                }
            }

            // --- D. HITUNG POTONGAN PER MEJA & DISTRIBUSI KEPADA PEKERJA ---
            $pegawaiFinal = [];

            foreach ($groupMeja as $meja) {
                $selisih = $meja['total_hasil_meja'] - $meja['target'];
                $potonganPerOrang = 0;

                if ($selisih < 0 && $meja['target'] > 0) {
                    $totalDendaMeja = abs($selisih) * $meja['potongan_per_lembar'];
                    $jumlahPekerja = count($meja['pekerja']);

                    if ($jumlahPekerja > 0) {
                        $rawPotongan = $totalDendaMeja / $jumlahPekerja;

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

            // --- E. FORMAT HASIL AKHIR ---
            foreach ($pegawaiFinal as $row) {
                $row['hasil'] = implode(', ', $row['hasil_raw']);
                unset($row['hasil_raw']);
                $results[] = $row;
            }
        }

        return $results;
    }
}
