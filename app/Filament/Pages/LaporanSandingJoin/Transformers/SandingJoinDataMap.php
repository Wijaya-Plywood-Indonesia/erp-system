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
    /**
     * Jam istirahat pabrik (tetap): 12:00 - 13:00.
     * Dipotong dari jam kerja HANYA jika rentang masuk-pulang pegawai
     * benar-benar beririsan dengan jam istirahat ini (sama dengan Join).
     */
    private const ISTIRAHAT_MULAI   = '12:00';
    private const ISTIRAHAT_SELESAI = '13:00';

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
            $jamAktualPerOrang = [];

            foreach ($produksi->pegawaiSandingJoint as $pj) {
                if (!$pj->pegawai) continue;

                $totalGajiTim += (float) ($pj->pegawai->gaji ?? 0);

                if (!$pj->masuk || !$pj->pulang) continue;

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

                $idUkuran    = $firstHasil->id_ukuran;
                $idJenisKayu = $firstHasil->id_jenis_kayu;
                $hasilGrup   = (float) $hasilRows->sum('jumlah');

                if ($ukuranModel && $jenisKayuModel) {
                    $kwSuffix = in_array(strtolower($kw), ['afs', 'afm']) ? $kw : '';
                    $kodeUkuran = 'SANDING JOINT ' . strtoupper($jenisKayuModel->nama_kayu) . ' ' .
                        $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kwSuffix;
                } else {
                    $kodeUkuran = 'SANDING-JOINT-NOT-FOUND-' . $idUkuran . '-' . $idJenisKayu . '-' . $kw;
                }

                // Kunci unik per kombinasi ukuran + jenis kayu + KW, supaya dua
                // jenis kayu dengan dimensi yang sama tidak saling menimpa (overwrite)
                // di array $result pada langkah 4 di bawah.
                $groupKey = $idUkuran . '|' . $idJenisKayu . '|' . $kw;

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
                        'group_key'      => $groupKey,
                        'kode_ukuran'    => $kodeUkuran,
                        'ukuran_nama'    => $ukuranModel->nama_ukuran ?? '-',
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
                $targetNormal       = (float) $target->target;

                // ADJUSTED ke total tenaga kerja tim hari itu (dipakai SEKALI per ukuran),
                // dibulatkan karena satuannya lembar utuh. Sama dengan Join.
                $targetAdjusted     = round($ratePerOrgPerMenit * $jumlahPekerja * $avgMenitPerOrang);
                $capaian            = $targetAdjusted > 0 ? ($hasilGrup / $targetAdjusted) * 100 : 100.0;
                $nilaiTarget        = $targetAdjusted * $biayaPerUnit;

                $sumCapaianPersen += $capaian;
                $sumNilaiTarget   += $nilaiTarget;
                $jumlahUkuranAda  += 1;

                $ukuranGroups[] = [
                    'group_key'      => $groupKey,
                    'kode_ukuran'    => $kodeUkuran,
                    'ukuran_nama'    => $ukuranModel->nama_ukuran ?? '-',
                    'jenis_kayu'     => $jenisKayuModel->nama_kayu ?? '-',
                    'kw'             => $kw,
                    'hasil'          => $hasilGrup,
                    'target'         => $targetAdjusted,
                    'target_normal'  => $targetNormal,
                    'selisih'        => $hasilGrup - $targetAdjusted,
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
                $key = $grup['group_key'];

                $result[$key] = [
                    'kode_ukuran'            => $grup['kode_ukuran'],
                    'ukuran'                 => $grup['ukuran_nama'],
                    'jenis_kayu'             => $grup['jenis_kayu'],
                    'kw'                     => $grup['kw'],
                    'hasil'                  => $grup['hasil'],
                    'target'                 => $grup['target'],
                    'target_normal'          => $grup['target_normal'] ?? null,
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
                    'jam_aktual_bersih' => $jamAktualPerOrang[$idPegawai] ?? null,
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

    /**
     * Menit kerja BERSIH: (pulang - masuk) dikurangi irisan dengan jam istirahat.
     * Kalau pekerja pulang sebelum istirahat / masuk sesudah istirahat, tidak ada potongan.
     */
    private static function hitungMenitKerjaBersih(Carbon $masuk, Carbon $pulang): int
    {
        if ($pulang->lessThan($masuk)) {
            $pulang = $pulang->copy()->addDay();
        }

        $totalMenit = $masuk->diffInMinutes($pulang);

        $istirahatMulai   = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_MULAI);
        $istirahatSelesai = Carbon::parse($masuk->format('Y-m-d') . ' ' . self::ISTIRAHAT_SELESAI);

        $overlapMulai   = $masuk->greaterThan($istirahatMulai) ? $masuk : $istirahatMulai;
        $overlapSelesai = $pulang->lessThan($istirahatSelesai) ? $pulang : $istirahatSelesai;

        $menitIstirahatTerpotong = 0;
        if ($overlapSelesai->greaterThan($overlapMulai)) {
            $menitIstirahatTerpotong = $overlapMulai->diffInMinutes($overlapSelesai);
        }

        return max(0, (int) round($totalMenit - $menitIstirahatTerpotong));
    }
}