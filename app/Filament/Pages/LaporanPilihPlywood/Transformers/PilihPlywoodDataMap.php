<?php

namespace App\Filament\Pages\LaporanPilihPlywood\Transformers;

use App\Filament\Pages\LaporanPilihPlywood\Queries\LoadLaporanPilihPlywood;
use App\Models\ProduksiPilihPlywood;
use App\Services\Target\Resolvers\PilihPlywoodTargetResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hitung target & potongan Pilih Plywood PER PASANGAN pegawai.
 *
 * PENTING: hasil dicatat per pasangan spesifik lewat relasi
 * HasilPilihPlywood::pegawais() (lihat "Group by Pegawai" di halaman
 * Hasil Pilih Plywood) — bukan digabung semua pekerja yang hadir hari
 * itu. Satu hari bisa punya beberapa pasangan berbeda, masing-masing
 * mengerjakan barang yang berbeda pula. Karena itu setiap pasangan
 * dihitung sebagai unit target sendiri, terpisah dari pasangan lain,
 * supaya target tidak ikut membengkak akibat digabung dengan orang
 * yang sebenarnya tidak ikut mengerjakan barang tersebut.
 *
 * - Target dari mesin "PILIH DAN TEMBEL": FLAT 2 kategori (bukan per
 *   tebal lagi) - "Pilih 5s" (tebal 5mm + jenis kayu Sengon) dan
 *   "Pilih Mebel" (barang lainnya).
 * - Target DISKALAKAN ke jumlah orang & jam kerja pasangan itu sendiri
 *   (bukan seluruh tim hari itu).
 * - Hasil dipakai adalah 'jumlah_bagus' (bukan 'jumlah', yang termasuk cacat).
 * - Baris hasil tanpa pasangan tercatat (pegawais kosong) dikelompokkan
 *   terpisah sebagai "Tanpa pasangan tercatat" dan tidak dipotong
 *   (tidak jelas siapa yang harus menanggung).
 */
class PilihPlywoodDataMap
{
    private const ISTIRAHAT_MENIT = 60;

    /**
     * @return array<int, array> satu elemen per pasangan (bisa lebih dari
     *                           satu per tanggal kalau ada beberapa pasangan)
     */
    public static function make($collection): array
    {
        $out = [];
        foreach ($collection as $produksi) {
            foreach (self::makeProduksi($produksi) as $blok) {
                $out[] = $blok;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array> satu elemen per pasangan dalam produksi ini
     */
    public static function makeProduksi(ProduksiPilihPlywood $produksi): array
    {
        $produksi->loadMissing(LoadLaporanPilihPlywood::RELASI);

        $resolver = new PilihPlywoodTargetResolver;
        $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

        // Data absen (jam masuk/pulang) per id_pegawai, untuk dicocokkan
        // ke pasangan yang mengerjakan barang.
        $absenPerPegawai = [];
        foreach ($produksi->pegawaiPilihPlywood as $pp) {
            if (! $pp->pegawai) {
                continue;
            }
            $idPegawai = (string) ($pp->id_pegawai ?? $pp->pegawai->id);
            $absenPerPegawai[$idPegawai] = [
                'id' => $pp->pegawai->kode_pegawai ?? '-',
                'nama' => $pp->pegawai->nama_pegawai ?? '-',
                'gaji' => (float) ($pp->pegawai->gaji ?? 0),
                'jam_masuk' => $pp->masuk ? Carbon::parse($pp->masuk)->format('H:i') : '-',
                'jam_pulang' => $pp->pulang ? Carbon::parse($pp->pulang)->format('H:i') : '-',
                'ijin' => $pp->ijin ?? '-',
                'keterangan' => $pp->ket ?? '-',
                'menit' => self::hitungMenitBersih($pp->masuk, $pp->pulang),
            ];
        }

        // Kelompokkan hasil per PASANGAN (kombinasi id pegawai yang sama).
        $pasanganMap = [];
        foreach ($produksi->hasilPilihPlywood as $hasil) {
            $idsPasangan = $hasil->pegawais->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $key = empty($idsPasangan) ? '__tanpa_pasangan__' : implode(',', $idsPasangan);

            if (! isset($pasanganMap[$key])) {
                $pasanganMap[$key] = [
                    'ids' => $idsPasangan,
                    'hasil' => [],
                ];
            }
            $pasanganMap[$key]['hasil'][] = $hasil;
        }

        $blokList = [];

        foreach ($pasanganMap as $key => $pasangan) {
            $blokList[] = self::hitungBlokPasangan(
                $produksi,
                $tanggal,
                $pasangan['ids'],
                $pasangan['hasil'],
                $absenPerPegawai,
                $resolver,
                $key === '__tanpa_pasangan__'
            );
        }

        return $blokList;
    }

    private static function hitungBlokPasangan(
        ProduksiPilihPlywood $produksi,
        string $tanggal,
        array $idsPasangan,
        array $hasilList,
        array $absenPerPegawai,
        PilihPlywoodTargetResolver $resolver,
        bool $tanpaPasangan
    ): array {
        /* 1. Pekerja pasangan ini + menit kerja */
        $daftarPekerja = [];
        $totalMenit = 0.0;
        $totalGajiPasangan = 0.0;

        foreach ($idsPasangan as $idPegawai) {
            $a = $absenPerPegawai[$idPegawai] ?? null;
            if (! $a) {
                continue;
            }
            $totalGajiPasangan += $a['gaji'];
            if ($a['menit'] !== null) {
                $totalMenit += $a['menit'];
            }
            $daftarPekerja[$idPegawai] = [
                'id' => $a['id'],
                'nama' => $a['nama'],
                'jam_masuk' => $a['jam_masuk'],
                'jam_pulang' => $a['jam_pulang'],
                'ijin' => $a['ijin'],
                'keterangan' => $a['keterangan'],
            ];
        }

        $jumlahPekerja = count($daftarPekerja);

        /* 2. Kelompokkan hasil pasangan ini per KATEGORI (5s Sengon vs mebel) */
        $grup = [];
        foreach ($hasilList as $hasil) {
            $b = $hasil->barangSetengahJadiHp;
            $ukuran = $b?->ukuran;
            $kategori = $b?->grade?->kategoriBarang;
            $tebal = $ukuran?->tebal;
            $jenis = $b?->jenisBarang?->nama_jenis_barang;

            $is5s = $tebal !== null
                && abs(round((float) $tebal, 2) - 5.00) < 0.01
                && $jenis !== null
                && str_contains(strtolower(trim($jenis)), 'sengon');

            $key = $is5s ? '5s' : 'mebel';

            if (! isset($grup[$key])) {
                $grup[$key] = [
                    'label' => $is5s ? 'Pilih 5s (Sengon)' : 'Pilih Mebel',
                    'is_5s' => $is5s,
                    'tebal' => $tebal,
                    'kategori' => $kategori?->nama_kategori ?? '-',
                    'jenis' => $jenis,
                    'grade' => $b?->grade?->nama_grade,
                    'ukuran_list' => [],
                    'hasil' => 0.0,
                ];
            }

            $grup[$key]['hasil'] += (float) ($hasil->jumlah_bagus ?? 0);
            if ($ukuran) {
                $grup[$key]['ukuran_list'][] = self::formatUkuran($ukuran);
            }
        }

        /* 3. Target per grup, diskalakan ke jumlah orang & jam PASANGAN INI */
        $perBarang = [];
        $sumCapaian = 0.0;
        $sumNilai = 0.0;
        $jumlahAda = 0;
        $sumTargetAdj = 0.0;
        $sumHasilBerTarget = 0.0;

        foreach ($grup as $g) {
            $target = $tanpaPasangan ? null : $resolver->resolve($g['tebal'], $g['jenis']);
            $labelUkuran = implode(', ', array_unique($g['ukuran_list'])) ?: '-';
            $keterangan = $g['label'].' - '.($g['jenis'] ?? '-').' / '.($g['grade'] ?? '-').' ('.$g['kategori'].')';

            if (! $target) {
                if (! $tanpaPasangan) {
                    Log::warning('Target Pilih Plywood tidak ditemukan', [
                        'id_produksi' => $produksi->id,
                        'tebal' => $g['tebal'],
                    ]);
                }

                $perBarang[] = [
                    'ukuran' => $labelUkuran,
                    'keterangan' => $keterangan,
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

            // Diskalakan ke total menit kerja PASANGAN INI SAJA
            // (jumlah orang dalam pasangan x menit kerja masing-masing).
            $targetAdj = $ratePerOrgPerMenit * $totalMenit;
            $capaian = $targetAdj > 0 ? ($g['hasil'] / $targetAdj) * 100 : 0.0;
            $biayaPerUnit = (float) $target->potongan;

            $sumCapaian += $capaian;
            $sumNilai += $targetAdj * $biayaPerUnit;
            $sumTargetAdj += $targetAdj;
            $sumHasilBerTarget += $g['hasil'];
            $jumlahAda++;

            $perBarang[] = [
                'ukuran' => $labelUkuran,
                'keterangan' => $keterangan,
                'hasil' => $g['hasil'],
                'target' => $targetAdj,
                'target_normal' => (float) $target->target,
                'selisih' => $g['hasil'] - $targetAdj,
                'capaian_persen' => $capaian,
                'has_target' => true,
            ];
        }

        /* 4. Potongan pasangan -> dibagi rata ke anggota pasangan, kelipatan 500 */
        $potonganTotalPasangan = 0.0;
        $potonganPerOrang = 0.0;

        if ($jumlahAda > 0 && $jumlahPekerja > 0) {
            $nilaiSatuHariPenuh = $sumNilai / $jumlahAda;
            $kekuranganPersen = max(0, 100 - $sumCapaian) / 100;
            $potonganTotalPasangan = $kekuranganPersen * $nilaiSatuHariPenuh;
            $potonganPerOrang = round(($potonganTotalPasangan / $jumlahPekerja) / 500) * 500;
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
            'tanpa_pasangan' => $tanpaPasangan,
            'jumlah_pekerja' => $jumlahPekerja,
            'jam_aktual_rata' => $jumlahPekerja > 0 ? round($totalMenit / $jumlahPekerja / 60, 2) : 0,
            'per_barang' => $perBarang,
            'punya_target' => $jumlahAda > 0,
            'capaian_global' => $sumCapaian,
            'target_total' => $sumTargetAdj,
            'hasil_total' => $sumHasilBerTarget,
            'selisih_total' => $sumHasilBerTarget - $sumTargetAdj,
            'potongan_total_tim' => $potonganTotalPasangan,
            'potongan_per_orang' => (int) $potonganPerOrang,
            'potongan_melebihi_gaji' => $totalGajiPasangan > 0 && $potonganTotalPasangan > $totalGajiPasangan,
            'total_gaji_tim' => $totalGajiPasangan,
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
            .' x '.rtrim(rtrim(number_format((float) $u->lebar, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.');
    }
}