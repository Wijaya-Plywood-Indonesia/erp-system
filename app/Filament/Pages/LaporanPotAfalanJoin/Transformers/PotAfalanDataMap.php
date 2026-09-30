<?php

namespace App\Filament\Pages\LaporanPotAfalanJoin\Transformers;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Services\Target\Strategies\ProporsionalStrategy;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;

class PotAfalanDataMap
{
    // Jam istirahat yang dipotong dari jam kerja (sama dengan Join, Pot Siku, Pot Jelek, Pilih Veneer)
    private const ISTIRAHAT_MULAI = '12:00';

    private const ISTIRAHAT_SELESAI = '13:00';

    // Semua target Pot Afalan Joint memakai grade AF, apapun KW yang diinput di hasil
    private const GRADE_TARGET = 'AF';

    public static function make($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $tanggalStr = Carbon::parse($produksi->tanggal_produksi)->format('Y-m-d');

            // 1. Prepare Pekerja Input DTOs
            $pekerjaInput = [];
            $totalGajiTim = 0.0;
            foreach ($produksi->pegawaiPotAfJoint as $pj) {
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

            // 2. Group Hasil per Ukuran & Jenis Kayu.
            //    KW TIDAK dipakai sebagai pembeda target (semua target = AF), jadi hasil
            //    dengan KW berbeda tapi ukuran+kayu sama DIJUMLAHKAN dalam satu grup,
            //    supaya target ukuran tsb tidak terhitung dobel.
            $groupedHasil = [];
            foreach ($produksi->hasilPotAfJoint as $hasil) {
                $ukId = $hasil->id_ukuran;
                $jkId = $hasil->id_jenis_kayu;
                $keyH = $ukId.'-'.$jkId;
                if (! isset($groupedHasil[$keyH])) {
                    $groupedHasil[$keyH] = [
                        'id_ukuran' => $ukId,
                        'id_jenis_kayu' => $jkId,
                        'kw_list' => [],
                        'hasil' => $hasil,
                        'jumlah' => 0,
                        'no_palet_list' => [],
                    ];
                }
                $groupedHasil[$keyH]['jumlah'] += $hasil->jumlah;
                if (! empty($hasil->kw)) {
                    $groupedHasil[$keyH]['kw_list'][] = strtoupper(trim((string) $hasil->kw));
                }
                if (! empty($hasil->no_palet)) {
                    $groupedHasil[$keyH]['no_palet_list'][] = $hasil->no_palet;
                }
            }

            // 3. Hitung capaian total (Pencapaian) lintas ukuran.
            //    Target di-ADJUST ke total jam kerja tim (menit kerja bersih semua pekerja),
            //    dibulatkan karena satuannya lembar utuh.
            //    Target sudah untuk 2 orang (kolom `orang` di tabel targets), jadi
            //    rate per orang = target / jam / orang.
            $totalPencapaian = 0.0;
            $totalValue = 0.0;
            $jumlahUkuranAda = 0;
            $hasTarget = false;
            $targetPerGrup = [];

            $resolver = TargetResolverFactory::make(Mesin::PotAfalanJoint);

            foreach ($groupedHasil as $keyH => $gh) {
                $targetModel = $resolver->resolve(
                    Mesin::PotAfalanJoint->value,
                    $gh['id_ukuran'],
                    $gh['id_jenis_kayu'],
                    self::GRADE_TARGET
                );

                // Fallback: target lama yang belum diisi grade
                if (! $targetModel) {
                    $targetModel = $resolver->resolve(
                        Mesin::PotAfalanJoint->value,
                        $gh['id_ukuran'],
                        $gh['id_jenis_kayu']
                    );
                }

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
            //    (rata-rata nilai target per ukuran, sama seperti Join & Pilih Veneer),
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
                $ukuranModel = $hasilModel->ukuran;
                $jenisKayuModel = $hasilModel->jenisKayu;

                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'POT AFALAN JOINT'.
                        $ukuranModel->panjang.
                        $ukuranModel->lebar;
                } else {
                    $kodeUkuran = 'POT-AFALAN-NOT-FOUND';
                }

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

                $kwList = array_unique($gh['kw_list'] ?? []);
                $kwStr = ! empty($kwList) ? implode(', ', $kwList) : self::GRADE_TARGET;

                $detailProduksiList[] = [
                    'ukuran' => $ukuranModel->nama_ukuran ?? '-',
                    'kode_ukuran' => $kodeUkuran,
                    'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                    'kw' => $kwStr,
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

            $nomorMeja = $produksi->pegawaiPotAfJoint->first()?->tugas
                ?? $produksi->pegawaiPotAfJoint->first()?->nomor_meja
                ?? 'Pegawai Pot AF Joint';

            $result[] = [
                'nomor_meja' => $nomorMeja,
                'tanggal' => $tanggal,
                'detail_produksi' => $detailProduksiList,
                'rekap_pekerja' => $rekapPekerjaList,
                // Rasio pencapaian RESMI: hasil_a/target_a + hasil_b/target_b + ...
                // Nilai inilah yang menentukan apakah kena potongan (langkah 3 & 4).
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