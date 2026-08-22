<?php

namespace App\Filament\Pages\LaporanPotAfalanJoin\Transformers;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Models\Target;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;

class PotAfalanDataMap
{
    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $tanggalStr = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');

            // 1. Prepare Pekerja Input DTOs
            $pekerjaInput = [];
            foreach ($produksi->pegawaiPotAfJoint as $pj) {
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
            foreach ($produksi->hasilPotAfJoint as $hasil) {
                $ukId = $hasil->id_ukuran;
                $jkId = $hasil->id_jenis_kayu;
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
                if (!empty($hasil->no_palet)) {
                    $groupedHasil[$keyH]['no_palet_list'][] = $hasil->no_palet;
                }
            }

            // 3. Compute total achievement (Pencapaian) across all sizes
            $totalPencapaian = 0.0;
            $totalValue = 0.0;
            $maxGaji = 0.0;
            $hasTarget = false;

            $resolver = TargetResolverFactory::make(Mesin::PotAfalanJoint);

            foreach ($groupedHasil as $gh) {
                $targetModel = $resolver->resolve(Mesin::PotAfalanJoint->value, $gh['id_ukuran'], $gh['id_jenis_kayu']);
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

            // 4. Call HitungPotonganProduksiAction ONCE using normalized virtual target
            $hitung = null;
            if ($hasTarget) {
                $virtualTarget = new Target;
                $virtualTarget->target = 1.0;
                $virtualTarget->orang = $orgAktual;
                $virtualTarget->jam = ($totalMenit > 0 && $orgAktual > 0) ? ($totalMenit / (60 * $orgAktual)) : 7.0;
                $virtualTarget->potongan = $totalValue;
                $virtualTarget->gaji = $maxGaji;

                $action = new HitungPotonganProduksiAction;
                $hitung = $action->execute(
                    Mesin::PotAfalanJoint,
                    StrategiPembagian::Kolektif,
                    $pekerjaInput,
                    (float) $totalPencapaian,
                    null,
                    null,
                    $virtualTarget
                );
            }

            // 5. Build BAGIAN A: DETAIL PRODUKSI PER UKURAN
            $detailProduksiList = [];
            foreach ($groupedHasil as $gh) {
                $hasilModel = $gh['hasil'];
                $ukuranModel = $hasilModel->ukuran;
                $jenisKayuModel = $hasilModel->jenisKayu;

                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'POT AFALAN JOINT'.
                        $ukuranModel->panjang.
                        $ukuranModel->lebar;
                } else {
                    $kodeUkuran = 'POT-AFALAN-NOT-FOUND';
                }

                $targetModel = $resolver->resolve(Mesin::PotAfalanJoint->value, $gh['id_ukuran'], $gh['id_jenis_kayu']);
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
                $noPaletStr = !empty($noPalets) ? implode(', ', array_unique($noPalets)) : '-';

                $detailProduksiList[] = [
                    'ukuran' => $ukuranModel->nama_ukuran ?? '-',
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
            foreach ($produksi->pegawaiPotAfJoint as $pj) {
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
                $potTargetVal = $hitung ? (int) ($hitung->potonganPerPegawai[$kodep] ?? 0) : 0;

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

            $nomorMeja = $produksi->pegawaiPotAfJoint->first()?->tugas
                ?? $produksi->pegawaiPotAfJoint->first()?->nomor_meja
                ?? 'Pegawai Pot AF Joint';

            $result[] = [
                'nomor_meja' => $nomorMeja,
                'tanggal' => $tanggal,
                'detail_produksi' => $detailProduksiList,
                'rekap_pekerja' => $rekapPekerjaList,
                // Rasio pencapaian RESMI (bukan total_hasil/total_target sederhana):
                // hasil_a/target_a + hasil_b/target_b + ... — nilai inilah yang
                // menentukan apakah kena potongan (lihat langkah 3 & 4 di atas).
                // Dipakai oleh export Excel & tampilan agar konsisten dengan
                // logika potongan yang sesungguhnya.
                'pencapaian_global' => $totalPencapaian,
            ];
        }

        return $result;
    }

    private static function roundToNearest500(float $value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) {
            return (int) ($ribuan * 1000);
        }
        if ($ratusan < 800) {
            return (int) ($ribuan * 1000 + 500);
        }

        return (int) (($ribuan + 1) * 1000);
    }
}
