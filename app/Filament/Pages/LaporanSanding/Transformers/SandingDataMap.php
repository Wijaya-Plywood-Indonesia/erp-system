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
 * - Target dari master Target: FLAT per mesin (Besar/Kecil) + shift (Pagi/Malam),
 *   tidak lagi dibedakan per ukuran/tebal/jenis kayu/kategori barang.
 * - Target ADJUSTED ke man-minutes aktual tim (rate per-orang-per-menit x total menit).
 * - Semua hasil (berapapun ukuran/tebalnya) DIJUMLAH jadi satu total, lalu
 *   dibandingkan ke satu target global shift ini; potongan tim =
 *   (100% - capaian global) x nilai satu hari penuh, dibagi RATA ke semua
 *   pekerja, dibulatkan kelipatan 500 (strategi Kolektif).
 * - Produksi tanpa baris target (mesin+shift) di master -> punya_target=false,
 *   tidak ikut dihitung potongannya.
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
        $shift = $produksi->shift;

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

        /* 3. Target flat per mesin + shift (bukan per ukuran/kategori lagi).
         * Baris per_ukuran di bawah ini murni untuk tampilan breakdown hasil
         * per ukuran/jenis kayu/kategori - tidak ada target individual per
         * baris, semua dibandingkan ke SATU target global shift ini. */
        $perUkuran = [];
        $totalHasilSemua = 0.0;

        foreach ($grup as $g) {
            $labelUkuran = implode(', ', array_unique($g['ukuran_list'])) ?: '-';
            $labelJenis = implode(', ', array_unique($g['jenis_list'])) ?: '-';
            $labelGrade = implode(', ', array_unique($g['grade_list'])) ?: '-';

            $totalHasilSemua += $g['hasil'];

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
        }

        $target = $resolver->resolve($idMesin, $shift);
        $jumlahGrupAda = 0;
        $sumCapaian = 0.0;
        $sumTargetAdj = 0.0;
        $targetNormal = null;
        $orangNormalTarget = null;
        $jamNormalTarget = null;
        $sumHasilBerTarget = 0.0;
        $potonganTotalTim = 0.0;
        $potonganPerOrang = 0.0;

        if (! $target) {
            Log::warning('Target Sanding tidak ditemukan', [
                'id_produksi' => $produksi->id,
                'id_mesin' => $idMesin,
                'shift' => $shift,
            ]);
        } else {
            $menitNormal = ((float) $target->jam) * 60;
            $orangNormal = (int) $target->orang;
            $ratePerOrgPerMenit = ($menitNormal > 0 && $orangNormal > 0)
                ? ((float) $target->target / $menitNormal) / $orangNormal
                : 0.0;

            $targetAdj = $ratePerOrgPerMenit * $totalMenit;
            $capaian = $targetAdj > 0 ? ($totalHasilSemua / $targetAdj) * 100 : 0.0;
            $biayaPerUnit = (float) $target->potongan;
            $nilaiSatuHariPenuh = $targetAdj * $biayaPerUnit;

            $sumCapaian = $capaian;
            $sumTargetAdj = $targetAdj;
            $targetNormal = (float) $target->target;
            $orangNormalTarget = $orangNormal;
            $jamNormalTarget = (float) $target->jam;
            $sumHasilBerTarget = $totalHasilSemua;
            $jumlahGrupAda = 1;

            /* 4. Potongan tim -> dibagi rata (Kolektif), kelipatan 500.
             * Potongan per orang dibulatkan dulu ke kelipatan 500, lalu
             * potongan total tim dihitung ULANG dari angka yang sudah
             * dibulatkan itu, supaya angka total (header/footer) dan
             * angka per pekerja selalu konsisten. */
            if ($jumlahPekerja > 0) {
                $kekuranganPersen = max(0, 100 - $capaian) / 100;
                $potonganTotalTimMentah = $kekuranganPersen * $nilaiSatuHariPenuh;
                $potonganPerOrang = round(($potonganTotalTimMentah / $jumlahPekerja) / 500) * 500;
                $potonganTotalTim = $potonganPerOrang * $jumlahPekerja;
            }
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
            'target_normal' => $targetNormal,
            'target_normal_orang' => $orangNormalTarget,
            'target_normal_jam' => $jamNormalTarget,
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