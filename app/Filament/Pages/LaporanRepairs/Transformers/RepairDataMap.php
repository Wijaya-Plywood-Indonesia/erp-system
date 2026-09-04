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

    public static function make($collection): array
    {
        $action = new HitungPotonganProduksiAction;
        $targetCache = [];

        // Kunci cache HARUS mencakup id_ukuran + id_jenis_kayu + grade(kw) sekaligus.
        // Sebelumnya cache cuma pakai id_ukuran saja, jadi satu ukuran yang punya
        // beberapa baris target (beda jenis kayu / beda KW) bisa saling "menimpa"
        // hasil resolve satu sama lain.
        $resolveTarget = function (?int $idUkuran, ?int $idJenisKayu, ?string $grade) use ($action, &$targetCache) {
            if (! $idUkuran) {
                return null;
            }

            $cacheKey = $idUkuran.'|'.($idJenisKayu ?? '0').'|'.($grade ?? '');

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
                    $kodeUkuran = 'REPAIR '.$ukuranModel->panjang.$ukuranModel->lebar.
                        str_replace('.', ',', $ukuranModel->tebal).$kwSuffix;
                } else {
                    $kodeUkuran = 'REPAIR-NOT-FOUND';
                }

                // Kolom `grade` di tabel targets bertipe varchar — cast eksplisit
                // supaya perbandingan di query resolver konsisten (mis. "3" bukan 3).
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

                // Target PER-KEPALA sesuai desain tabel target (kolom "Tgt/Org").
                // Nilai ini TETAP, tidak peduli berapa orang yang aktual kerja hari ini.
                $targetPerOrang = $orangNormal > 0 ? $targetBaris / $orangNormal : $targetBaris;

                $pekerjaBaris = $detail->rencanaPegawais->filter(fn ($rp) => $rp->pegawai);
                $jumlahPekerjaBaris = $pekerjaBaris->count();
                // Hasil baris ini dibagi rata ke pegawai yg tercatat DI BARIS INI SAJA —
                // bukan diasumsikan seluruh meja mengerjakan baris ini bersama.
                $hasilIndividuBaris = $jumlahPekerjaBaris > 0 ? floor($jumlahHasil / $jumlahPekerjaBaris) : 0;

                // --- Penyesuaian JAM: rata-rata jam bersih tim vs jam normal ---
                // Tim dianggap kerja bersama; kalau ada yang pulang lebih awal,
                // target tim ikut turun mengikuti rata-rata jam bersih semua
                // pekerja di baris ini — BUKAN dihitung per orang secara terpisah.
                $menitNormal = $rateInfo ? ((float) $rateInfo['target']->jam) * 60 : 0;

                $daftarMenitBersih = $pekerjaBaris
                    ->map(function ($rp) {
                        $masuk = $rp->jam_masuk ? Carbon::parse($rp->jam_masuk) : null;
                        $pulang = $rp->jam_pulang ? Carbon::parse($rp->jam_pulang) : null;

                        return self::hitungMenitBersih($masuk, $pulang);
                    })
                    ->filter(fn ($menit) => $menit !== null); // abaikan yg datanya kosong

                $rataMenitBersihBaris = $daftarMenitBersih->count() > 0
                    ? $daftarMenitBersih->avg()
                    : null;

                // Kalau tidak ada data jam sama sekali, anggap normal (rasio = 1,
                // tidak mengubah target) daripada memaksa target jadi 0.
                $rasioJam = ($menitNormal > 0 && $rataMenitBersihBaris !== null)
                    ? ($rataMenitBersihBaris / $menitNormal)
                    : 1.0;

                // Target PER-KEPALA, sudah dikoreksi rata-rata jam kerja tim.
                $targetPerOrangJamAdjusted = $targetPerOrang * $rasioJam;

                // Target BARIS (untuk tampilan) disesuaikan proporsional terhadap
                // jumlah pekerja AKTUAL vs jumlah pekerja NORMAL di tabel target,
                // DAN terhadap rata-rata jam kerja aktual tim vs jam normal.
                // - Semua aktual == normal  -> target TETAP (tidak berubah).
                // - Ada yang beda (orang atau jam)  -> target ikut menyesuaikan.
                $targetEfektifBaris = ($orangNormal > 0 && $jumlahPekerjaBaris > 0)
                    ? $targetPerOrangJamAdjusted * $jumlahPekerjaBaris
                    : $targetBaris;

                // --- kumpulan untuk tampilan (per meja) ---
                if (! isset($mejaGrup[$nomorMeja])) {
                    $mejaGrup[$nomorMeja] = [
                        'nomor_meja' => $nomorMeja,
                        'tanggal' => $tanggal,
                        'keterangan_hasil' => $detail->keterangan ?? '—',
                        'keterangan_kerja' => $kendalaHariIni,
                        'items' => [],
                        'pekerja_ids' => [],
                    ];
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
                ];

                // --- kumpulan untuk LOGIKA (per individu, lintas baris/meja) ---
                foreach ($pekerjaBaris as $rp) {
                    $kodePegawai = $rp->pegawai->kode_pegawai ?? '-';
                    $idKey = $rp->id_pegawai ?? $rp->pegawai->id;

                    $mejaGrup[$nomorMeja]['pekerja_ids'][$kodePegawai] = $idKey;

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
                        // Capaian individu dibandingkan ke target PER-ORANG yang sudah
                        // dikoreksi rata-rata jam kerja tim (targetPerOrangJamAdjusted) —
                        // bukan target baris mentah, dan bukan target per-orang yang
                        // masih mengasumsikan semua orang kerja jam normal penuh.
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

        // Hitung capaian global & potongan PER INDIVIDU (jumlah-persen, gaya PotSiku)
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

        // Susun output per meja (TAMPILAN tidak berubah), pot_target diambil per individu
        $result = [];
        foreach ($mejaGrup as $nomorMeja => $m) {
            $totalTargetMeja = array_sum(array_column($m['items'], 'target'));
            $totalHasilMeja = array_sum(array_column($m['items'], 'hasil'));
            $totalSelisih = $totalHasilMeja - $totalTargetMeja;
            $capaianTotalMeja = $totalTargetMeja > 0 ? ($totalHasilMeja / $totalTargetMeja) * 100 : null;

            $pekerjaList = [];
            foreach ($m['pekerja_ids'] as $kodePegawai => $idKey) {
                $src = $porPegawai[$idKey] ?? null;
                if (! $src) {
                    continue;
                }
                $pekerjaList[] = [
                    'id' => $kodePegawai,
                    'nama' => $src['nama'],
                    'jam_masuk' => $src['jam_masuk'],
                    'jam_pulang' => $src['jam_pulang'],
                    'ijin' => $src['ijin'],
                    'keterangan' => $src['keterangan'],
                    // Potongan sekarang PER ORANG, dari capaian individunya sendiri —
                    // bukan lagi dibagi rata dari total denda meja.
                    'pot_target' => $potonganPerIndividu[$idKey] ?? 0,
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
                'capaian_total' => $capaianTotalMeja, // tetap ditampilkan sebagai info rasio-meja
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

        usort($result, fn ($a, $b) => strnatcmp((string) $a['nomor_meja'], (string) $b['nomor_meja']));

        return $result;
    }
}
