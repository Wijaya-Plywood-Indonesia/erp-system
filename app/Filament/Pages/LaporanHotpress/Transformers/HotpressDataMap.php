<?php

namespace App\Filament\Pages\LaporanHotpress\Transformers;

use App\Filament\Pages\LaporanHotpress\Queries\LoadLaporanHotpress;
use App\Models\JenisKayu;
use App\Models\ProduksiHp;
use App\Services\Target\Resolvers\HotpressTargetResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hitung target & potongan Hotpress per PRODUKSI (semua mesin Hotpress
 * & Coldpress dalam satu produksi/shift dihitung bersama, sesuai cara
 * data dicatat sekarang).
 *
 * - Target DISKALAKAN ke jumlah orang DAN jam kerja aktual (Cara A):
 *     rate per-orang-per-menit (dari target/orang normal/jam normal)
 *     dikali (jumlah pekerja aktual x jam kerja rata-rata tim).
 *   Ini konsisten dengan Sanding & Nyusup, dan cocok dipakai sekarang
 *   karena target di tabel sudah disesuaikan ke kru 32 orang.
 * - Barang Platform hanya dicocokkan ke target berkategori Platform;
 *   barang Triplek/Plywood tidak pernah memakai target Platform
 *   (lihat HotpressTargetResolver).
 * - Beberapa ukuran/kategori dalam sehari: capaian tiap barang
 *   DIJUMLAH, potongan = (100% - capaian global) x nilai satu hari
 *   penuh (rata-rata nilai target antar barang), dibagi rata ke semua
 *   pekerja, dibulatkan kelipatan 500.
 */
class HotpressDataMap
{
    private const ISTIRAHAT_MENIT = 60;

    public static function make($collection): array
    {
        $out = [];
        foreach ($collection as $produksi) {
            $out[] = self::makeProduksi($produksi);
        }

        return $out;
    }

    public static function makeProduksi(ProduksiHp $produksi): array
    {
        $produksi->loadMissing(LoadLaporanHotpress::RELASI);

        $resolver = new HotpressTargetResolver;
        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

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

        /* 1. Pekerja & menit kerja bersih (dijumlah untuk man-minutes tim) */
        $daftarPekerja = [];
        $totalMenitTim = 0.0;
        $jumlahMenitTerhitung = 0;
        $totalGajiTim = 0.0;

        foreach ($produksi->detailPegawaiHp as $dp) {
            if (! $dp->pegawaiHp) {
                continue;
            }

            $totalGajiTim += (float) ($dp->pegawaiHp->gaji ?? 0);
            $menit = self::hitungMenitBersih($dp->masuk, $dp->pulang);
            $idPegawai = (string) $dp->id_pegawai;

            if ($menit !== null) {
                $totalMenitTim += $menit;
                $jumlahMenitTerhitung++;
            }

            $daftarPekerja[$idPegawai] = [
                'id' => $dp->pegawaiHp->kode_pegawai ?? '-',
                'nama' => $dp->pegawaiHp->nama_pegawai ?? '-',
                'mesin' => $dp->mesin?->nama_mesin ?? '-',
                'jam_masuk' => $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i') : '-',
                'jam_pulang' => $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i') : '-',
                'ijin' => $dp->ijin ?? '-',
                'keterangan' => $dp->ket ?? '-',
            ];
        }

        $jumlahPekerja = count($daftarPekerja);
        $jamRataRata = $jumlahMenitTerhitung > 0 ? ($totalMenitTim / $jumlahMenitTerhitung) / 60 : 0.0;

        // Total menit kerja TIM (untuk skala target: orang x jam), dipakai
        // di rumus target adjusted di bawah. Kalau ada pekerja yang jam
        // masuk/pulangnya kosong, dianggap ikut kerja selama rata-rata tim
        // supaya jumlah orang tetap terhitung penuh.
        $totalMenitUntukTarget = $jumlahPekerja > 0 ? $jumlahPekerja * ($jamRataRata * 60) : 0.0;

        /* 2. Kelompokkan hasil (Platform + Triplek) per tebal+kategori+kayu+grade */
        $grup = [];
        $tambahHasil = function ($items, string $tipeBarang) use (&$grup) {
            foreach ($items as $item) {
                $b = $item->barangSetengahJadi;
                $ukuran = $b?->ukuran;
                $kategori = $b?->grade?->kategoriBarang;
                $jenis = $b?->jenisBarang?->nama_jenis_barang;
                $grade = $b?->grade?->nama_grade;
                $tebal = $ukuran?->tebal;

                $key = $tipeBarang.'|'.($tebal ?? 'x').'|'.strtolower((string) $jenis).'|'.strtolower((string) $grade);

                if (! isset($grup[$key])) {
                    $grup[$key] = [
                        'tipe' => $tipeBarang,
                        'tebal' => $tebal,
                        'id_ukuran' => $ukuran?->id,
                        'jenis' => $jenis,
                        'grade' => $grade,
                        'kategori' => $kategori?->nama_kategori ?? '-',
                        'ukuran_list' => [],
                        'hasil' => 0.0,
                    ];
                }

                $grup[$key]['hasil'] += (float) ($item->isi ?? 0);
                if ($ukuran) {
                    $grup[$key]['ukuran_list'][] = self::formatUkuran($ukuran);
                }
            }
        };

        $tambahHasil($produksi->platformHasilHp, 'platform');
        $tambahHasil($produksi->triplekHasilHp, 'triplek');

        /* 3. Target per grup */
        $perBarang = [];
        $sumCapaian = 0.0;
        $sumNilai = 0.0;
        $jumlahAda = 0;
        $sumTargetAdj = 0.0;
        $sumHasilBerTarget = 0.0;

        foreach ($grup as $g) {
            $idKayu = $resolveKayu($g['jenis']);
            $target = $resolver->resolve($g['id_ukuran'], $g['tebal'], $idKayu, $g['grade'], $g['tipe']);
            $labelUkuran = implode(', ', array_unique($g['ukuran_list'])) ?: '-';
            $labelJenis = ucfirst($g['tipe']).': '.($g['jenis'] ?? '-').' / '.($g['grade'] ?? '-').' ('.$g['kategori'].')';

            if (! $target) {
                Log::warning('Target Hotpress tidak ditemukan', [
                    'id_produksi' => $produksi->id,
                    'tipe' => $g['tipe'],
                    'tebal' => $g['tebal'],
                    'jenis' => $g['jenis'],
                    'grade' => $g['grade'],
                ]);

                $perBarang[] = [
                    'ukuran' => $labelUkuran,
                    'keterangan' => $labelJenis,
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

            // Cara A: rate per-orang-per-menit dari target dasar, dikali
            // total menit kerja tim (jumlah orang aktual x jam rata-rata).
            $ratePerOrgPerMenit = $menitNormal > 0
                ? ((float) $target->target / $menitNormal) / $orangNormal
                : 0.0;

            $targetAdj = $ratePerOrgPerMenit * $totalMenitUntukTarget;
            $capaian = $targetAdj > 0 ? ($g['hasil'] / $targetAdj) * 100 : 0.0;
            $biayaPerUnit = (float) $target->potongan;

            $sumCapaian += $capaian;
            $sumNilai += $targetAdj * $biayaPerUnit;
            $sumTargetAdj += $targetAdj;
            $sumHasilBerTarget += $g['hasil'];
            $jumlahAda++;

            $perBarang[] = [
                'ukuran' => $labelUkuran,
                'keterangan' => $labelJenis,
                'hasil' => $g['hasil'],
                'target' => $targetAdj,
                'target_normal' => (float) $target->target,
                'selisih' => $g['hasil'] - $targetAdj,
                'capaian_persen' => $capaian,
                'has_target' => true,
            ];
        }

        /* 4. Potongan tim -> dibagi rata, kelipatan 500 */
        $potonganTotalTim = 0.0;
        $potonganPerOrang = 0.0;

        if ($jumlahAda > 0 && $jumlahPekerja > 0) {
            $nilaiSatuHariPenuh = $sumNilai / $jumlahAda;
            $kekuranganPersen = max(0, 100 - $sumCapaian) / 100;
            $potonganTotalTim = $kekuranganPersen * $nilaiSatuHariPenuh;
            $potonganPerOrang = round(($potonganTotalTim / $jumlahPekerja) / 500) * 500;
        }

        $potonganPerPegawai = [];
        foreach ($daftarPekerja as $idPegawai => &$p) {
            $p['pot_target'] = (int) $potonganPerOrang;
            $potonganPerPegawai[$idPegawai] = (int) $potonganPerOrang;
        }
        unset($p);

        return [
            'id_produksi' => $produksi->id,
            'tanggal' => $tanggal,
            'shift' => ucfirst((string) ($produksi->shift ?? '-')),
            'jumlah_pekerja' => $jumlahPekerja,
            'jam_aktual_rata' => round($jamRataRata, 2),
            'per_barang' => $perBarang,
            'punya_target' => $jumlahAda > 0,
            'capaian_global' => $sumCapaian,
            'target_total' => $sumTargetAdj,
            'hasil_total' => $sumHasilBerTarget,
            'selisih_total' => $sumHasilBerTarget - $sumTargetAdj,
            'potongan_total_tim' => $potonganTotalTim,
            'potongan_per_orang' => (int) $potonganPerOrang,
            'potongan_melebihi_gaji' => $totalGajiTim > 0 && $potonganTotalTim > $totalGajiTim,
            'total_gaji_tim' => $totalGajiTim,
            'pekerja' => array_values($daftarPekerja),
            'potongan_per_pegawai' => $potonganPerPegawai,
        ];
    }

    private static function hitungMenitBersih($masuk, $pulang): ?float
    {
        if (! $masuk || ! $pulang) {
            return null;
        }

        $m = Carbon::parse(Carbon::parse($masuk)->format('H:i'));
        $p = Carbon::parse(Carbon::parse($pulang)->format('H:i'));
        if ($p->lessThan($m)) {
            $p->addDay();
        }

        return (float) max(0, $m->diffInMinutes($p) - self::ISTIRAHAT_MENIT);
    }

    private static function formatUkuran($u): string
    {
        return rtrim(rtrim(number_format((float) $u->panjang, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.');
    }
}