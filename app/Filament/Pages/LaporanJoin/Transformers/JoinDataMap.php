<?php

namespace App\Filament\Pages\LaporanJoin\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Services\Target\Strategies\ProporsionalStrategy;
use Illuminate\Support\Facades\Log;

class JoinDataMap
{
    /**
     * Jam istirahat pabrik (tetap): 12:00 - 13:00.
     * Dipotong dari jam kerja HANYA jika rentang masuk-pulang pegawai
     * benar-benar beririsan dengan jam istirahat ini.
     */
    private const ISTIRAHAT_MULAI   = '12:00';
    private const ISTIRAHAT_SELESAI = '13:00';

    /**
     * Struktur hasil: 1 elemen per MEJA/KRU (bukan per meja+ukuran).
     * Tiap elemen berisi 'pekerja' (tabel atas) dan 'items' (tabel bawah).
     */
    public static function make($collection): array
    {
        $result = [];
        $action = new HitungPotonganProduksiAction();

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            /* ============================================================
             * 1. JAM AKTUAL & ORG AKTUAL — SEKALI PER PRODUKSI (SATU KRU)
             * ============================================================ */

            $totalPersonMenit = 0;
            $jumlahPekerja    = $produksi->pegawaiJoint->count();
            $pekerjaInput     = [];
            $totalGajiTim     = 0;
            $jamAktualPerOrang = [];

            foreach ($produksi->pegawaiJoint as $pj) {
                if (!$pj->pegawai) {
                    continue;
                }

                $totalGajiTim += (float) ($pj->pegawai->gaji ?? 0);

                if (!$pj->masuk || !$pj->pulang) {
                    continue;
                }

                $netMenit = self::hitungMenitKerjaBersih(
                    Carbon::parse($pj->masuk),
                    Carbon::parse($pj->pulang)
                );

                $totalPersonMenit += $netMenit;

                $idPegawai = (string) ($pj->id_pegawai ?? $pj->pegawai->id);
                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: $idPegawai,
                    menitKerja: (float) $netMenit,
                );
                $jamAktualPerOrang[$idPegawai] = round($netMenit / 60, 2);
            }

            $avgMenitPerOrang = $jumlahPekerja > 0 ? $totalPersonMenit / $jumlahPekerja : 0;
            $jamAktualRata    = $avgMenitPerOrang / 60;

            /* ============================================================
             * 2. HITUNG TARGET-ADJUSTED & CAPAIAN (%) PER UKURAN
             * ------------------------------------------------------------
             * PENTING (INI YANG SEMPAT SALAH): target tiap ukuran memang
             * dirancang basis "1 kru kerja 1 hari PENUH cuma buat 1 ukuran
             * itu" (org & jam normal sama semua). Tapi itu BUKAN alasan
             * untuk buang penyesuaian jam aktual sama sekali — yang salah
             * itu kalau jam aktual dipakai PENUH untuk SETIAP ukuran
             * (double count kalau kru kerja >1 ukuran).
             *
             * Yang BENAR: target tiap ukuran tetap di-ADJUST pakai TOTAL
             * TENAGA KERJA TIM HARI ITU (jumlahPekerja x rata-rata jam
             * aktual mereka) — dipakai SEKALI per ukuran (bukan diulang
             * penuh tiap ukuran), jadi tidak dobel-hitung. Kalau ada
             * pekerja pulang cepat, rata-rata jam tim turun, target
             * adjusted tiap ukuran ikut turun proporsional.
             *
             * Capaian tiap ukuran (hasil/targetAdjusted) tetap DIJUMLAH
             * (bukan dirata-rata) lintas ukuran, karena kru cuma punya 1
             * "jatah hari kerja" yang dibagi ke semua ukuran itu.
             * ============================================================ */

            $hasilGrouped = $produksi->hasilJoint->groupBy(function ($h) {
                return $h->id_ukuran . '|' . $h->id_jenis_kayu . '|' . $h->kw;
            });

            $itemsMeja        = [];
            $sumCapaianPersen = 0;
            $sumNilaiTarget   = 0;
            $jumlahUkuranAda  = 0;

            foreach ($hasilGrouped as $hasilRows) {
                $firstHasil     = $hasilRows->first();
                $ukuranModel    = $firstHasil->ukuran;
                $jenisKayuModel = $firstHasil->jenisKayu;
                $kw             = $firstHasil->kw ?? '1';

                if ($ukuranModel && $jenisKayuModel) {
                    $kodeUkuran = 'JOINT' . $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kw .
                        strtolower($jenisKayuModel->kode_kayu ?? 'jnt');
                } else {
                    $kodeUkuran = 'JOINT-NOT-FOUND';
                }

                $idUkuran    = $firstHasil->id_ukuran;
                $idJenisKayu = $firstHasil->id_jenis_kayu;
                $hasilGrup   = (float) $hasilRows->sum('jumlah');

                $rateInfo = ($idUkuran && $idJenisKayu)
                    ? $action->resolveTargetDanRate(Mesin::Joint, $idUkuran, $idJenisKayu)
                    : null;

                if (!$rateInfo) {
                    Log::warning('Target Join tidak ditemukan / data ukuran-jenis kayu tidak lengkap', [
                        'id_produksi'   => $produksi->id,
                        'kode_ukuran'   => $kodeUkuran,
                        'id_ukuran'     => $idUkuran,
                        'id_jenis_kayu' => $idJenisKayu,
                    ]);

                    $itemsMeja[] = [
                        'kode_ukuran'    => $kodeUkuran,
                        'ukuran'         => $ukuranModel->nama_ukuran ?? '-',
                        'jenis_kayu'     => $jenisKayuModel->nama_kayu ?? '-',
                        'kw'             => $kw,
                        'hasil'          => $hasilGrup,
                        'target'         => 0,
                        'selisih'        => $hasilGrup,
                        'capaian_persen' => null,
                        'has_target'     => false,
                    ];
                    continue;
                }

                $target             = $rateInfo['target'];
                $ratePerOrgPerMenit = $rateInfo['ratePerOrgPerMenit'];
                $biayaPerUnit       = (float) $target->potongan;

                // ADJUSTED ke total tenaga kerja tim hari itu — dipakai
                // SEKALI per ukuran (bukan penuh per ukuran), jadi tidak
                // dobel hitung meski kru kerja banyak ukuran sekaligus.
                // Dibulatkan ke bilangan bulat karena satuannya lembar utuh
                // (gak mungkin ada "433,33 lembar").
                $targetAdjusted = round($ratePerOrgPerMenit * $jumlahPekerja * $avgMenitPerOrang);

                $capaian     = $targetAdjusted > 0 ? ($hasilGrup / $targetAdjusted) * 100 : 100.0;
                $nilaiTarget = $targetAdjusted * $biayaPerUnit;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilaiTarget;
                $jumlahUkuranAda  += 1;

                $itemsMeja[] = [
                    'kode_ukuran'    => $kodeUkuran,
                    'ukuran'         => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'     => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'             => $kw,
                    'hasil'          => $hasilGrup,
                    'target'         => $targetAdjusted,
                    'target_normal'  => (float) $target->target,
                    'selisih'        => $hasilGrup - $targetAdjusted,
                    'capaian_persen' => $capaian,
                    'has_target'     => true,
                ];
            }

            /* ============================================================
             * 3. CAPAIAN GLOBAL (JUMLAH PERSEN) → POTONGAN KOLEKTIF
             * ============================================================ */

            $capaianGlobal      = $sumCapaianPersen;
            $nilaiSatuHariPenuh = $jumlahUkuranAda > 0 ? ($sumNilaiTarget / $jumlahUkuranAda) : 0;
            $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
            $potonganTotalTim   = $kekuranganPersen * $nilaiSatuHariPenuh;

            $proporsional       = new ProporsionalStrategy();
            $potonganPerPegawai = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);

            $potonganMelebihiGaji = $totalGajiTim > 0 && $potonganTotalTim > $totalGajiTim;

            /* ============================================================
             * 4. SUSUN OUTPUT PER MEJA
             * ============================================================ */

            $nomorMejaGroups = $produksi->pegawaiJoint->groupBy(fn ($pj) => $pj->tugas ?? $pj->nomor_meja ?? '-');

            foreach ($nomorMejaGroups as $nomorMeja => $pjRows) {
                $pekerjaOutput = [];

                foreach ($pjRows as $pj) {
                    if (!$pj->pegawai) {
                        continue;
                    }

                    $idPegawai = (string) ($pj->id_pegawai ?? $pj->pegawai->id);

                    $pekerjaOutput[] = [
                        'id'                => $pj->pegawai->kode_pegawai ?? '-',
                        'nama'              => $pj->pegawai->nama_pegawai ?? '-',
                        'jam_masuk'         => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang'        => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'jam_aktual_bersih' => $jamAktualPerOrang[$idPegawai] ?? null,
                        'ijin'              => $pj->ijin ?? '-',
                        'keterangan'        => $pj->ket ?? '-',
                        'pot_target'        => $potonganPerPegawai[$idPegawai] ?? 0,
                    ];
                }

                $result[] = [
                    'nomor_meja'             => $nomorMeja,
                    'tanggal'                => $tanggal,
                    'jam_aktual'             => $jamAktualRata,
                    'jumlah_pekerja'         => count($pekerjaOutput),
                    'capaian_global_persen'  => $capaianGlobal,
                    'potongan_total_tim'     => $potonganTotalTim,
                    'potongan_melebihi_gaji' => $potonganMelebihiGaji,
                    'total_gaji_tim'         => $totalGajiTim,
                    'pekerja'                => $pekerjaOutput,
                    'items'                  => $itemsMeja,
                ];
            }
        }

        return $result;
    }

    private static function hitungMenitKerjaBersih(Carbon $masuk, Carbon $pulang): int
    {
        if ($pulang->lessThan($masuk)) {
            $pulang = $pulang->copy()->addDay();
        }

        $totalMenit = $masuk->diffInMinutes($pulang);

        $istirahatMulai = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_MULAI);
        $istirahatSelesai = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_SELESAI);

        $overlapMulai   = $masuk->greaterThan($istirahatMulai) ? $masuk : $istirahatMulai;
        $overlapSelesai = $pulang->lessThan($istirahatSelesai) ? $pulang : $istirahatSelesai;

        $menitIstirahatTerpotong = 0;
        if ($overlapSelesai->greaterThan($overlapMulai)) {
            $menitIstirahatTerpotong = $overlapMulai->diffInMinutes($overlapSelesai);
        }

        return max(0, $totalMenit - $menitIstirahatTerpotong);
    }
}