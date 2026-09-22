<?php

namespace App\Filament\Pages\LaporanNyusup\Transformers;

use App\Filament\Pages\LaporanNyusup\Queries\LoadLaporanNyusup;
use App\Models\JenisKayu;
use App\Models\Mesin;
use App\Models\ProduksiNyusup;
use App\Services\Target\Resolvers\NyusupTargetResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hitung target & potongan Nyusup PER PEGAWAI (individual).
 *
 * - Hasil dicatat per pegawai di detail_barang_dikerjakan (id_pegawai_nyusup).
 * - Target dari master Target (mesin NYUSUP): tebal + jenis kayu + grade.
 * - Target ADJUSTED ke menit kerja bersih pegawai itu sendiri:
 *     target / (jam normal x 60) / orang normal x menit bersih pegawai.
 * - Beberapa barang dalam sehari: capaian tiap barang DIJUMLAH, lalu
 *     potongan = (100% - capaian global) x nilai satu hari penuh
 *     (rata-rata nilai target antar barang), dibulatkan kelipatan 500.
 * - Barang tanpa target tidak ikut dihitung (has_target=false).
 * - Pegawai tanpa satu pun hasil bertarget: potongan 0.
 */
class NyusupDataMap
{
    /** Jendela istirahat 12:00-13:00 (menit sejak tengah malam). */
    private const ISTIRAHAT_MULAI = 720;

    private const ISTIRAHAT_SELESAI = 780;

    /**
     * @return array<int, array> satu elemen per produksi (normalnya 1 per hari)
     */
    public static function make($collection): array
    {
        $out = [];
        foreach ($collection as $produksi) {
            $out[] = self::makeProduksi($produksi);
        }

        return $out;
    }

    public static function makeProduksi(ProduksiNyusup $produksi): array
    {
        $produksi->loadMissing(LoadLaporanNyusup::RELASI);

        $idMesin = self::idMesinNyusup();
        $resolver = new NyusupTargetResolver;
        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

        // nama jenis barang -> id jenis kayu (dicocokkan lewat nama)
        $kayuCache = [];
        $resolveKayu = function (?string $nama) use (&$kayuCache): ?int {
            if (! $nama) {
                return null;
            }
            $k = strtolower(trim($nama));
            if (! array_key_exists($k, $kayuCache)) {
                $kayuCache[$k] = JenisKayu::whereRaw('LOWER(nama_kayu) = ?', [$k])->value('id');
            }

            return $kayuCache[$k] ? (int) $kayuCache[$k] : null;
        };

        /* 1. Kumpulkan pegawai (digabung per id_pegawai) */
        $porPegawai = [];
        $rowKePegawai = [];

        foreach ($produksi->pegawaiNyusup as $pn) {
            if (! $pn->pegawai) {
                continue;
            }

            $key = (int) $pn->id_pegawai;
            $menit = self::hitungMenitBersih($pn->masuk, $pn->pulang);

            if (! isset($porPegawai[$key])) {
                $porPegawai[$key] = [
                    'id' => $pn->pegawai->kode_pegawai ?? '-',
                    'nama' => $pn->pegawai->nama_pegawai ?? '-',
                    'tugas' => [],
                    'jam_masuk' => $pn->masuk ? Carbon::parse($pn->masuk)->format('H:i') : '-',
                    'jam_pulang' => $pn->pulang ? Carbon::parse($pn->pulang)->format('H:i') : '-',
                    'menit' => null,
                    'ijin' => $pn->ijin ?? '-',
                    'keterangan' => $pn->ket ?? '-',
                    'groups' => [],
                ];
            }

            if ($menit !== null) {
                $porPegawai[$key]['menit'] = ($porPegawai[$key]['menit'] ?? 0) + $menit;
            }
            if (! empty($pn->tugas)) {
                $porPegawai[$key]['tugas'][] = $pn->tugas;
            }

            $rowKePegawai[$pn->id] = $key;
        }

        /* 2. Kelompokkan hasil per pegawai: tebal + jenis kayu + grade */
        foreach ($produksi->detailBarangDikerjakan as $d) {
            $key = $rowKePegawai[$d->id_pegawai_nyusup] ?? null;
            if ($key === null) {
                continue;
            }

            $b = $d->barangSetengahJadiHp;
            $u = $b?->ukuran;
            $jenis = $b?->jenisBarang?->nama_jenis_barang;
            $grade = $b?->grade?->nama_grade;
            $tebal = $u?->tebal;

            $gk = ($tebal ?? 'x').'|'.strtolower((string) $jenis).'|'.strtolower((string) $grade);

            if (! isset($porPegawai[$key]['groups'][$gk])) {
                $porPegawai[$key]['groups'][$gk] = [
                    'tebal' => $tebal,
                    'jenis' => $jenis,
                    'grade' => $grade,
                    'ukuran_list' => [],
                    'hasil' => 0.0,
                ];
            }

            $porPegawai[$key]['groups'][$gk]['hasil'] += (float) ($d->hasil ?? 0);
            if ($u) {
                $porPegawai[$key]['groups'][$gk]['ukuran_list'][] = self::formatUkuran($u);
            }
        }

        /* 3. Hitung target & potongan per pegawai */
        $hasilPegawai = [];
        $potonganTotal = 0;
        $adaTanpaTarget = false;

        foreach ($porPegawai as $key => $p) {
            $items = [];
            $sumCapaian = 0.0;
            $sumNilai = 0.0;
            $jumlahAda = 0;
            $hasilSemua = 0.0;

            uasort($p['groups'], fn ($a, $b) => (float) $a['tebal'] <=> (float) $b['tebal']);

            foreach ($p['groups'] as $g) {
                $hasilSemua += $g['hasil'];
                $idKayu = $resolveKayu($g['jenis']);
                $target = $resolver->resolve($idMesin, $g['tebal'], $idKayu, $g['grade']);
                $labelUkuran = implode(', ', array_unique($g['ukuran_list'])) ?: '-';

                if (! $target) {
                    Log::warning('Target Nyusup tidak ditemukan', [
                        'id_produksi' => $produksi->id,
                        'id_mesin' => $idMesin,
                        'tebal' => $g['tebal'],
                        'jenis' => $g['jenis'],
                        'grade' => $g['grade'],
                    ]);

                    $adaTanpaTarget = true;
                    $items[] = [
                        'ukuran' => $labelUkuran,
                        'tebal' => $g['tebal'],
                        'jenis_kayu' => $g['jenis'] ?? '-',
                        'grade' => $g['grade'] ?? '-',
                        'kode_ukuran' => 'NYUSUP '.($g['tebal'] ?? '-'),
                        'hasil' => $g['hasil'],
                        'target' => 0,
                        'selisih' => $g['hasil'],
                        'capaian_persen' => null,
                        'has_target' => false,
                    ];

                    continue;
                }

                $menitNormal = ((float) $target->jam) * 60;
                $orangNormal = max(1, (int) $target->orang);
                $ratePerOrgPerMenit = $menitNormal > 0
                    ? ((float) $target->target / $menitNormal) / $orangNormal
                    : 0.0;

                // Kalau jam masuk/pulang kosong, anggap kerja normal penuh.
                $menitPakai = $p['menit'] ?? $menitNormal;
                $targetAdj = $ratePerOrgPerMenit * $menitPakai;
                $capaian = $targetAdj > 0 ? ($g['hasil'] / $targetAdj) * 100 : 0.0;
                $biayaPerUnit = (float) $target->potongan;

                $sumCapaian += $capaian;
                $sumNilai += $targetAdj * $biayaPerUnit;
                $jumlahAda++;

                $items[] = [
                    'ukuran' => $labelUkuran,
                    'tebal' => $g['tebal'],
                    'jenis_kayu' => $g['jenis'] ?? '-',
                    'grade' => $g['grade'] ?? '-',
                    'kode_ukuran' => 'NYUSUP '.($g['tebal'] ?? '-'),
                    'hasil' => $g['hasil'],
                    'target' => $targetAdj,
                    'target_normal' => (float) $target->target,
                    'selisih' => $g['hasil'] - $targetAdj,
                    'capaian_persen' => $capaian,
                    'has_target' => true,
                ];
            }

            $potongan = 0;
            if ($jumlahAda > 0) {
                $nilaiSatuHariPenuh = $sumNilai / $jumlahAda;
                $kekuranganPersen = max(0, 100 - $sumCapaian) / 100;
                $potongan = (int) (round(($kekuranganPersen * $nilaiSatuHariPenuh) / 500) * 500);
            }

            $potonganTotal += $potongan;

            $hasilPegawai[] = [
                'id_pegawai' => $key,
                'id' => $p['id'],
                'nama' => $p['nama'],
                'tugas' => implode(', ', array_unique($p['tugas'])),
                'jam_masuk' => $p['jam_masuk'],
                'jam_pulang' => $p['jam_pulang'],
                'jam_aktual_bersih' => $p['menit'] !== null ? round($p['menit'] / 60, 2) : null,
                'ijin' => $p['ijin'],
                'keterangan' => $p['keterangan'],
                'items' => $items,
                'punya_target' => $jumlahAda > 0,
                'capaian_global' => $jumlahAda > 0 ? $sumCapaian : null,
                'hasil_total' => $hasilSemua,
                'pot_target' => $potongan,
            ];
        }

        return [
            'id_produksi' => $produksi->id,
            'tanggal' => $tanggal,
            'jumlah_pekerja' => count($hasilPegawai),
            'pegawai' => $hasilPegawai,
            'potongan_total' => $potonganTotal,
            'ada_tanpa_target' => $adaTanpaTarget,
            'mesin_ditemukan' => $idMesin !== null,
        ];
    }

    /** Mesin Nyusup dicari lewat nama, bukan ID yang di-hardcode. */
    private static function idMesinNyusup(): ?int
    {
        $id = Mesin::whereRaw('UPPER(nama_mesin) = ?', ['NYUSUP'])->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Menit kerja bersih: pulang - masuk dikurangi irisan dengan jendela
     * istirahat 12:00-13:00. Kalau lewat tengah malam, dikurangi 60 menit flat.
     */
    private static function hitungMenitBersih($masuk, $pulang): ?float
    {
        if (! $masuk || ! $pulang) {
            return null;
        }

        $m = Carbon::parse($masuk);
        $p = Carbon::parse($pulang);

        $masukMenit = $m->hour * 60 + $m->minute;
        $pulangMenit = $p->hour * 60 + $p->minute;

        if ($pulangMenit < $masukMenit) {
            return (float) max(0, ($pulangMenit + 1440 - $masukMenit) - 60);
        }

        $overlap = max(0, min($pulangMenit, self::ISTIRAHAT_SELESAI) - max($masukMenit, self::ISTIRAHAT_MULAI));

        return (float) max(0, ($pulangMenit - $masukMenit) - $overlap);
    }

    private static function formatUkuran($u): string
    {
        return rtrim(rtrim(number_format((float) $u->panjang, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->lebar, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.');
    }
}