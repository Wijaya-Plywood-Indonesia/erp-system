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

            foreach ($produksi->pegawaiJoint as $pj) {
                if (!$pj->pegawai || !$pj->masuk || !$pj->pulang) {
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
             * 2. HITUNG TARGET-ADJUSTED & DELTA RUPIAH PER UKURAN — BELUM
             *    DIBAGI KE PEGAWAI. Karena kru sama kerja di banyak ukuran
             *    sekaligus dalam 1 hari, kita GABUNG (net) dulu surplus dan
             *    defisitnya lintas ukuran, baru tentukan apakah tim ini
             *    kena potongan atau tidak secara keseluruhan.
             *
             * Kenapa begini: kalau dievaluasi per-ukuran sendiri-sendiri,
             * tim bisa dianggap "kurang" di ukuran A padahal sebenarnya
             * mereka SUDAH LEBIH capai di ukuran B — surplus itu seharusnya
             * menutupi defisitnya, bukan dua-duanya dievaluasi terpisah.
             * ============================================================ */

            $hasilGrouped = $produksi->hasilJoint->groupBy(function ($h) {
                return $h->id_ukuran . '|' . $h->id_jenis_kayu . '|' . $h->kw;
            });

            $ukuranGroups   = []; // data mentah per ukuran, dipakai lagi di step 4
            $netDeltaRupiah = 0;  // total (hasil - targetAdjusted) * biayaPerUnit, digabung semua ukuran

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
                        'has_target'      => false,
                    ];
                    continue;
                }

                $target             = $rateInfo['target'];
                $ratePerOrgPerMenit = $rateInfo['ratePerOrgPerMenit'];
                $biayaPerUnit       = (float) $target->potongan;

                $targetAdjusted = $ratePerOrgPerMenit * $jumlahPekerja * $avgMenitPerOrang;

                $deltaGrup = ($hasilGrup - $targetAdjusted) * $biayaPerUnit; // + = surplus, - = defisit
                $netDeltaRupiah += $deltaGrup;

                $ukuranGroups[] = [
                    'kode_ukuran'     => $kodeUkuran,
                    'ukuran_nama'     => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'      => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'              => $kw,
                    'hasil'           => $hasilGrup,
                    'target_adjusted' => $targetAdjusted,
                    'selisih'         => $hasilGrup - $targetAdjusted,
                    'has_target'      => true,
                ];
            }

            /* ============================================================
             * 3. TENTUKAN POTONGAN KOLEKTIF TIM (SETELAH NETTING), LALU
             *    BAGI KE PEKERJA PAKAI ProporsionalStrategy (sesuai porsi
             *    jam kerja tiap orang — BUKAN rata), karena tidak semua
             *    pekerja tentu kerja durasi yang sama persis.
             * ------------------------------------------------------------
             * Kalau net gabungan masih defisit (netDeltaRupiah < 0), itu
             * baru jadi potongan kolektif tim, dibagi proporsional.
             * ============================================================ */

            $potonganTotalTim = $netDeltaRupiah < 0 ? abs($netDeltaRupiah) : 0;

            $proporsional        = new ProporsionalStrategy();
            $potonganPerPegawai  = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);

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
                            'jam_aktual'      => $jamAktualRata,
                            'jumlah_pekerja'  => $jumlahPekerja,
                            'tanggal'         => $tanggal,
                            'has_target'      => $grup['has_target'],
                            // Info tambahan: hasil netting seluruh kru hari ini (sama untuk semua kartu)
                            'net_delta_tim'      => $netDeltaRupiah,
                            'potongan_total_tim' => $potonganTotalTim,
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
                        // kerja masing-masing (bukan rata), dari hasil
                        // netting kolektif lintas ukuran — sama di semua
                        // kartu ukuran hari itu.
                        'pot_target' => $potonganPerPegawai[(string) ($pj->id_pegawai ?? $pj->pegawai->id)] ?? 0,
                    ];
                }
            }
        }

        return array_values($result);
    }
}