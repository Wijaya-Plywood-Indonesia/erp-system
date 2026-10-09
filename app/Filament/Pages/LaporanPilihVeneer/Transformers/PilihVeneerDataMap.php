<?php

namespace App\Filament\Pages\LaporanPilihVeneer\Transformers;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Services\Target\Strategies\ProporsionalStrategy;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;

class PilihVeneerDataMap
{
    // Jam istirahat yang dipotong dari jam kerja (sama dengan Join, Pot Siku, Pot Jelek)
    private const ISTIRAHAT_MULAI = '12:00';

    private const ISTIRAHAT_SELESAI = '13:00';

    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $tanggalStr = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');

            // 1. Prepare Pekerja Input DTOs
            $pekerjaInput = [];
            $totalGajiTim = 0.0;
            foreach ($produksi->pegawaiPilihVeneer as $pj) {
                if (! $pj->pegawai) {
                    continue;
                }

                $totalGajiTim += (float) ($pj->pegawai->gaji ?? 0);

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
                    $menitKerja = self::hitungMenitKerjaBersih($masukAt, $pulangAt);
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

                if ($m->id_stok_veneer_jadi && $m->stokVeneerJadi) {
                    $stok = $m->stokVeneerJadi;
                    $jkId = $stok->id_jenis_kayu;
                    $matchUkuran = \App\Models\Ukuran::where('panjang', floatval($stok->panjang))
                        ->where('lebar', floatval($stok->lebar))
                        ->where(function($q) use ($stok) {
                            $q->where('tebal', floatval($stok->tebal))
                              ->orWhereNull('tebal');
                        })
                        ->first();
                    $ukId = $matchUkuran?->id;
                }

                $kwRaw = $hasil->kw ?? '1';
                $keyH = ($ukId ?? 'null').'-'.($jkId ?? 'null').'-'.$kwRaw;
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

            // 3. Hitung capaian total (Pencapaian) lintas ukuran
            //    Target di-ADJUST ke total jam kerja tim (menit kerja bersih semua pekerja),
            //    dibulatkan karena satuannya lembar utuh.
            $totalPencapaian = 0.0;
            $totalValue = 0.0;
            $jumlahUkuranAda = 0;
            $hasTarget = false;
            $targetPerGrup = [];

            $resolver = TargetResolverFactory::make(Mesin::PilihVeneer);

            foreach ($groupedHasil as $keyH => $gh) {
                if (! $gh['id_ukuran']) {
                    continue;
                }

                $targetModel = $resolver->resolve(Mesin::PilihVeneer->value, $gh['id_ukuran'], $gh['id_jenis_kayu'], (string) $gh['kw']);
                if (! $targetModel) {
                    continue;
                }

                $hasTarget = true;
                $menitNormalTotal = $targetModel->jam * 60;
                $ratePerMenit = ($targetModel->orang > 0 && $menitNormalTotal > 0)
                    ? $targetModel->target / $menitNormalTotal
                    : 0;
                $ratePerOrgPerMenit = $targetModel->orang > 0 ? $ratePerMenit / $targetModel->orang : 0;
                $targetAdjusted = round($ratePerOrgPerMenit * $totalMenit);

                $targetPerGrup[$keyH] = [
                    'model' => $targetModel,
                    'adjusted' => $targetAdjusted,
                ];

                $totalPencapaian += $targetAdjusted > 0 ? ($gh['jumlah'] / $targetAdjusted) : 0;
                $totalValue += $targetAdjusted * (float) $targetModel->potongan;
                $jumlahUkuranAda++;
            }

            // 4. Potongan tim = kekurangan % x nilai SATU hari penuh tim
            //    (rata-rata nilai target per ukuran, sama seperti Join),
            //    lalu dibagi ke pekerja sesuai porsi jam kerjanya.
            $potonganTotalTim = 0.0;
            $potonganPerPegawai = [];
            if ($hasTarget && $jumlahUkuranAda > 0) {
                $nilaiSatuHariPenuh = $totalValue / $jumlahUkuranAda;
                $kekuranganPersen = max(0, 100 - ($totalPencapaian * 100)) / 100;
                $potonganTotalTim = $kekuranganPersen * $nilaiSatuHariPenuh;

                $proporsional = new ProporsionalStrategy;
                $potonganPerPegawai = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);
            }

            $potonganMelebihiGaji = $totalGajiTim > 0 && $potonganTotalTim > $totalGajiTim;
            $jamAktualRata = $orgAktual > 0 ? ($totalMenit / $orgAktual) / 60 : 0;

            // 5. Build BAGIAN A: DETAIL PRODUKSI PER UKURAN
            $detailProduksiList = [];
            foreach ($groupedHasil as $keyH => $gh) {
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

                $targetModel = $targetPerGrup[$keyH]['model'] ?? null;
                $targetHarian = 0;
                $targetNormal = null;
                $capaianPersen = null;
                if ($targetModel) {
                    $targetAdjusted = $targetPerGrup[$keyH]['adjusted'];
                    $targetHarian = (int) $targetAdjusted;
                    $targetNormal = (float) $targetModel->target;
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
                    'target_normal' => $targetNormal,
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
                    $menitKerja = self::hitungMenitKerjaBersih($masukAt, $pulangAt);
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
                    'jam_aktual_bersih' => round($menitKerja / 60, 2),
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
                'jumlah_pekerja' => $orgAktual,
                'jam_aktual' => $jamAktualRata,
                'potongan_total_tim' => $potonganTotalTim,
                'potongan_melebihi_gaji' => $potonganMelebihiGaji,
                'total_gaji_tim' => $totalGajiTim,
            ];
        }

        return $result;
    }

    /**
     * Menit kerja BERSIH: (pulang - masuk) dikurangi irisan dengan jam istirahat.
     * Kalau pekerja pulang sebelum istirahat / masuk sesudah istirahat, tidak ada potongan.
     */
    private static function hitungMenitKerjaBersih(Carbon $masuk, Carbon $pulang): int
    {
        if ($pulang->lessThan($masuk)) {
            $pulang = $pulang->copy()->addDay();
        }

        $totalMenit = (int) round($masuk->diffInMinutes($pulang, true));

        $istirahatMulai = Carbon::parse($masuk->format('Y-m-d').' '.self::ISTIRAHAT_MULAI);
        $istirahatSelesai = Carbon::parse($masuk->format('Y-m-d').' '.self::ISTIRAHAT_SELESAI);

        $overlapMulai = $masuk->greaterThan($istirahatMulai) ? $masuk : $istirahatMulai;
        $overlapSelesai = $pulang->lessThan($istirahatSelesai) ? $pulang : $istirahatSelesai;

        $menitIstirahatTerpotong = 0;
        if ($overlapSelesai->greaterThan($overlapMulai)) {
            $menitIstirahatTerpotong = (int) round($overlapMulai->diffInMinutes($overlapSelesai, true));
        }

        return max(0, $totalMenit - $menitIstirahatTerpotong);
    }
}