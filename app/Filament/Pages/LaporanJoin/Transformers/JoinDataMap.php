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
     * Jatah istirahat baku yang sudah termasuk dalam rentang jam
     * masuk-pulang (06:00-16:00 = 10 jam kotor, tapi jam kerja bersihnya
     * cuma 9 jam karena 1 jam di antaranya istirahat).
     */
    private const ISTIRAHAT_MENIT = 60;

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
            $pekerjaInput     = []; // PekerjaKerjaInput[], dipakai lagi di step 3 (ProporsionalStrategy)
            $totalGajiTim     = 0;  // buat flag "potongan melebihi gaji normal"

            foreach ($produksi->pegawaiJoint as $pj) {
                if (!$pj->pegawai) {
                    continue;
                }

                $totalGajiTim += (float) ($pj->pegawai->gaji ?? 0);

                if (!$pj->masuk || !$pj->pulang) {
                    continue;
                }

                $masuk  = Carbon::parse($pj->masuk);
                $pulang = Carbon::parse($pj->pulang);

                if ($pulang->lessThan($masuk)) {
                    $pulang->addDay();
                }

                $grossMenit = $masuk->diffInMinutes($pulang);
                $netMenit   = max(0, $grossMenit - self::ISTIRAHAT_MENIT);

                $totalPersonMenit += $netMenit;

                $idPegawai = (string) ($pj->id_pegawai ?? $pj->pegawai->id);
                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: $idPegawai,
                    menitKerja: (float) $netMenit,
                );
            }

            $avgMenitPerOrang = $jumlahPekerja > 0 ? $totalPersonMenit / $jumlahPekerja : 0;
            $jamAktualRata    = $avgMenitPerOrang / 60;

            /* ============================================================
             * 2. HITUNG TARGET-ADJUSTED & CAPAIAN (%) PER UKURAN
             * ------------------------------------------------------------
             * PENTING: target tiap ukuran dirancang dengan basis "1 kru
             * kerja 1 hari PENUH cuma buat ukuran itu doang" (makanya org &
             * jam normalnya sama semua). Kalau 1 kru ngerjain BEBERAPA
             * ukuran di hari yang sama, jam kerja mereka otomatis KEBAGI ke
             * semua ukuran itu — bukan berarti tiap ukuran dapat jam PENUH
             * sendiri-sendiri.
             *
             * Makanya capaian di-GABUNG dengan cara JUMLAH PERSEN tiap
             * ukuran (sama seperti widget HotPress temanmu), BUKAN
             * dijumlah nilai Rupiah-nya. Kalau kru cuma sempat ±10% dari
             * target tiap ukuran karena waktunya kebagi ke 10 ukuran, itu
             * WAJAR (bukan kurang kerja) — dan totalnya emang seharusnya
             * bisa nyampe ~100% kalau 1 hari kerja mereka terpakai penuh
             * secara produktif, gak peduli dibagi ke berapa ukuran.
             * ============================================================ */

            $hasilGrouped = $produksi->hasilJoint->groupBy(function ($h) {
                return $h->id_ukuran . '|' . $h->id_jenis_kayu . '|' . $h->kw;
            });

            $ukuranGroups    = []; // data mentah per ukuran, dipakai lagi di step 4
            $sumCapaianPersen = 0;  // Σ (hasil_i/target_i x 100) — INI yang dijumlah, bukan rupiah
            $sumNilaiTarget   = 0;  // buat hitung rata-rata "nilai 1 hari penuh" di step 3
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

                    $ukuranGroups[] = [
                        'kode_ukuran'     => $kodeUkuran,
                        'ukuran_nama'     => $ukuranModel->nama_ukuran ?? '-',
                        'jenis_kayu'      => $jenisKayuModel->nama_kayu ?? '-',
                        'kw'              => $kw,
                        'hasil'           => $hasilGrup,
                        'target_adjusted' => 0,
                        'capaian_persen'  => null,
                        'has_target'      => false,
                    ];
                    continue;
                }

                $target             = $rateInfo['target'];
                $ratePerOrgPerMenit = $rateInfo['ratePerOrgPerMenit'];
                $biayaPerUnit       = (float) $target->potongan;

                // Target di sini pakai basis NORMAL (org & jam normal target
                // itu sendiri) — BUKAN target-adjusted dari jam aktual kru
                // hari ini, karena jam aktual kru itu memang sengaja dibagi
                // ke banyak ukuran, bukan dipakai penuh untuk 1 ukuran.
                $targetNormal = (float) $target->target;
                $capaian      = $targetNormal > 0 ? ($hasilGrup / $targetNormal) * 100 : 100.0;
                $nilaiTarget  = $targetNormal * $biayaPerUnit;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilaiTarget;
                $jumlahUkuranAda  += 1;

                $ukuranGroups[] = [
                    'kode_ukuran'     => $kodeUkuran,
                    'ukuran_nama'     => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'      => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'              => $kw,
                    'hasil'           => $hasilGrup,
                    'target_adjusted' => $targetNormal,
                    'selisih'         => $hasilGrup - $targetNormal,
                    'capaian_persen'  => $capaian,
                    'has_target'      => true,
                ];
            }

            /* ============================================================
             * 3. CAPAIAN GLOBAL (JUMLAH PERSEN) → POTONGAN KOLEKTIF
             * ------------------------------------------------------------
             * capaianGlobal = Σ semua capaian_persen ukuran hari ini.
             * >= 100% -> 1 hari kerja kru terpakai penuh secara produktif,
             * TIDAK dipotong, walau tiap ukuran individually di bawah 100%.
             * < 100%  -> kekuranganPersen x nilaiSatuHariPenuh (RATA-RATA
             * nilai target per ukuran, BUKAN dijumlah — karena kru cuma
             * punya 1 "jatah hari kerja", bukan 1 jatah per ukuran).
             * ============================================================ */

            $capaianGlobal      = $sumCapaianPersen;
            $nilaiSatuHariPenuh = $jumlahUkuranAda > 0 ? ($sumNilaiTarget / $jumlahUkuranAda) : 0;
            $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
            $potonganTotalTim   = $kekuranganPersen * $nilaiSatuHariPenuh;

            $proporsional       = new ProporsionalStrategy();
            $potonganPerPegawai = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);

            // Flag informasi (BUKAN cap/pembatas) kalau potongan gabungan tim
            // melebihi total gaji normal tim hari itu — biar kelihatan di
            // laporan, keputusan lanjut diserahkan ke atasan/HR.
            $potonganMelebihiGaji = $totalGajiTim > 0 && $potonganTotalTim > $totalGajiTim;

            /* ============================================================
             * 4. SUSUN OUTPUT PER UKURAN (untuk kartu tampilan)
             * ============================================================ */

            foreach ($ukuranGroups as $grup) {
                foreach ($produksi->pegawaiJoint as $pj) {
                    if (!$pj->pegawai) {
                        continue;
                    }

                    $nomorMeja = $pj->tugas ?? $pj->nomor_meja ?? '-';
                    $key       = $nomorMeja . '|' . $grup['kode_ukuran'];

                    if (!isset($result[$key])) {
                        $result[$key] = [
                            'nomor_meja'      => $nomorMeja,
                            'kode_ukuran'     => $grup['kode_ukuran'],
                            'ukuran'          => $grup['ukuran_nama'],
                            'jenis_kayu'      => $grup['jenis_kayu'],
                            'kw'              => $grup['kw'],
                            'pekerja'         => [],
                            'hasil'           => $grup['hasil'],
                            'target_adjusted' => $grup['target_adjusted'],
                            'selisih'         => $grup['selisih'] ?? ($grup['hasil'] - $grup['target_adjusted']),
                            'capaian_persen'  => $grup['capaian_persen'],
                            'jam_aktual'      => $jamAktualRata,
                            'jumlah_pekerja'  => $jumlahPekerja,
                            'tanggal'         => $tanggal,
                            'has_target'      => $grup['has_target'],
                            // Info tambahan: capaian GLOBAL tim hari ini (sama untuk semua kartu)
                            'rata2_capaian_tim'      => $capaianGlobal,
                            'potongan_total_tim'     => $potonganTotalTim,
                            'potongan_melebihi_gaji' => $potonganMelebihiGaji,
                            'total_gaji_tim'         => $totalGajiTim,
                        ];
                    }

                    $result[$key]['pekerja'][] = [
                        'id'         => $pj->pegawai->kode_pegawai ?? '-',
                        'nama'       => $pj->pegawai->nama_pegawai ?? '-',
                        'jam_masuk'  => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                        'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                        'ijin'       => $pj->ijin ?? '-',
                        'keterangan' => $pj->ket ?? '-',
                        'hasil'      => $grup['hasil'],
                        // Potongan per orang dibagi PROPORSIONAL sesuai jam
                        // kerja masing-masing, dari hasil rata-rata capaian
                        // kolektif lintas ukuran — sama di semua kartu.
                        'pot_target' => $potonganPerPegawai[(string) ($pj->id_pegawai ?? $pj->pegawai->id)] ?? 0,
                    ];
                }
            }
        }

        return array_values($result);
    }
}