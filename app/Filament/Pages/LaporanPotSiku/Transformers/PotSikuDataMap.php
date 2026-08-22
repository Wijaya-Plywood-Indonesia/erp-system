<?php

namespace App\Filament\Pages\LaporanPotSiku\Transformers;

use App\Models\ProduksiPotSiku;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PotSikuDataMap
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
     * Tiap baris Target Pot Siku dirancang basis "1 ORANG kerja jam NORMAL
     * penuh (9 jam) khusus untuk 1 ukuran itu" (org=1 semua baris).
     *
     * DUA penyesuaian dipakai bareng di sini:
     *
     * 1) ADJUSTED ke jam aktual individu — kalau jam kerja aktual pegawai
     *    (masuk-pulang) beda dari jam normal target, target ikut menyusut/
     *    membesar proporsional. Sama untuk SEMUA ukuran yang dia kerjakan
     *    hari itu (1 orang cuma punya 1 rentang jam kerja). Jam istirahat
     *    HANYA dipotong kalau benar-benar beririsan dengan rentang
     *    masuk-pulang (lihat hitungMenitKerjaBersih).
     *
     * 2) JUMLAH PERSEN GLOBAL — karena 1 pegawai bisa kerja beberapa
     *    ukuran dalam 1 hari (jam kerjanya kebagi ke semua ukuran itu,
     *    sama seperti kasus Join), capaian tiap ukuran dijumlah (bukan
     *    dirata-rata) jadi capaian global per orang. >=100% -> gak
     *    dipotong walau tiap ukuran individually di bawah 100%.
     *
     * @return array{tanggal: string, kendala: string, validasi: ?array, pekerja: array}
     */
    public static function make(ProduksiPotSiku $produksi): array
    {
        $action = new HitungPotonganProduksiAction();

        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
        $details = $produksi->detailBarangDikerjakanPotSiku ?? collect();

        $porPegawai = $details->groupBy('id_pegawai_pot_siku');

        // Cache rate/target per id_ukuran supaya gak query DB berulang
        // untuk ukuran yang sama dipakai banyak pegawai.
        $targetCache = [];

        $resolveTarget = function (?int $idUkuran) use ($action, &$targetCache) {
            if (!$idUkuran) {
                return null;
            }
            if (!array_key_exists($idUkuran, $targetCache)) {
                // idJenisKayu sengaja null -> resolver skip filter jenis
                // kayu (baris Target Pot Siku tidak dibedakan per jenis
                // kayu, cuma per ukuran).
                $targetCache[$idUkuran] = $action->resolveTargetDanRate(
                    Mesin::PotSiku,
                    $idUkuran,
                    null
                );
            }
            return $targetCache[$idUkuran];
        };

        $pekerjaOutput = [];

        foreach ($porPegawai as $idPegawaiPotSiku => $rows) {
            $pps = $rows->first()->pegawaiPotSiku;

            /* ============================================================
             * 0. JAM AKTUAL INDIVIDU
             * ------------------------------------------------------------
             * Istirahat pabrik 12:00-13:00 HANYA dipotong kalau rentang
             * masuk-pulang pegawai beneran beririsan dengan jam itu (lihat
             * hitungMenitKerjaBersih). Kalau pegawai pulang sebelum jam
             * istirahat mulai (mis. masuk 06:00, pulang 11:00), tidak ada
             * potongan sama sekali.
             * ============================================================ */
            $jamAktualMenit = null;
            if ($pps?->masuk && $pps?->pulang) {
                $jamAktualMenit = self::hitungMenitKerjaBersih(
                    Carbon::parse($pps->masuk),
                    Carbon::parse($pps->pulang)
                );
            }

            /* ============================================================
             * 1. CAPAIAN PER UKURAN (target sudah ADJUSTED ke jam aktual)
             * ============================================================ */
            $rowsByUkuran = $rows->groupBy('id_ukuran');

            $sumCapaianPersen = 0;
            $sumNilaiTarget   = 0;
            $jumlahUkuranAda  = 0;
            $itemsPegawai     = [];

            foreach ($rowsByUkuran as $idUkuran => $rowsUkuran) {
                $first       = $rowsUkuran->first();
                $ukuranModel = $first->ukuran;
                $ukuranLabel = $ukuranModel
                    ? "{$ukuranModel->panjang}x{$ukuranModel->lebar}x{$ukuranModel->tebal}"
                    : ($ukuranModel->nama_ukuran ?? '-');

                $hasilUkuran = (int) $rowsUkuran->sum(fn ($r) => (int) ($r->tinggi ?? 0));

                $rateInfo = $resolveTarget($idUkuran ? (int) $idUkuran : null);

                if (!$rateInfo) {
                    Log::warning('Target Pot Siku tidak ditemukan untuk ukuran ini', [
                        'id_produksi' => $produksi->id,
                        'id_pegawai_pot_siku' => $idPegawaiPotSiku,
                        'id_ukuran' => $idUkuran,
                    ]);

                    $itemsPegawai[] = [
                        'ukuran'         => $ukuranLabel,
                        'jenis_kayu'     => $first->jenisKayu?->nama_kayu ?? $first->jenisKayu?->nama ?? '-',
                        'kw'             => $first->kw ?? '-',
                        'hasil'          => $hasilUkuran,
                        'target'         => 0,
                        'capaian_persen' => null,
                        'has_target'     => false,
                        'no_palet_list'  => $rowsUkuran->pluck('no_palet')->filter()->implode(', ') ?: '-',
                    ];
                    continue;
                }

                $target             = $rateInfo['target'];
                $ratePerOrgPerMenit = $rateInfo['ratePerOrgPerMenit'];
                $biayaPerCm         = (float) $target->potongan;

                // Fallback ke jam normal target kalau data jam aktual kosong
                // (anggap kerja penuh).
                $menitDipakai = $jamAktualMenit ?? ((float) $target->jam * 60);
                $targetUkuran = $ratePerOrgPerMenit * 1 * $menitDipakai; // orgAktual = 1 (individual)

                $capaian = $targetUkuran > 0 ? ($hasilUkuran / $targetUkuran) * 100 : 100.0;
                $nilai   = $targetUkuran * $biayaPerCm;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilai;
                $jumlahUkuranAda  += 1;

                $itemsPegawai[] = [
                    'ukuran'         => $ukuranLabel,
                    'jenis_kayu'     => $first->jenisKayu?->nama_kayu ?? $first->jenisKayu?->nama ?? '-',
                    'kw'             => $first->kw ?? '-',
                    'hasil'          => $hasilUkuran,
                    'target'         => $targetUkuran,
                    'selisih'        => $hasilUkuran - $targetUkuran,
                    'capaian_persen' => $capaian,
                    'has_target'     => true,
                    'no_palet_list'  => $rowsUkuran->pluck('no_palet')->filter()->implode(', ') ?: '-',
                ];
            }

            /* ============================================================
             * 2. CAPAIAN GLOBAL & POTONGAN
             * ============================================================ */
            $capaianGlobal = $sumCapaianPersen; // dijumlah, bukan dirata-rata (lihat README Join)

            $potonganOrang = 0;
            if ($jumlahUkuranAda > 0) {
                $nilaiSatuHariPenuh = $sumNilaiTarget / $jumlahUkuranAda;
                $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
                $potonganOrang      = round(($kekuranganPersen * $nilaiSatuHariPenuh) / 500) * 500;
            }

            $pekerjaOutput[] = [
                'id_pegawai_pot_siku'   => $idPegawaiPotSiku,
                'kode_pegawai'          => $pps?->pegawai?->kode_pegawai ?? '-',
                'nama'                  => $pps?->pegawai?->nama_pegawai ?? $pps?->pegawai?->nama ?? '-',
                'jam_masuk'             => $pps?->masuk ? Carbon::parse($pps->masuk)->format('H:i') : '-',
                'jam_pulang'            => $pps?->pulang ? Carbon::parse($pps->pulang)->format('H:i') : '-',
                'jam_aktual_bersih'     => $jamAktualMenit !== null ? round($jamAktualMenit / 60, 2) : null,
                'ijin'                  => $pps?->ijin ?? '-',
                'keterangan'            => $pps?->ket ?? '-',
                'total_hasil'           => (int) $rows->sum(fn ($r) => (int) ($r->tinggi ?? 0)),
                'capaian_global_persen' => round($capaianGlobal, 1),
                'potongan'              => $potonganOrang,
                'items'                 => $itemsPegawai,
            ];
        }

        return [
            'tanggal'  => $tanggal,
            'kendala'  => $produksi->kendala ?? '-',
            'validasi' => $produksi->validasiTerakhir
                ? [
                    'status' => $produksi->validasiTerakhir->status,
                    'role'   => $produksi->validasiTerakhir->role,
                ]
                : null,
            'pekerja' => $pekerjaOutput,
        ];
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