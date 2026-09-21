<?php

namespace App\Filament\Pages\LaporanSanding\Transformers;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\Filament\Pages\LaporanSanding\Queries\LoadLaporanSanding;
use App\Models\ProduksiSanding;
use App\Services\Target\Resolvers\SandingTargetResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hitung target & potongan Sanding per PRODUKSI (1 produksi = 1 mesin
 * Besar/Kecil + 1 shift, sesuai tabel produksi_sandings).
 *
 * - Target dari master Target: mesin (id_mesin produksi) + tebal + kategori barang.
 * - Target ADJUSTED ke man-minutes aktual tim (rate per-orang-per-menit x total menit).
 * - Beberapa tebal/kategori dalam 1 produksi: capaian tiap grup DIJUMLAH, lalu
 *   potongan tim = (100% - capaian global) x nilai satu hari penuh
 *   (rata-rata nilai target antar grup), dibagi RATA ke semua pekerja,
 *   dibulatkan kelipatan 500 (strategi Kolektif).
 * - Grup tanpa target -> has_target=false, tidak ikut dihitung.
 */
class SandingDataMap
{
    /** Asumsi sama dengan Sanding Joint: istirahat 60 menit dari jam kotor. */
    private const ISTIRAHAT_MENIT = 60;

    /**
     * @return array<int, array>  satu elemen per produksi
     */
    public static function make($collection): array
    {
        $out = [];
        foreach ($collection as $produksi) {
            $out[] = self::makeProduksi($produksi);
        }

        return $out;
    }

    public static function makeProduksi(ProduksiSanding $produksi): array
    {
        $produksi->loadMissing(LoadLaporanSanding::RELASI);

        $resolver = new SandingTargetResolver;
        $tanggal = Carbon::parse($produksi->tanggal)->format('d/m/Y');
        $idMesin = (int) $produksi->id_mesin;

        /* 1. Pekerja & menit kerja bersih */
        $pekerjaInput = [];
        $daftarPekerja = [];
        $totalMenit = 0.0;
        $totalGajiTim = 0.0;

        foreach ($produksi->pegawaiSandings as $ps) {
            if (! $ps->pegawai) {
                continue;
            }

            $totalGajiTim += (float) ($ps->pegawai->gaji ?? 0);
            $menit = self::hitungMenitBersih($ps->masuk, $ps->pulang);
            $idPegawai = (string) ($ps->id_pegawai ?? $ps->pegawai->id);

            if ($menit !== null) {
                $totalMenit += $menit;
            }

            $pekerjaInput[] = new PekerjaKerjaInput(idPegawai: $idPegawai, menitKerja: (float) ($menit ?? 0));
            $daftarPekerja[$idPegawai] = [
                'id' => $ps->pegawai->kode_pegawai ?? '-',
                'nama' => $ps->pegawai->nama_pegawai ?? '-',
                'jam_masuk' => $ps->masuk ? Carbon::parse($ps->masuk)->format('H:i') : '-',
                'jam_pulang' => $ps->pulang ? Carbon::parse($ps->pulang)->format('H:i') : '-',
                'jam_aktual_bersih' => $menit !== null ? round($menit / 60, 2) : null,
                'ijin' => $ps->ijin ?? '-',
                'keterangan' => $ps->ket ?? '-',
            ];
        }

        $jumlahPekerja = count($pekerjaInput);

        /* 2. Grup hasil per tebal + kategori */
        $grup = [];
        foreach ($produksi->hasilSandings as $hasil) {
            $b = $hasil->barangSetengahJadi;
            $ukuran = $b?->ukuran;
            $kategori = $b?->grade?->kategoriBarang;

            $tebal = $ukuran?->tebal;
            $idKategori = $kategori?->id;
            $key = ($tebal ?? 'x').'|'.($idKategori ?? 'x');

            if (! isset($grup[$key])) {
                $grup[$key] = [
                    'tebal' => $tebal,
                    'id_kategori' => $idKategori,
                    'kategori' => $kategori?->nama_kategori ?? '-',
                    'ukuran_list' => [],
                    'jenis_list' => [],
                    'grade_list' => [],
                    'hasil' => 0.0,
                ];
            }

            $grup[$key]['hasil'] += (float) ($hasil->kuantitas ?? 0);

            if ($ukuran) {
                $grup[$key]['ukuran_list'][] = self::formatUkuran($ukuran);
            }
            if ($b?->jenisBarang?->nama_jenis_barang) {
                $grup[$key]['jenis_list'][] = $b->jenisBarang->nama_jenis_barang;
            }
            if ($b?->grade?->nama_grade) {
                $grup[$key]['grade_list'][] = $b->grade->nama_grade;
            }
        }

        /* 3. Target per grup */
        $perUkuran = [];
        $sumCapaian = 0.0;
        $sumNilai = 0.0;
        $jumlahGrupAda = 0;
        $sumTargetAdj = 0.0;
        $sumHasilBerTarget = 0.0;

        foreach ($grup as $g) {
            $target = $resolver->resolve($idMesin, $g['tebal'], $g['id_kategori']);
            $labelUkuran = implode(', ', array_unique($g['ukuran_list'])) ?: '-';
            $labelJenis = implode(', ', array_unique($g['jenis_list'])) ?: '-';
            $labelGrade = implode(', ', array_unique($g['grade_list'])) ?: '-';

            if (! $target) {
                Log::warning('Target Sanding tidak ditemukan', [
                    'id_produksi' => $produksi->id,
                    'id_mesin' => $idMesin,
                    'tebal' => $g['tebal'],
                    'id_kategori' => $g['id_kategori'],
                ]);

                $perUkuran[] = [
                    'ukuran' => $labelUkuran,
                    'jenis_kayu' => $labelJenis,
                    'tebal' => $g['tebal'],
                    'kategori' => $g['kategori'],
                    'grade' => $labelGrade,
                    'kode_ukuran' => 'SANDING '.($g['tebal'] ?? '-').' '.$g['kategori'],
                    'hasil' => $g['hasil'],
                    'target' => 0,
                    'selisih' => $g['hasil'],
                    'capaian_persen' => null,
                    'has_target' => false,
                ];

                continue;
            }

            $menitNormal = ((float) $target->jam) * 60;
            $orangNormal = (int) $target->orang;
            $ratePerOrgPerMenit = ($menitNormal > 0 && $orangNormal > 0)
                ? ((float) $target->target / $menitNormal) / $orangNormal
                : 0.0;

            $targetAdj = $ratePerOrgPerMenit * $totalMenit;
            $capaian = $targetAdj > 0 ? ($g['hasil'] / $targetAdj) * 100 : 0.0;
            $biayaPerUnit = (float) $target->potongan;

            $sumCapaian += $capaian;
            $sumNilai += $targetAdj * $biayaPerUnit;
            $sumTargetAdj += $targetAdj;
            $sumHasilBerTarget += $g['hasil'];
            $jumlahGrupAda++;

            $perUkuran[] = [
                'ukuran' => $labelUkuran,
                'jenis_kayu' => $labelJenis,
                'tebal' => $g['tebal'],
                'kategori' => $g['kategori'],
                'grade' => $labelGrade,
                'kode_ukuran' => 'SANDING '.($g['tebal'] ?? '-').' '.$g['kategori'],
                'hasil' => $g['hasil'],
                'target' => $targetAdj,
                'target_normal' => (float) $target->target,
                'selisih' => $g['hasil'] - $targetAdj,
                'capaian_persen' => $capaian,
                'has_target' => true,
            ];
        }

        /* 4. Potongan tim -> dibagi rata (Kolektif), kelipatan 500 */
        $potonganTotalTim = 0.0;
        $potonganPerOrang = 0.0;

        if ($jumlahGrupAda > 0 && $jumlahPekerja > 0) {
            $nilaiSatuHariPenuh = $sumNilai / $jumlahGrupAda;
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
            'mesin' => $produksi->mesin?->nama_mesin ?? '-',
            'shift' => ucfirst((string) ($produksi->shift ?? '-')),
            'jumlah_pekerja' => $jumlahPekerja,
            'jam_aktual_rata' => $jumlahPekerja > 0 ? round($totalMenit / $jumlahPekerja / 60, 2) : 0,
            'per_ukuran' => $perUkuran,
            'punya_target' => $jumlahGrupAda > 0,
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

    /**
     * Menit kerja bersih = (pulang - masuk, lewat tengah malam ditangani)
     * dikurangi istirahat 60 menit. Null kalau jam masuk/pulang kosong.
     */
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
            .' x '.rtrim(rtrim(number_format((float) $u->lebar, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.');
    }
}