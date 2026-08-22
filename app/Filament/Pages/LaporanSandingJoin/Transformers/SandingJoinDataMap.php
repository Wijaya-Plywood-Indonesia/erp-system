<?php

namespace App\Filament\Pages\LaporanSandingJoin\Transformers;

use Carbon\Carbon;
use App\Enums\Mesin;
use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Services\Target\Strategies\ProporsionalStrategy;
use Illuminate\Support\Facades\Log;

class SandingJoinDataMap
{
    private const ISTIRAHAT_MENIT = 60;

    public static function make($collection): array
    {
        $result = [];
        $action = new HitungPotonganProduksiAction();

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            // 1. Jam aktual & pekerja — sekali per produksi
            $totalPersonMenit = 0;
            $jumlahPekerja    = $produksi->pegawaiSandingJoint->count();
            $pekerjaInput     = [];
            $totalGajiTim     = 0;

            foreach ($produksi->pegawaiSandingJoint as $pj) {
                if (!$pj->pegawai) continue;

                $totalGajiTim += (float) ($pj->pegawai->gaji ?? 0);

                if (!$pj->masuk || !$pj->pulang) continue;

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

            // 2. Capaian per ukuran (persen), akumulasi global
            $hasilGrouped = $produksi->hasilSandingJoint->groupBy(function ($h) {
                return $h->id_ukuran . '|' . $h->id_jenis_kayu . '|' . $h->kw;
            });

            $ukuranGroups     = [];
            $sumCapaianPersen = 0;
            $sumNilaiTarget   = 0;
            $jumlahUkuranAda  = 0;

            foreach ($hasilGrouped as $hasilRows) {
                $firstHasil     = $hasilRows->first();
                $ukuranModel    = $firstHasil->ukuran;
                $jenisKayuModel = $firstHasil->jenisKayu;
                $kw             = $firstHasil->kw ?? '1';

                if ($ukuranModel && $jenisKayuModel) {
                    $kwSuffix = in_array(strtolower($kw), ['afs', 'afm']) ? $kw : '';
                    $kodeUkuran = 'SANDING JOINT' . $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kwSuffix;
                } else {
                    $kodeUkuran = 'SANDING-JOINT-NOT-FOUND';
                }

                $idUkuran    = $firstHasil->id_ukuran;
                $idJenisKayu = $firstHasil->id_jenis_kayu;
                $hasilGrup   = (float) $hasilRows->sum('jumlah');

                $rateInfo = ($idUkuran && $idJenisKayu)
                    ? $action->resolveTargetDanRate(Mesin::SandingJoint, $idUkuran, $idJenisKayu)
                    : null;

                if (!$rateInfo) {
                    Log::warning('Target Sanding Joint tidak ditemukan / data ukuran-jenis kayu tidak lengkap', [
                        'id_produksi'   => $produksi->id,
                        'kode_ukuran'   => $kodeUkuran,
                        'id_ukuran'     => $idUkuran,
                        'id_jenis_kayu' => $idJenisKayu,
                    ]);

                    $ukuranGroups[] = [
                        'kode_ukuran'    => $kodeUkuran,
                        'ukuran_nama'    => $ukuranModel->nama_ukuran ?? '-',
                        'jenis_kayu'     => $jenisKayuModel->nama_kayu ?? '-',
                        'kw'             => $kw,
                        'hasil'          => $hasilGrup,
                        'target'         => 0,
                        'capaian_persen' => null,
                        'has_target'     => false,
                    ];
                    continue;
                }

                $target             = $rateInfo['target'];
                $biayaPerUnit       = (float) $target->potongan;
                $targetNormal       = (float) $target->target;
                $capaian            = $targetNormal > 0 ? ($hasilGrup / $targetNormal) * 100 : 100.0;
                $nilaiTarget        = $targetNormal * $biayaPerUnit;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilaiTarget;
                $jumlahUkuranAda  += 1;

                $ukuranGroups[] = [
                    'kode_ukuran'    => $kodeUkuran,
                    'ukuran_nama'    => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'     => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'             => $kw,
                    'hasil'          => $hasilGrup,
                    'target'         => $targetNormal,
                    'selisih'        => $hasilGrup - $targetNormal,
                    'capaian_persen' => $capaian,
                    'has_target'     => true,
                ];
            }

            // 3. Capaian global -> potongan kolektif -> bagi proporsional
            $capaianGlobal      = $sumCapaianPersen;
            $nilaiSatuHariPenuh = $jumlahUkuranAda > 0 ? ($sumNilaiTarget / $jumlahUkuranAda) : 0;
            $kekuranganPersen   = max(0, 100 - $capaianGlobal) / 100;
            $potonganTotalTim   = $kekuranganPersen * $nilaiSatuHariPenuh;

            $proporsional       = new ProporsionalStrategy();
            $potonganPerPegawai = $proporsional->bagikan($pekerjaInput, $potonganTotalTim);

            $potonganMelebihiGaji = $totalGajiTim > 0 && $potonganTotalTim > $totalGajiTim;

            // 4. Susun output per ukuran (tanpa meja, pekerja tetap gabungan per hari)
            foreach ($ukuranGroups as $grup) {
                $key = $grup['kode_ukuran'];

                $result[$key] = [
                    'kode_ukuran'            => $grup['kode_ukuran'],
                    'ukuran'                 => $grup['ukuran_nama'],
                    'jenis_kayu'             => $grup['jenis_kayu'],
                    'kw'                     => $grup['kw'],
                    'hasil'                  => $grup['hasil'],
                    'target'                 => $grup['target'],
                    'selisih'                => $grup['selisih'] ?? ($grup['hasil'] - $grup['target']),
                    'capaian_persen'         => $grup['capaian_persen'],
                    'jam_aktual'             => $jamAktualRata,
                    'jumlah_pekerja'         => $jumlahPekerja,
                    'tanggal'                => $tanggal,
                    'has_target'             => $grup['has_target'],
                    'rata2_capaian_tim'      => $capaianGlobal,
                    'potongan_total_tim'     => $potonganTotalTim,
                    'potongan_melebihi_gaji' => $potonganMelebihiGaji,
                    'total_gaji_tim'         => $totalGajiTim,
                ];
            }

            $daftarPekerja = [];
            foreach ($produksi->pegawaiSandingJoint as $pj) {
                if (!$pj->pegawai) continue;

                $idPegawai = (string) ($pj->id_pegawai ?? $pj->pegawai->id);
                $daftarPekerja[] = [
                    'id'         => $pj->pegawai->kode_pegawai ?? '-',
                    'nama'       => $pj->pegawai->nama_pegawai ?? '-',
                    'jam_masuk'  => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '-',
                    'jam_pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '-',
                    'ijin'       => $pj->ijin ?? '-',
                    'keterangan' => $pj->ket ?? '-',
                    'pot_target' => $potonganPerPegawai[$idPegawai] ?? 0,
                ];
            }
        }

        return [
            'per_ukuran' => array_values($result),
            'pekerja'    => $daftarPekerja ?? [],
        ];
    }
}
