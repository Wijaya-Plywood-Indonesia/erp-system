<?php

namespace App\Filament\Pages\LaporanRepairs\Transformers;

use App\Actions\HitungPotonganProduksiAction;
use App\Enums\Mesin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RepairDataMap
{
    // Jendela jam istirahat, dipakai untuk menghitung potongan overlap.
    // ASUMSI: 12:00–13:00 (60 menit), berdasar contoh kasus. Ubah di sini
    // kalau ternyata ada jendela istirahat berbeda per shift.
    private const ISTIRAHAT_MULAI_MENIT = 12 * 60; // 720

    private const ISTIRAHAT_SELESAI_MENIT = 13 * 60; // 780

    /**
     * Hitung jam kerja bersih (menit) seorang pekerja, dengan memotong
     * bagian jam kerjanya yang beririsan dengan jendela istirahat.
     * Contoh: masuk 06:00 pulang 12:30 -> beririsan 30 menit dgn istirahat
     * (12:00-12:30) -> jam kotor 6,5 jam - 0,5 jam = 6 jam bersih.
     */
    private static function hitungMenitBersih(?Carbon $masuk, ?Carbon $pulang): ?float
    {
        if (! $masuk || ! $pulang) {
            return null;
        }

        $masukMenit = $masuk->hour * 60 + $masuk->minute;
        $pulangMenit = $pulang->hour * 60 + $pulang->minute;

        $kerjaKotor = max(0, $pulangMenit - $masukMenit);

        $overlapMulai = max($masukMenit, self::ISTIRAHAT_MULAI_MENIT);
        $overlapSelesai = min($pulangMenit, self::ISTIRAHAT_SELESAI_MENIT);
        $potonganIstirahat = max(0, $overlapSelesai - $overlapMulai);

        return $kerjaKotor - $potonganIstirahat;
    }

    /**
     * KETERANGAN LABEL (tampilan saja, TIDAK mengubah nominal potongan):
     * Tentukan status kerja seseorang berdasarkan jumlah pekerja di SETIAP
     * baris/ukuran yang ia kerjakan.
     * - Semua baris berisi PAS 2 orang -> "Tim (2 org)"
     * - Ada baris yang jumlahnya BUKAN 2 (1 orang sendirian, atau 3+ orang)
     *   -> "Individu"
     */
    private static function tentukanStatusKerja(array $jumlahPekerjaPerBaris): string
    {
        if (empty($jumlahPekerjaPerBaris)) {
            return '-';
        }

        $semuaTim2 = true;
        foreach ($jumlahPekerjaPerBaris as $n) {
            if ($n !== 2) {
                $semuaTim2 = false;
                break;
            }
        }

        return $semuaTim2 ? 'Tim (2 org)' : 'Individu';
    }

    public static function make($collection): array
    {
        $action = new HitungPotonganProduksiAction;
        $targetCache = [];

        $resolveTarget = function (?int $idUkuran, ?int $idJenisKayu, ?string $grade) use ($action, &$targetCache) {
            if (! $idUkuran) {
                return null;
            }

            $cacheKey = $idUkuran . '|' . ($idJenisKayu ?? '0') . '|' . ($grade ?? '');

            if (! array_key_exists($cacheKey, $targetCache)) {
                $targetCache[$cacheKey] = $action->resolveTargetDanRate(
                    Mesin::Repair,
                    $idUkuran,
                    $idJenisKayu,
                    $grade,
                );
            }

            return $targetCache[$cacheKey];
        };

        $mejaGrup = [];       // untuk TAMPILAN: tetap dikelompokkan per meja
        $porPegawai = [];     // untuk LOGIKA: dikelompokkan per individu, lintas meja/ukuran

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d/m/Y');
            $kendalaHariIni = $produksi->kendala ?? '—';

            foreach ($produksi->detailHasilRepairs as $detail) {
                $nomorMeja = (string) ($detail->nomor_meja ?? '1');
                $jumlahHasil = (int) $detail->jumlah;

                $ukuranModel = $detail->ukuran;
                $jenisKayuModel = $detail->modalRepair?->jenisKayu ?? $detail->jenisKayu;
                $kw = $detail->kw ?? 1;

                if ($ukuranModel && $jenisKayuModel) {
                    $kwSuffix = in_array(strtolower((string) $kw), ['afs', 'afm']) ? $kw : '';
                    $kodeUkuran = 'REPAIR ' . $ukuranModel->panjang . $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) . $kwSuffix;
                } else {
                    $kodeUkuran = 'REPAIR-NOT-FOUND';
                }

                $idJenisKayuBaris = $jenisKayuModel->id ?? null;
                $gradeBaris = $kw !== null ? strtolower((string) $kw) : null;

                $rateInfo = $resolveTarget(
                    $detail->id_ukuran ? (int) $detail->id_ukuran : null,
                    $idJenisKayuBaris,
                    $gradeBaris,
                );
                if (! $rateInfo) {
                    Log::warning('Target Repair tidak ditemukan', [
                        'id_produksi' => $produksi->id,
                        'id_detail' => $detail->id,
                        'id_ukuran' => $detail->id_ukuran,
                        'id_jenis_kayu' => $idJenisKayuBaris,
                        'grade' => $gradeBaris,
                    ]);
                }

                $targetBaris = $rateInfo ? (float) $rateInfo['target']->target : 0;
                $biayaPerUnit = $rateInfo ? (float) $rateInfo['target']->potongan : 0;
                $orangNormal = $rateInfo ? (int) $rateInfo['target']->orang : 0;

                $targetPerOrang = $orangNormal > 0 ? $targetBaris / $orangNormal : $targetBaris;

                $pekerjaBaris = $detail->rencanaPegawais->filter(fn($rp) => $rp->pegawai);
                $jumlahPekerjaBaris = $pekerjaBaris->count();
                // Hasil baris ini dibagi rata ke pegawai yg tercatat DI BARIS INI SAJA —
                // bukan diasumsikan seluruh meja mengerjakan baris ini bersama.
                $hasilIndividuBaris = $jumlahPekerjaBaris > 0 ? ($jumlahHasil / $jumlahPekerjaBaris) : 0;

                $menitNormal = $rateInfo ? ((float) $rateInfo['target']->jam) * 60 : 0;

                $daftarMenitBersih = $pekerjaBaris
                    ->map(function ($rp) {
                        $masuk = $rp->jam_masuk ? Carbon::parse($rp->jam_masuk) : null;
                        $pulang = $rp->jam_pulang ? Carbon::parse($rp->jam_pulang) : null;

                        return self::hitungMenitBersih($masuk, $pulang);
                    })
                    ->filter(fn($menit) => $menit !== null);

                $rataMenitBersihBaris = $daftarMenitBersih->count() > 0
                    ? $daftarMenitBersih->avg()
                    : null;

                $rasioJam = ($menitNormal > 0 && $rataMenitBersihBaris !== null)
                    ? ($rataMenitBersihBaris / $menitNormal)
                    : 1.0;

                $targetPerOrangJamAdjusted = $targetPerOrang * $rasioJam;

                $targetEfektifBaris = ($orangNormal > 0 && $jumlahPekerjaBaris > 0)
                    ? $targetPerOrangJamAdjusted * $jumlahPekerjaBaris
                    : $targetBaris;

                if (! isset($mejaGrup[$nomorMeja])) {
                    $mejaGrup[$nomorMeja] = [
                        'nomor_meja' => $nomorMeja,
                        'tanggal' => $tanggal,
                        'keterangan_hasil' => $detail->keterangan ?? '—',
                        'keterangan_kerja' => $kendalaHariIni,
                        'items' => [],
                        'pekerja_ids' => [],
                        'status_pekerja' => [],
                        'jam_bersih_semua' => [], // menit bersih semua pekerja/baris di meja ini
                    ];
                }

                // Kumpulkan menit bersih baris ini ke akumulator meja (utk rata-rata jam kerja meja)
                foreach ($daftarMenitBersih as $menit) {
                    $mejaGrup[$nomorMeja]['jam_bersih_semua'][] = $menit;
                }

                $capaianBaris = $targetEfektifBaris > 0 ? ($jumlahHasil / $targetEfektifBaris) * 100 : null;
                $mejaGrup[$nomorMeja]['items'][] = [
                    'kode_ukuran' => $kodeUkuran,
                    'ukuran' => $ukuranModel->nama_ukuran ?? $ukuranModel->dimensi ?? '-',
                    'jenis_kayu' => $jenisKayuModel->nama_kayu ?? '-',
                    'kw' => $kw,
                    'target' => $targetEfektifBaris,
                    'hasil' => $jumlahHasil,
                    'selisih' => $jumlahHasil - $targetEfektifBaris,
                    'capaian_persen' => $capaianBaris,
                    'has_target' => $rateInfo !== null,
                    'jumlah_pekerja' => $jumlahPekerjaBaris,
                ];

                foreach ($pekerjaBaris as $rp) {
                    $kodePegawai = $rp->pegawai->kode_pegawai ?? '-';
                    $idKey = $rp->id_pegawai ?? $rp->pegawai->id;

                    $mejaGrup[$nomorMeja]['pekerja_ids'][$kodePegawai] = $idKey;
                    $mejaGrup[$nomorMeja]['status_pekerja'][$idKey][] = $jumlahPekerjaBaris;

                    if (! isset($porPegawai[$idKey])) {
                        $porPegawai[$idKey] = [
                            'kode_pegawai' => $kodePegawai,
                            'nama' => $rp->pegawai->nama_pegawai ?? '-',
                            'jam_masuk' => $rp->jam_masuk ? Carbon::parse($rp->jam_masuk)->format('H:i') : '-',
                            'jam_pulang' => $rp->jam_pulang ? Carbon::parse($rp->jam_pulang)->format('H:i') : '-',
                            'ijin' => $rp->ijin ?? '-',
                            'keterangan' => $rp->keterangan ?? '-',
                            'sumCapaianPersen' => 0,
                            'sumNilaiTarget' => 0,
                            'jumlahUkuranAda' => 0,
                        ];
                    }

                    if ($rateInfo) {
                        $capaianIndividu = $targetPerOrangJamAdjusted > 0
                            ? ($hasilIndividuBaris / $targetPerOrangJamAdjusted) * 100
                            : 100.0;
                        $nilaiTarget = $targetPerOrangJamAdjusted * $biayaPerUnit;

                        $porPegawai[$idKey]['sumCapaianPersen'] += $capaianIndividu;
                        $porPegawai[$idKey]['sumNilaiTarget'] += $nilaiTarget;
                        $porPegawai[$idKey]['jumlahUkuranAda'] += 1;
                    }
                }
            }
        }

        $potonganPerIndividu = [];
        foreach ($porPegawai as $idKey => $data) {
            if ($data['jumlahUkuranAda'] === 0) {
                $potonganPerIndividu[$idKey] = 0;

                continue;
            }
            $capaianGlobal = $data['sumCapaianPersen'];
            $nilaiSatuHariPenuh = $data['sumNilaiTarget'] / $data['jumlahUkuranAda'];
            $kekuranganPersen = max(0, 100 - $capaianGlobal) / 100;
            $potonganPerIndividu[$idKey] = round(($kekuranganPersen * $nilaiSatuHariPenuh) / 500) * 500;
        }

        $result = [];
        foreach ($mejaGrup as $nomorMeja => $m) {
            $totalHasilMeja = array_sum(array_column($m['items'], 'hasil'));

            $capaianTotalMeja = 0;
            $hasValidCapaian = false;
            foreach ($m['items'] as $item) {
                if ($item['capaian_persen'] !== null) {
                    $capaianTotalMeja += $item['capaian_persen'];
                    $hasValidCapaian = true;
                }
            }

            if (! $hasValidCapaian) {
                $capaianTotalMeja = null;
                $totalTargetMeja = array_sum(array_column($m['items'], 'target'));
                $totalSelisih = $totalHasilMeja - $totalTargetMeja;
            } else {
                if ($capaianTotalMeja > 0) {
                    $totalTargetMeja = $totalHasilMeja / ($capaianTotalMeja / 100);
                } else {
                    $totalTargetMeja = array_sum(array_column($m['items'], 'target'));
                }
                $totalSelisih = $totalHasilMeja - $totalTargetMeja;
            }

            // Rata-rata jam bersih (menit -> jam) dari semua pekerja/baris di meja ini,
            // setara "jam_aktual" pada laporan press dryer.
            $jamBersihList = collect($m['jam_bersih_semua'] ?? []);
            $jamKerjaMeja = $jamBersihList->count() > 0 ? $jamBersihList->avg() / 60 : 0;

            $pekerjaList = [];
            foreach ($m['pekerja_ids'] as $kodePegawai => $idKey) {
                $src = $porPegawai[$idKey] ?? null;
                if (! $src) {
                    continue;
                }

                $jumlahPerBarisOrangIni = $m['status_pekerja'][$idKey] ?? [];
                $statusKerja = self::tentukanStatusKerja($jumlahPerBarisOrangIni);

                $pekerjaList[] = [
                    'id' => $kodePegawai,
                    'nama' => $src['nama'],
                    'jam_masuk' => $src['jam_masuk'],
                    'jam_pulang' => $src['jam_pulang'],
                    'ijin' => $src['ijin'],
                    'keterangan' => $src['keterangan'],
                    'pot_target' => $potonganPerIndividu[$idKey] ?? 0,
                    'status_kerja' => $statusKerja,
                ];
            }

            $firstItem = $m['items'][0] ?? [];

            $result[] = [
                'nomor_meja' => $nomorMeja,
                'tanggal' => $m['tanggal'],
                'items' => $m['items'],
                'pekerja' => $pekerjaList,
                'total_target' => $totalTargetMeja,
                'total_hasil' => $totalHasilMeja,
                'total_selisih' => $totalSelisih,
                'capaian_total' => $capaianTotalMeja,
                'jam_kerja' => $jamKerjaMeja, // <-- baru: rata-rata jam bersih tim, setara jam_aktual press dryer
                'keterangan_hasil' => $m['keterangan_hasil'],
                'keterangan_kerja' => $m['keterangan_kerja'],
                'kode_ukuran' => $firstItem['kode_ukuran'] ?? '-',
                'ukuran' => $firstItem['ukuran'] ?? '-',
                'jenis_kayu' => $firstItem['jenis_kayu'] ?? '-',
                'kw' => $firstItem['kw'] ?? '-',
                'target' => $firstItem['target'] ?? 0,
                'hasil' => $firstItem['hasil'] ?? 0,
                'selisih' => $firstItem['selisih'] ?? 0,
                'capaian_persen' => $firstItem['capaian_persen'] ?? null,
                'has_target' => $firstItem['has_target'] ?? true,
            ];
        }

        usort($result, fn($a, $b) => strnatcmp((string) $a['nomor_meja'], (string) $b['nomor_meja']));

        return $result;
    }
}
