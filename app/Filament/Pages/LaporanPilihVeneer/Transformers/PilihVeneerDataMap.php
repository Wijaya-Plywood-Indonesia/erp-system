<?php

namespace App\Filament\Pages\LaporanPilihVeneer\Transformers;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Services\Target\Strategies\ProporsionalStrategy;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;

class PilihVeneerDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $tanggalStr = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');

            // 1. Prepare Pekerja Input DTOs
            $pekerjaInput = [];
            foreach ($produksi->pegawaiPilihVeneer as $pj) {
                if (! $pj->pegawai) {
                    continue;
                }

                $masukAt = null;
                $pulangAt = null;
                if (! empty($pj->masuk) && ! empty($pj->pulang)) {
                    $masukAt = Carbon::parse($tanggalStr.' '.$pj->masuk);
                    $pulangAt = Carbon::parse($tanggalStr.' '.$pj->pulang);
                    if ($pulangAt->lessThan($masukAt)) {
                        $pulangAt->addDay();
                    }
                }
                $menitKerja = 0;
                if ($masukAt && $pulangAt) {
                    $menitKerja = max(0, $masukAt->diffInMinutes($pulangAt));
                }

                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: $pj->pegawai->kode_pegawai ?? '-',
                    menitKerja: (float) $menitKerja
                );
            }

            $totalMenit = array_sum(array_map(fn ($p) => $p->menitKerja, $pekerjaInput));
            $orgAktual = count($pekerjaInput);

            // 2. Group Hasil by Size & Jenis Kayu & KW to handle duplicates/summing
            $groupedHasil = [];
            foreach ($produksi->hasilPilihVeneer as $hasil) {
                $m = $hasil->modalPilihVeneer;
                if (! $m) {
                    continue;
                }
                $ukId = $m->id_ukuran;
                $jkId = $m->id_jenis_kayu;
                $kwRaw = $hasil->kw ?? '1';
                $keyH = $ukId.'-'.$jkId.'-'.$kwRaw;
                if (! isset($groupedHasil[$keyH])) {
                    $groupedHasil[$keyH] = [
                        'id_ukuran' => $ukId,
                        'id_jenis_kayu' => $jkId,
                        'kw' => $kwRaw,
                        'hasil' => $hasil,
                        'jumlah' => 0,
                        'no_palet_list' => [],
                    ];
                }
                $groupedHasil[$keyH]['jumlah'] += $hasil->jumlah;
                if (! empty($hasil->no_palet)) {
                    $groupedHasil[$keyH]['no_palet_list'][] = $hasil->no_palet;
                }
            }

            // 3. Compute total achievement (Pencapaian) across all sizes
            $totalPencapaian = 0.0;
            $totalValue = 0.0;
            $maxGaji = 0.0;
            $hasTarget = false;

            $resolver = TargetResolverFactory::make(Mesin::PilihVeneer);

            foreach ($groupedHasil as $gh) {
                $targetModel = $resolver->resolve(Mesin::PilihVeneer->value, $gh['id_ukuran'], $gh['id_jenis_kayu'], (string) $gh['kw']);
                if ($targetModel) {
                    $hasTarget = true;
                    // Calculate targetAdjusted for this size
                    $menitNormalTotal = $targetModel->jam * 60;
                    $ratePerMenit = ($targetModel->orang > 0 && $menitNormalTotal > 0)
                        ? $targetModel->target / $menitNormalTotal
                        : 0;
                    $ratePerOrgPerMenit = $targetModel->orang > 0 ? $ratePerMenit / $targetModel->orang : 0;
                    $targetAdjusted = $ratePerOrgPerMenit * $totalMenit;

                    $ghPencapaian = $targetAdjusted > 0 ? ($gh['jumlah'] / $targetAdjusted) : 0;
                    $totalPencapaian += $ghPencapaian;

                    $totalValue += $targetAdjusted * (float) $targetModel->potongan;
                    $maxGaji = max($maxGaji, (float) ($targetModel->gaji ?? 0));
                }
            }

            // 4. Calculate total team deduction and share it proportionally
            $potonganTotalTim = 0.0;
            $potonganPerPegawai = [];
            if ($hasTarget) {
                $kekuranganPersen = max(0, 100 - ($totalPencapaian * 100)) / 100;
                $potonganTotalTim = $kekuranganPersen * $totalValue;

                // Rounding denda total to nearest 500 or round each worker's potongan?
                // Let's use ProporsionalStrategy:
                $proporsional = new ProporsionalStrategy;
                $potonganPerPegawai = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);
            }

            // 5. Build BAGIAN A: DETAIL PRODUKSI PER UKURAN
            $detailProduksiList = [];
            foreach ($groupedHasil as $gh) {
                $hasilModel = $gh['hasil'];
                $m = $hasilModel->modalPilihVeneer;

                // StokVeneerJadi TIDAK punya relasi ukuran() — ukuran disimpan
                // langsung sebagai kolom panjang/lebar/tebal di tabel
                // stok_veneer_jadi itu sendiri. Pola ini disamakan dengan
                // HasilPilihVeneersTable yang sudah terbukti jalan benar.
                $ukuranModel = $m?->ukuran ?? null;
                $stokVeneer = $m?->stokVeneerJadi ?? null;

                $jenisKayuModel = $m?->jenisKayu
                    ?? $stokVeneer?->jenisKayu
                    ?? null;

                if ($ukuranModel && $jenisKayuModel) {
                    $panjang = $ukuranModel->panjang;
                    $lebar = $ukuranModel->lebar;
                    $tebal = $ukuranModel->tebal ?? null;
                    $kodeUkuran = 'PILIH VENEER'.$panjang.$lebar;
                } elseif ($stokVeneer && $jenisKayuModel) {
                    $panjang = floatval($stokVeneer->panjang);
                    $lebar = floatval($stokVeneer->lebar);
                    $tebal = floatval($stokVeneer->tebal);
                    $kodeUkuran = 'PILIH VENEER'.$panjang.$lebar;
                } else {
                    $panjang = $lebar = $tebal = null;
                    $kodeUkuran = 'PILIH-VENEER-NOT-FOUND';
                }

                $namaUkuran = $panjang !== null
                    ? "{$panjang} x {$lebar}".($tebal !== null ? " x {$tebal}" : '')
                    : '-';

                $targetModel = $resolver->resolve(Mesin::PilihVeneer->value, $gh['id_ukuran'], $gh['id_jenis_kayu'], (string) $gh['kw']);
                $targetHarian = 0;
                $capaianPersen = null;
                if ($targetModel) {
                    $menitNormalTotal = $targetModel->jam * 60;
                    $ratePerMenit = ($targetModel->orang > 0 && $menitNormalTotal > 0)
                        ? $targetModel->target / $menitNormalTotal
                        : 0;
                    $ratePerOrgPerMenit = $targetModel->orang > 0 ? $ratePerMenit / $targetModel->orang : 0;
                    $targetAdjusted = $ratePerOrgPerMenit * $totalMenit;
                    $targetHarian = (int) $targetAdjusted;
                    $capaianPersen = $targetAdjusted > 0 ? ($gh['jumlah'] / $targetAdjusted) * 100 : 0;
                }

                $noPalets = $gh['no_palet_list'] ?? [];
                $noPaletStr = ! empty($noPalets) ? implode(', ', array_unique($noPalets)) : '-';

                $detailProduksiList[] = [
                    'ukuran' => $namaUkuran,
                    'kode_ukuran' => $kodeUkuran,
                    'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                    'kw' => $gh['kw'],
                    'no_palet_list' => $noPaletStr,
                    'target' => $targetHarian,
                    'hasil' => $gh['jumlah'],
                    'selisih' => $gh['jumlah'] - $targetHarian,
                    'capaian_persen' => $capaianPersen,
                    'has_target' => $targetModel !== null,
                ];
            }

            // 6. Build BAGIAN B: REKAP POTONGAN HARIAN
            $rekapPekerjaList = [];
            foreach ($produksi->pegawaiPilihVeneer as $pj) {
                if (! $pj->pegawai) {
                    continue;
                }

                $masukAt = null;
                $pulangAt = null;
                if (! empty($pj->masuk) && ! empty($pj->pulang)) {
                    $masukAt = Carbon::parse($tanggalStr.' '.$pj->masuk);
                    $pulangAt = Carbon::parse($tanggalStr.' '.$pj->pulang);
                    if ($pulangAt->lessThan($masukAt)) {
                        $pulangAt->addDay();
                    }
                }
                $menitKerja = 0;
                if ($masukAt && $pulangAt) {
                    $menitKerja = max(0, $masukAt->diffInMinutes($pulangAt));
                }

                $jamKerjaVal = round($menitKerja / 60, 1);
                $kodep = $pj->pegawai->kode_pegawai ?? '-';
                $potTargetVal = (int) ($potonganPerPegawai[$kodep] ?? $potonganPerPegawai[ltrim($kodep, '0')] ?? 0);

                $rekapPekerjaList[] = [
                    'id' => $kodep,
                    'nama' => $pj->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                    'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                    'jam_kerja' => $jamKerjaVal.' jam',
                    'ijin' => $pj->ijin ?? '-',
                    'pencapaian' => $totalPencapaian * 100, // percentage format
                    'kekurangan' => max(0, 1.0 - $totalPencapaian) * 100, // percentage format
                    'pot_target' => $potTargetVal,
                    'keterangan' => $pj->ket ?? '-',
                ];
            }

            $nomorMeja = 'PILIH VENEER';

            $result[] = [
                'nomor_meja' => $nomorMeja,
                'tanggal' => $tanggal,
                'detail_produksi' => $detailProduksiList,
                'rekap_pekerja' => $rekapPekerjaList,
                'pencapaian_global' => $totalPencapaian,
            ];
        }

        return $result;
    }
}
