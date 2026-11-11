<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahLog;
use App\Models\StokVeneerKering;
use Carbon\Carbon;

/**
 * HppGlobalService
 *
 * HPP GLOBAL (bukan per ukuran) untuk menu HPP Direksi.
 *
 * Kategori berdasarkan tebal (mm):
 *   - fb   (Face/Back) : tebal < 1
 *   - core (Core)      : tebal >= 1
 *
 * Angka utama  = HPP PRODUKSI bulan itu: rata-rata biaya veneer yang MASUK pada bulan itu,
 *                dibobot m³ (stok lama tidak ikut).
 * Angka kecil  = HPP STOK GUDANG pada akhir bulan: Σ nilai stok ÷ Σ stok m³ (HPP Average gabungan).
 *
 * Menambah tahap baru (veneer jadi, triplek, dst.): buat method bulanan + stok seperti
 * veneerBasah()/veneerKering() lalu panggil susun() — bagian tampilan tidak perlu diubah.
 */
class HppGlobalService
{
    /** Tebal (mm) mulai dari angka ini masuk Core. */
    public const BATAS_CORE = 1.0;

    public const KATEGORI = [
        'fb'   => 'Face / Back',
        'core' => 'Core',
    ];

    public const JUMLAH_BULAN_TREN = 6;

    /** Ekspresi SQL pembagi kategori dari sebuah kolom tebal. */
    protected function kategoriSql(string $kolomTebal): string
    {
        $batas = number_format(self::BATAS_CORE, 2, '.', '');

        return "CASE WHEN ROUND($kolomTebal, 2) >= $batas THEN 'core' ELSE 'fb' END";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Daftar bulan yang punya data (gabungan semua tahap)
    // ─────────────────────────────────────────────────────────────────────

    public function bulanTersedia(): array
    {
        $basah = HppVeneerBasahLog::query()
            ->where('tipe_transaksi', 'masuk')
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m') AS b")
            ->distinct()
            ->pluck('b');

        $kering = StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->where('hpp_kering_per_m3', '>', 0)
            ->selectRaw("DATE_FORMAT(tanggal_transaksi, '%Y-%m') AS b")
            ->distinct()
            ->pluck('b');

        return $basah->merge($kering)->filter()->unique()->sortDesc()->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Semua kartu untuk satu bulan
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array> tahap => [judul, deskripsi, kategori => kartu]
     */
    public function semuaTahap(string $bulan): array
    {
        $akhir = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->endOfMonth();
        $mulai = $akhir->copy()->startOfMonth()->subMonthsNoOverflow(self::JUMLAH_BULAN_TREN - 1);

        $daftarBulan = [];
        for ($i = 0; $i < self::JUMLAH_BULAN_TREN; $i++) {
            $daftarBulan[] = $mulai->copy()->addMonthsNoOverflow($i)->format('Y-m');
        }

        return [
            'basah' => [
                'judul'     => 'Veneer Basah',
                'deskripsi' => 'Biaya membuat 1 m³ veneer di rotary (kayu + pekerja + mesin + bahan penolong).',
                'kartu'     => $this->susun(
                    $this->basahBulanan($mulai->toDateString(), $akhir->toDateString()),
                    $this->basahStok($akhir->toDateString()),
                    $daftarBulan,
                    $bulan,
                    [
                        'kayu'    => 'Kayu',
                        'pekerja' => 'Pekerja',
                        'mesin'   => 'Mesin',
                        'bahan'   => 'Bahan penolong',
                    ],
                    true
                ),
            ],
            'kering' => [
                'judul'     => 'Veneer Kering',
                'deskripsi' => 'Biaya 1 m³ veneer setelah dryer (HPP veneer basah + ongkos dryer).',
                'kartu'     => $this->susun(
                    $this->keringBulanan($mulai->toDateString(), $akhir->toDateString()),
                    $this->keringStok($akhir->toDateString()),
                    $daftarBulan,
                    $bulan,
                    [
                        'basah'  => 'Veneer basah',
                        'ongkos' => 'Ongkos dryer',
                    ],
                    false
                ),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Veneer BASAH
    // ─────────────────────────────────────────────────────────────────────

    /** Rata-rata masuk per bulan & kategori: [bulan][kat] => ['m3','palet','m3_sementara','n'=>[komponen => Rp×m³]] */
    protected function basahBulanan(string $mulai, string $akhir): array
    {
        $komp = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0))';
        $kat  = $this->kategoriSql('tebal');

        $rows = HppVeneerBasahLog::query()
            ->where('tipe_transaksi', 'masuk')
            ->where('referensi_type', DetailHasilPaletRotary::class)
            ->whereRaw("$komp > 0")
            ->whereBetween('tanggal', [$mulai, $akhir])
            ->selectRaw("
                DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
                $kat AS kat,
                COUNT(*) AS palet,
                SUM(total_kubikasi) AS m3,
                SUM(CASE WHEN keterangan LIKE '%[HPP SEMENTARA]%' THEN total_kubikasi ELSE 0 END) AS m3_sementara,
                SUM(COALESCE(hpp_kayu,0)           * total_kubikasi) AS n_kayu,
                SUM(COALESCE(hpp_pekerja,0)        * total_kubikasi) AS n_pekerja,
                SUM(COALESCE(hpp_mesin,0)          * total_kubikasi) AS n_mesin,
                SUM(COALESCE(hpp_bahan_penolong,0) * total_kubikasi) AS n_bahan,
                SUM($komp * total_kubikasi)                          AS n_total
            ")
            ->groupBy('bulan', 'kat')
            ->get();

        return $this->petakan($rows, ['kayu', 'pekerja', 'mesin', 'bahan', 'total']);
    }

    /** Stok gudang akhir bulan per kategori: [kat] => ['m3','nilai'] */
    protected function basahStok(string $sampaiTanggal): array
    {
        // Log terakhir tiap kombinasi (jenis kayu, ukuran, KW) sampai akhir bulan
        $ids = HppVeneerBasahLog::query()
            ->where('tanggal', '<=', $sampaiTanggal)
            ->selectRaw('MAX(id) AS id')
            ->groupBy('id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $kat = $this->kategoriSql('tebal');
        $out = [];

        foreach ($ids->chunk(2000) as $potong) {
            $rows = HppVeneerBasahLog::query()
                ->whereIn('id', $potong)
                ->where('stok_kubikasi_after', '>', 0)
                ->selectRaw("$kat AS kat, SUM(stok_kubikasi_after) AS m3, SUM(nilai_stok_after) AS nilai")
                ->groupBy('kat')
                ->get();

            foreach ($rows as $r) {
                $out[$r->kat]['m3']    = ($out[$r->kat]['m3'] ?? 0) + (float) $r->m3;
                $out[$r->kat]['nilai'] = ($out[$r->kat]['nilai'] ?? 0) + (float) $r->nilai;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Veneer KERING
    // ─────────────────────────────────────────────────────────────────────

    protected function keringBulanan(string $mulai, string $akhir): array
    {
        $kat = $this->kategoriSql('ukurans.tebal');

        $rows = StokVeneerKering::query()
            ->join('ukurans', 'ukurans.id', '=', 'stok_veneer_kerings.id_ukuran')
            ->where('stok_veneer_kerings.jenis_transaksi', 'masuk')
            ->where('stok_veneer_kerings.hpp_kering_per_m3', '>', 0)
            ->whereBetween('stok_veneer_kerings.tanggal_transaksi', [$mulai, $akhir])
            ->selectRaw("
                DATE_FORMAT(stok_veneer_kerings.tanggal_transaksi, '%Y-%m') AS bulan,
                $kat AS kat,
                COUNT(*) AS palet,
                SUM(stok_veneer_kerings.m3) AS m3,
                0 AS m3_sementara,
                SUM(COALESCE(stok_veneer_kerings.hpp_veneer_basah_per_m3,0) * stok_veneer_kerings.m3) AS n_basah,
                SUM(COALESCE(stok_veneer_kerings.ongkos_dryer_per_m3,0)     * stok_veneer_kerings.m3) AS n_ongkos,
                SUM(stok_veneer_kerings.hpp_kering_per_m3                   * stok_veneer_kerings.m3) AS n_total
            ")
            ->groupBy('bulan', 'kat')
            ->get();

        return $this->petakan($rows, ['basah', 'ongkos', 'total']);
    }

    protected function keringStok(string $sampaiTanggal): array
    {
        $ids = StokVeneerKering::query()
            ->where('tanggal_transaksi', '<=', $sampaiTanggal)
            ->selectRaw('MAX(id) AS id')
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $kat = $this->kategoriSql('ukurans.tebal');
        $out = [];

        foreach ($ids->chunk(2000) as $potong) {
            $rows = StokVeneerKering::query()
                ->join('ukurans', 'ukurans.id', '=', 'stok_veneer_kerings.id_ukuran')
                ->whereIn('stok_veneer_kerings.id', $potong)
                ->where('stok_veneer_kerings.stok_m3_sesudah', '>', 0)
                ->selectRaw("$kat AS kat, SUM(stok_veneer_kerings.stok_m3_sesudah) AS m3, SUM(stok_veneer_kerings.nilai_stok_sesudah) AS nilai")
                ->groupBy('kat')
                ->get();

            foreach ($rows as $r) {
                $out[$r->kat]['m3']    = ($out[$r->kat]['m3'] ?? 0) + (float) $r->m3;
                $out[$r->kat]['nilai'] = ($out[$r->kat]['nilai'] ?? 0) + (float) $r->nilai;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Pembantu bersama
    // ─────────────────────────────────────────────────────────────────────

    protected function petakan($rows, array $kunci): array
    {
        $peta = [];

        foreach ($rows as $r) {
            $n = [];
            foreach ($kunci as $k) {
                $n[$k] = (float) ($r->{'n_'.$k} ?? 0);
            }

            $peta[$r->bulan][$r->kat] = [
                'm3'           => (float) $r->m3,
                'palet'        => (int) $r->palet,
                'm3_sementara' => (float) $r->m3_sementara,
                'n'            => $n,
            ];
        }

        return $peta;
    }

    /**
     * Susun kartu per kategori (fb, core) dari data bulanan + stok.
     *
     * @param array<string,string> $komponen kunci => label (urutan tampil)
     */
    protected function susun(
        array $bulanan,
        array $stok,
        array $daftarBulan,
        string $bulan,
        array $komponen,
        bool $punyaEstimasi
    ): array {
        $bulanLalu = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->subMonthNoOverflow()->format('Y-m');
        $hasil = [];

        foreach (self::KATEGORI as $kat => $labelKat) {
            $tren = [];
            foreach ($daftarBulan as $b) {
                $d = $bulanan[$b][$kat] ?? null;
                $tren[] = [
                    'bulan' => $b,
                    'total' => $d && $d['m3'] > 0 ? $d['n']['total'] / $d['m3'] : null,
                ];
            }

            $now  = $bulanan[$bulan][$kat] ?? null;
            $prev = $bulanan[$bulanLalu][$kat] ?? null;

            $total     = $now && $now['m3'] > 0 ? $now['n']['total'] / $now['m3'] : null;
            $totalLalu = $prev && $prev['m3'] > 0 ? $prev['n']['total'] / $prev['m3'] : null;

            $rincian = [];
            if ($now && $now['m3'] > 0) {
                foreach ($komponen as $k => $label) {
                    $rincian[] = ['label' => $label, 'nilai' => $now['n'][$k] / $now['m3']];
                }
            }

            $s = $stok[$kat] ?? null;

            $hasil[$kat] = [
                'label'      => $labelKat,
                'total'      => $total,
                'm3'         => $now['m3'] ?? 0.0,
                'palet'      => $now['palet'] ?? 0,
                'selisih'    => ($total !== null && $totalLalu !== null) ? $total - $totalLalu : null,
                'selisih_pct'=> ($total !== null && $totalLalu) ? ($total - $totalLalu) / $totalLalu * 100 : null,
                'rincian'    => $rincian,
                'estimasi_pct' => ($punyaEstimasi && $now && $now['m3'] > 0)
                    ? $now['m3_sementara'] / $now['m3'] * 100
                    : null,
                'stok_hpp'   => ($s && $s['m3'] > 0) ? $s['nilai'] / $s['m3'] : null,
                'stok_m3'    => $s['m3'] ?? 0.0,
                'tren'       => $tren,
            ];
        }

        return $hasil;
    }
}