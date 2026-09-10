<?php

namespace App\Filament\Pages\LaporanPotJelek\Transformers;

use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PotJelekDataMap
{
    /**
     * Jam istirahat pabrik (tetap): 12:00 - 13:00.
     * Dipotong dari jam kerja HANYA jika rentang masuk-pulang pegawai
     * benar-benar beririsan dengan jam istirahat ini. Kalau pegawai
     * pulang sebelum jam istirahat mulai, atau masuk setelah jam
     * istirahat selesai, TIDAK ada potongan sama sekali.
     */
    private const ISTIRAHAT_MULAI   = '12:00';
    private const ISTIRAHAT_SELESAI = '13:00';

    /**
     * Sama polanya dengan PotSikuDataMap: tiap baris Target Pot Jelek
     * dirancang basis "1 orang kerja 9 jam PENUH khusus 1 ukuran" (org=1
     * semua baris), kode_ukuran-nya SAMA untuk semua baris ('POT JELEK'),
     * jadi harus dibedakan lewat id_ukuran (bukan string kode_ukuran).
     *
     * 1 pegawai bisa kerja beberapa ukuran dalam 1 hari -> jam kerjanya
     * kebagi ke semua ukuran itu -> dipakai rumus "jumlah persen global"
     * per individu (sama seperti Pot Siku & Join), BUKAN target flat.
     *
     * @return array{ list of produksi transformed, each: tanggal, kendala, pekerja[] }
     */
    public static function make($collection): array
    {
        $action = new HitungPotonganProduksiAction();
        $targetCache = [];

        $resolveTarget = function (?int $idUkuran) use ($action, &$targetCache) {
            if (!$idUkuran) {
                return null;
            }
            if (!array_key_exists($idUkuran, $targetCache)) {
                // idJenisKayu sengaja null -> resolver skip filter jenis kayu
                // (baris Target Pot Jelek tidak dibedakan per jenis kayu).
                $targetCache[$idUkuran] = $action->resolveTargetDanRate(
                    Mesin::PotJelek,
                    $idUkuran,
                    null
                );
            }
            return $targetCache[$idUkuran];
        };

        $hasilPerProduksi = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $details = $produksi->detailBarangDikerjakanPotJelek ?? collect();

            // Grouping per pegawai (lewat relasi PegawaiPotJelek)
            $porPegawai = $details->groupBy(function ($d) {
                return $d->PegawaiPotJelek?->id ?? $d->id_pegawai_pot_jelek ?? 'unknown';
            });

            $pekerjaOutput = [];

            foreach ($porPegawai as $idPegawaiPotJelek => $rows) {
                $pj = $rows->first()->PegawaiPotJelek;
                if (!$pj || !$pj->pegawai) {
                    continue;
                }

                /* ========================================================
                 * 0. JAM AKTUAL INDIVIDU (buat adjust target tiap ukuran)
                 * ------------------------------------------------------
                 * Target per ukuran basisnya "1 orang kerja jam NORMAL
                 * penuh" (org=1). Kalau jam kerja aktual pegawai beda dari
                 * jam normal (pulang cepat/lembur), target ikut menyusut/
                 * membesar proporsional -- sama untuk SEMUA ukuran yang dia
                 * kerjakan hari itu (karena 1 orang cuma punya 1 rentang
                 * jam kerja, dipakai buat semua barang yang dia kerjakan).
                 *
                 * Jam istirahat HANYA dipotong kalau benar-benar beririsan
                 * dengan rentang masuk-pulang (lihat hitungMenitKerjaBersih).
                 * ======================================================== */
                $jamAktualMenit = null;
                if ($pj->masuk && $pj->pulang) {
                    $jamAktualMenit = self::hitungMenitKerjaBersih(
                        Carbon::parse($pj->masuk),
                        Carbon::parse($pj->pulang)
                    );
                }

                /* ========================================================
                 * 1. CAPAIAN PER UKURAN + CAPAIAN GLOBAL (JUMLAH PERSEN)
                 * ======================================================== */
                $rowsByUkuran = $rows->groupBy('id_ukuran');

                $sumCapaianPersen = 0;
                $sumNilaiTarget   = 0;
                $jumlahUkuranAda  = 0;
                $itemsPegawai     = [];

                foreach ($rowsByUkuran as $idUkuran => $rowsUkuran) {
                    $first       = $rowsUkuran->first();
                    $ukuranModel = $first->ukuran;
                    $ukuranLabel = $ukuranModel?->nama_ukuran
                        ?? ($ukuranModel ? "{$ukuranModel->panjang}x{$ukuranModel->lebar}x{$ukuranModel->tebal}" : '-');

                    $hasilUkuran = (int) $rowsUkuran->sum(fn ($r) => (int) ($r->tinggi ?? 0));

                    $rateInfo = $resolveTarget($idUkuran ? (int) $idUkuran : null);

                    if (!$rateInfo) {
                        Log::warning('Target Pot Jelek tidak ditemukan untuk ukuran ini', [
                            'id_produksi' => $produksi->id,
                            'id_pegawai_pot_jelek' => $idPegawaiPotJelek,
                            'id_ukuran' => $idUkuran,
                        ]);

                        $itemsPegawai[] = [
                            'ukuran'         => $ukuranLabel,
                            'jenis_kayu'     => $first->jenisKayu->nama_kayu ?? $first->jenisKayu->nama ?? '-',
                            'kw'             => $first->kw ?? '-',
                            'hasil'          => $hasilUkuran,
                            'target'         => 0,
                            'capaian_persen' => null,
                            'has_target'     => false,
                            'no_palet_list'  => $rowsUkuran->pluck('no_palet')->filter()->implode(', ') ?: '-',
                        ];
                        continue;
                    }

                    $target       = $rateInfo['target'];
                    $ratePerOrgPerMenit = $rateInfo['ratePerOrgPerMenit'];
                    $biayaPerUnit = (float) $target->potongan;

                    // Kalau jam aktual gak ada datanya, fallback ke jam
                    // normal target itu sendiri (anggap kerja penuh).
                    $menitDipakai = $jamAktualMenit ?? ((float) $target->jam * 60);
                    $targetUkuran = $ratePerOrgPerMenit * 1 * $menitDipakai; // orgAktual = 1 (individual)

                    $capaian = $targetUkuran > 0 ? ($hasilUkuran / $targetUkuran) * 100 : 100.0;
                    $nilai   = $targetUkuran * $biayaPerUnit;

                    $sumCapaianPersen += $capaian;
                    $sumNilaiTarget   += $nilai;
                    $jumlahUkuranAda  += 1;

                    $itemsPegawai[] = [
                        'ukuran'         => $ukuranLabel,
                        'jenis_kayu'     => $first->jenisKayu->nama_kayu ?? $first->jenisKayu->nama ?? '-',
                        'kw'             => $first->kw ?? '-',
                        'hasil'          => $hasilUkuran,
                        'target'         => $targetUkuran,
                        'capaian_persen' => $capaian,
                        'has_target'     => true,
                        'no_palet_list'  => $rowsUkuran->pluck('no_palet')->filter()->implode(', ') ?: '-',
                    ];
                }

                $capaianGlobal = $sumCapaianPersen; // dijumlah, bukan dirata-rata

                $potonganOrang = 0;
                if ($jumlahUkuranAda > 0) {
                    $nilaiSatuHariPenuh = $sumNilaiTarget / $jumlahUkuranAda;
                    $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
                    $potonganOrang      = round(($kekuranganPersen * $nilaiSatuHariPenuh) / 500) * 500;
                }

                $pekerjaOutput[] = [
                    'kode_pegawai'          => $pj->pegawai->kode_pegawai ?? '-',
                    'nama'                  => $pj->pegawai->nama_pegawai ?? '-',
                    'jam_masuk'             => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                    'jam_pulang'            => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                    'jam_aktual_bersih'     => $jamAktualMenit !== null ? round($jamAktualMenit / 60, 2) : null,
                    'ijin'                  => $pj->ijin ?? '-',
                    'keterangan'            => $pj->keterangan ?? $produksi->kendala ?? '-',
                    'total_hasil'           => (int) $rows->sum(fn ($r) => (int) ($r->tinggi ?? 0)),
                    'capaian_global_persen' => round($capaianGlobal, 1),
                    'potongan'              => $potonganOrang,
                    'items'                 => $itemsPegawai,
                ];
            }

            $hasilPerProduksi[] = [
                'tanggal' => $tanggal,
                'kendala' => $produksi->kendala ?? '-',
                'pekerja' => $pekerjaOutput,
            ];
        }

        return $hasilPerProduksi;
    }

    /**
     * Hitung menit kerja bersih = durasi (masuk -> pulang) DIKURANGI
     * irisan waktu dengan jam istirahat pabrik (12:00-13:00).
     *
     * Kalau rentang kerja tidak menyentuh jam istirahat sama sekali
     * (misal masuk 06:00 pulang 11:00, atau masuk 14:00 pulang 20:00),
     * tidak ada potongan sama sekali. Kalau rentang kerja hanya kena
     * sebagian jam istirahat (misal pulang 12:30), yang dipotong cuma
     * irisannya (30 menit), bukan flat 1 jam.
     */
    private static function hitungMenitKerjaBersih(Carbon $masuk, Carbon $pulang): int
    {
        if ($pulang->lessThan($masuk)) {
            $pulang = $pulang->copy()->addDay();
        }

        $totalMenit = $masuk->diffInMinutes($pulang);

        $istirahatMulai = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_MULAI);
        $istirahatSelesai = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_SELESAI);

        // Irisan antara [masuk, pulang] dan [istirahatMulai, istirahatSelesai]
        $overlapMulai   = $masuk->greaterThan($istirahatMulai) ? $masuk : $istirahatMulai;
        $overlapSelesai = $pulang->lessThan($istirahatSelesai) ? $pulang : $istirahatSelesai;

        $menitIstirahatTerpotong = 0;
        if ($overlapSelesai->greaterThan($overlapMulai)) {
            $menitIstirahatTerpotong = $overlapMulai->diffInMinutes($overlapSelesai);
        }

        return max(0, $totalMenit - $menitIstirahatTerpotong);
    }
}