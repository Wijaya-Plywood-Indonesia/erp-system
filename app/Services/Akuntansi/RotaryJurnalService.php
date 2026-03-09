<?php

namespace App\Services\Akuntansi;

use App\Models\ProduksiRotary;
use App\Models\BahanPenolongRotary;
use App\Models\DetailHasilPaletRotary;
use App\Models\PenggunaanLahanRotary;
use App\Models\PegawaiRotary;
use App\Models\Mesin;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RotaryJurnalService
 *
 * Menghitung payload jurnal pembantu dari produksi rotary satu tanggal.
 *
 * ALUR:
 * 1. Terima tanggal produksi
 * 2. Ambil semua ProduksiRotary pada tanggal itu
 * 3. Cek apakah SEMUA mesin yang aktif sudah divalidasi (status = 'divalidasi' / 'disetujui')
 * 4. Jika belum semua → return null (jangan buat jurnal)
 * 5. Hitung nominal tiap akun
 * 6. Susun payload header + items terstruktur
 *
 * PEMETAAN AKUN (sesuai excel):
 * DEBIT:
 *   115-07  Veneer Basah F/B        → kubikasi_fb  * harga_veneer
 *   115-08  Veneer Basah CORE       → kubikasi_core * harga_veneer
 *   510-01  Upah Tenaga Kerja       → total ongkos pegawai
 *   520-08  Beban Kerugian Produksi → muncul jika total_debit < total_kredit (selisih)
 *   [akun bahan penolong]           → jika ada pemakaian bahan penolong
 *
 * KREDIT:
 *   115-01  Persediaan Kayu 130     → kubikasi * poin * 0.35 (kayu 130)
 *   115-02  Persediaan Kayu 260     → kubikasi * poin * 0.65 (kayu 260)  -- atau sesuai lahan
 *   210-02  Hutang Gaji             → sama dengan 510-01
 *   115-13  Persediaan Reeling Tape → jika ada bahan penolong reeling tape
 *   520-09  Keuntungan Hasil Produksi → muncul jika total_debit > total_kredit (selisih)
 *
 * PEMETAAN BAHAN PENOLONG (contoh):
 *   'reeling tape' / 'relling tape' → 115-13 (kredit)
 *   Bahan penolong lain → bisa dikembangkan
 */
class RotaryJurnalService
{
    // ─── Konstanta Kode Akun ─────────────────────────────────────────────────

    const AKUN = [
        'veneer_fb'          => ['kode' => '115-07', 'nama' => 'Veneer Basah F/B',             'map' => 'd'],
        'veneer_core'        => ['kode' => '115-08', 'nama' => 'Veneer Basah CORE',            'map' => 'd'],
        'upah_tk'            => ['kode' => '510-01', 'nama' => 'Upah Tenaga Kerja',            'map' => 'd'],
        'beban_kerugian'     => ['kode' => '520-08', 'nama' => 'Beban kerugian produksi',      'map' => 'd'],
        'kayu_130'           => ['kode' => '115-01', 'nama' => 'Persediaan Kayu 130',          'map' => 'k'],
        'kayu_260'           => ['kode' => '115-02', 'nama' => 'Persediaan Kayu 260',          'map' => 'k'],
        'hutang_gaji'        => ['kode' => '210-02', 'nama' => 'Hutang Gaji',                  'map' => 'k'],
        'reeling_tape'       => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape',      'map' => 'k'],
        'keuntungan_prod'    => ['kode' => '520-09', 'nama' => 'Keuntungan hasil produksi',    'map' => 'k'],
    ];

    // Mapping nama bahan penolong → kode akun (kredit)
    const BAHAN_PENOLONG_MAP = [
        'reeling tape'  => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
        'relling tape'  => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
        'reeling'       => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
        // Tambahkan bahan penolong lain di sini sesuai kebutuhan
    ];

    /**
     * Build payload jurnal untuk tanggal produksi tertentu.
     *
     * @param  string  $tanggal  Format: Y-m-d
     * @return array|null  null = belum semua mesin divalidasi
     */
    public function buildJurnalPayload(string $tanggal): ?array
    {
        $tgl = Carbon::parse($tanggal)->startOfDay();

        // ── 1. Ambil semua produksi pada tanggal ─────────────────────────────
        $produksiList = ProduksiRotary::with([
            'mesin',
            'detailValidasiHasilRotary',
            'detailPegawaiRotary.pegawai',
            'detailLahanRotary.lahan',
            'detailLahanRotary.jenisKayu',
            'detailPaletRotary.ukuran',
            'detailPaletRotary.penggunaanLahan.lahan',
            'bahanPenolongRotary',
        ])
            ->whereDate('tgl_produksi', $tgl)
            ->get();

        if ($produksiList->isEmpty()) {
            Log::info("RotaryJurnal: Tidak ada produksi pada tanggal {$tanggal}");
            return null;
        }

        // ── 2. Cek validasi semua mesin ───────────────────────────────────────
        foreach ($produksiList as $produksi) {
            $validated = $produksi->detailValidasiHasilRotary
                ->whereIn('status', ['divalidasi', 'disetujui'])
                ->count();

            if ($validated === 0) {
                Log::info("RotaryJurnal: Mesin [{$produksi->mesin->nama_mesin}] belum divalidasi. Jurnal ditunda.");
                return null;
            }
        }

        // ── 3. Hitung semua nominal ───────────────────────────────────────────
        $calc = $this->hitungNominal($produksiList, $tgl);

        // ── 4. Susun struktur jurnal header + items ───────────────────────────
        return $this->buildStructure($tgl, $produksiList, $calc);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  KALKULASI
    // ─────────────────────────────────────────────────────────────────────────

    private function hitungNominal(Collection $produksiList, Carbon $tgl): array
    {
        // ── Kubikasi veneer per jenis_hasil ──────────────────────────────────
        // Rumus kubikasi veneer: panjang * lebar * tebal * total_lembar / 10.000.000 (m³)
        // Kemudian dikali 65% → ini yang digunakan sebagai pembagi untuk harga veneer

        $kubikasiPerMesin = [];  // [id_produksi => ['fb' => ..., 'core' => ...]]
        $kubikasiTotalFB   = 0.0;
        $kubikasiTotalCore = 0.0;

        foreach ($produksiList as $produksi) {
            $jenis = strtolower($produksi->mesin->jenis_hasil ?? 'core');
            $kubikasi = 0.0;

            foreach ($produksi->detailPaletRotary as $palet) {
                $ukuran = $palet->ukuran;
                if (!$ukuran) continue;

                // panjang, lebar, tebal ada di tabel ukuran
                $vol = ($ukuran->panjang ?? 0)
                     * ($ukuran->lebar ?? 0)
                     * ($ukuran->tebal ?? 0)
                     * ($palet->total_lembar ?? 0)
                     / 10_000_000;

                $kubikasi += $vol;
            }

            $kubikasiPerMesin[$produksi->id] = [
                'jenis'    => $jenis,
                'kubikasi' => $kubikasi,
            ];

            if ($jenis === 'f/b') {
                $kubikasiTotalFB += $kubikasi;
            } else {
                $kubikasiTotalCore += $kubikasi;
            }
        }

        // ── Hitung poin kayu total ────────────────────────────────────────────
        // Rumus: poin = harga_beli * (p * d * d * jml_batang * 0.785 / 1.000.000) * 1000
        // Sumber: dari tabel penggunaan_lahan_rotaries → via harga_kayu
        // Sementara kita ambil dari lahan masing-masing produksi

        $poinKayu130 = 0.0;
        $poinKayu260 = 0.0;
        $detailKayuPerProduksi = [];  // untuk items jurnal pembantu

        foreach ($produksiList as $produksi) {
            foreach ($produksi->detailLahanRotary as $lahan) {
                $jenisKayu = $lahan->jenisKayu;
                $namaLahan = strtolower($lahan->lahan->nama_lahan ?? '');

                // Tentukan apakah kayu 130 atau 260 berdasarkan nama lahan
                // Konvensi: jika mengandung '130' → 115-01, '260' → 115-02
                // Sesuaikan logic ini dengan konvensi penamaan lahan di sistem Anda
                $isKayu130 = str_contains($namaLahan, '130');
                $isKayu260 = str_contains($namaLahan, '260') || !$isKayu130;

                // Untuk saat ini, nilai poin dihitung sementara (HPP kayu belum jadi)
                // Placeholder: ambil dari harga_kayu jika sudah ada, else 0
                $poin = $this->getPoinKayuFromLahan($lahan);

                $detailKayuPerProduksi[$produksi->id][] = [
                    'id_lahan'       => $lahan->id_lahan,
                    'nama_lahan'     => $lahan->lahan->nama_lahan ?? '-',
                    'kode_lahan'     => $lahan->lahan->kode_lahan ?? '-',
                    'id_jenis_kayu'  => $lahan->id_jenis_kayu,
                    'nama_kayu'      => $jenisKayu->nama_kayu ?? '-',
                    'jumlah_batang'  => $lahan->jumlah_batang,
                    'poin'           => $poin,
                    'is_kayu_130'    => $isKayu130,
                ];

                if ($isKayu130) {
                    $poinKayu130 += $poin;
                } else {
                    $poinKayu260 += $poin;
                }
            }
        }

        // ── Hitung kubikasi 65% (basis pembagi harga veneer) ─────────────────
        $kubikasiTotal65 = ($kubikasiTotalFB + $kubikasiTotalCore) * 0.65;

        // Harga veneer per m³ = total_poin / kubikasi_65%
        $totalPoin = $poinKayu130 + $poinKayu260;
        $hargaVeneer = ($kubikasiTotal65 > 0) ? ($totalPoin / $kubikasiTotal65) : 0;

        // Nilai veneer
        $nilaiVeneerFB   = $kubikasiTotalFB   * $hargaVeneer;
        $nilaiVeneerCore = $kubikasiTotalCore  * $hargaVeneer;

        // ── Upah tenaga kerja ─────────────────────────────────────────────────
        $totalUpah = 0.0;
        $detailUpahPerProduksi = [];

        foreach ($produksiList as $produksi) {
            $upahMesin = 0.0;
            foreach ($produksi->detailPegawaiRotary as $peg) {
                // Ongkos dari mesin (ongkos_mesin per shift)
                $ongkos = $produksi->mesin->ongkos_mesin ?? 0;
                $upahMesin += $ongkos;
            }
            // Deduplikasi: ambil ongkos_mesin sekali per produksi (bukan per pegawai)
            $upahMesin = $produksi->mesin->ongkos_mesin ?? 0;
            $totalUpah += $upahMesin;

            $detailUpahPerProduksi[$produksi->id] = [
                'nama_mesin'   => $produksi->mesin->nama_mesin,
                'ongkos_mesin' => $upahMesin,
            ];
        }

        // ── Bahan penolong ────────────────────────────────────────────────────
        $bahanPenolong = [];  // [kode_akun => ['nama' => ..., 'nilai' => ..., 'detail' => [...]]]

        foreach ($produksiList as $produksi) {
            foreach ($produksi->bahanPenolongRotary as $bahan) {
                $namaBahanLower = strtolower(trim($bahan->nama_bahan));
                $mappedAkun = null;

                foreach (self::BAHAN_PENOLONG_MAP as $keyword => $akun) {
                    if (str_contains($namaBahanLower, $keyword)) {
                        $mappedAkun = $akun;
                        break;
                    }
                }

                if (!$mappedAkun) continue;  // Bahan penolong tidak dikenali, skip

                $kode = $mappedAkun['kode'];
                if (!isset($bahanPenolong[$kode])) {
                    $bahanPenolong[$kode] = [
                        'kode'   => $kode,
                        'nama'   => $mappedAkun['nama'],
                        'nilai'  => 0.0,
                        'detail' => [],
                    ];
                }

                $bahanPenolong[$kode]['nilai']    += (float) ($bahan->jumlah ?? 0);
                $bahanPenolong[$kode]['detail'][] = [
                    'id_produksi'  => $produksi->id,
                    'nama_mesin'   => $produksi->mesin->nama_mesin,
                    'nama_bahan'   => $bahan->nama_bahan,
                    'jumlah'       => $bahan->jumlah,
                ];
            }
        }

        // ── Hitung selisih & tentukan akun penyeimbang ───────────────────────
        $totalDebit  = $nilaiVeneerFB + $nilaiVeneerCore + $totalUpah;
        $totalKredit = $poinKayu130 + $poinKayu260 + $totalUpah;

        // Tambahkan bahan penolong ke kredit
        foreach ($bahanPenolong as $bp) {
            $totalKredit += $bp['nilai'];
        }

        $selisih           = round($totalDebit - $totalKredit, 4);
        $akunSelisih       = null;

        if (abs($selisih) > 0.01) {
            if ($selisih > 0) {
                // Debit lebih besar → ada keuntungan → masuk kredit 520-09
                $akunSelisih = ['kode' => '520-09', 'nama' => 'Keuntungan hasil produksi', 'map' => 'k', 'nilai' => abs($selisih)];
            } else {
                // Kredit lebih besar → ada kerugian → masuk debit 520-08
                $akunSelisih = ['kode' => '520-08', 'nama' => 'Beban kerugian produksi', 'map' => 'd', 'nilai' => abs($selisih)];
            }
        }

        return compact(
            'kubikasiTotalFB',
            'kubikasiTotalCore',
            'kubikasiTotal65',
            'hargaVeneer',
            'nilaiVeneerFB',
            'nilaiVeneerCore',
            'poinKayu130',
            'poinKayu260',
            'totalPoin',
            'totalUpah',
            'bahanPenolong',
            'selisih',
            'akunSelisih',
            'totalDebit',
            'totalKredit',
            'kubikasiPerMesin',
            'detailKayuPerProduksi',
            'detailUpahPerProduksi'
        );
    }

    /**
     * Ambil poin kayu dari penggunaan lahan (sementara sampai HPP kayu jadi)
     * Rumus: harga_beli * (p * d * d * jml_batang * 0.785 / 1.000.000) * 1000
     */
    /**
     * Ambil poin kayu dari penggunaan lahan (sementara sampai HPP kayu jadi)
     * Rumus: harga_beli * (p * d * d * jml_batang * 0.785 / 1.000.000) * 1000
     */
    private function getPoinKayuFromLahan(PenggunaanLahanRotary $lahan): float
    {
        try {
            $poin = DB::table('penggunaan_lahan_rotaries as plr')
                ->join('riwayat_kayus as rk', 'rk.id_rotary', '=', 'plr.id_produksi')
                ->join('detail_kayu_masuks as dkm', 'dkm.id_lahan', '=', 'plr.id_lahan')
                ->leftJoin('harga_kayus as hk', function ($join) {
                    $join->on('hk.id_jenis_kayu', '=', 'dkm.id_jenis_kayu')
                         ->on('hk.grade', '=', 'dkm.grade')
                         ->on('hk.panjang', '=', 'dkm.panjang')
                         ->whereRaw('dkm.diameter BETWEEN hk.diameter_terkecil AND hk.diameter_terbesar');
                })
                ->where('plr.id', $lahan->id)
                ->selectRaw('
                    SUM(
                        hk.harga_beli *
                        (dkm.panjang * dkm.diameter * dkm.diameter * dkm.jumlah_batang * 0.785 / 1000000)
                        * 1000
                    ) as total_poin
                ')
                ->value('total_poin');

            return (float) ($poin ?? 0);
        } catch (\Throwable $e) {
            Log::warning("RotaryJurnal: Gagal ambil poin kayu lahan #{$lahan->id}: " . $e->getMessage());
            return 0.0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BUILD STRUKTUR PAYLOAD
    // ─────────────────────────────────────────────────────────────────────────

    private function buildStructure(Carbon $tgl, Collection $produksiList, array $c): array
    {
        $tglFormatted  = $tgl->format('Y-m-d');
        $keterangan    = 'Rotary tgl ' . $tgl->format('j');
        $noJurnal      = 'ROT/' . $tgl->format('Ymd');

        // ── Susun baris-baris jurnal (header rows) ────────────────────────────
        $rows = [];
        $urut = 1;

        // DEBIT: Veneer F/B
        if ($c['nilaiVeneerFB'] > 0) {
            $rows[] = $this->makeRow($urut++, 'd', '115-07', 'Veneer Basah F/B', $c['nilaiVeneerFB'], $keterangan, [
                'kubikasi' => $c['kubikasiTotalFB'],
                'harga_veneer_per_m3' => $c['hargaVeneer'],
                'breakdown_mesin' => $this->buildBreakdownMesinVeneer($produksiList, $c['kubikasiPerMesin'], 'f/b', $c['hargaVeneer']),
            ]);
        }

        // DEBIT: Veneer CORE
        if ($c['nilaiVeneerCore'] > 0) {
            $rows[] = $this->makeRow($urut++, 'd', '115-08', 'Veneer Basah CORE', $c['nilaiVeneerCore'], $keterangan, [
                'kubikasi' => $c['kubikasiTotalCore'],
                'harga_veneer_per_m3' => $c['hargaVeneer'],
                'breakdown_mesin' => $this->buildBreakdownMesinVeneer($produksiList, $c['kubikasiPerMesin'], 'core', $c['hargaVeneer']),
            ]);
        }

        // DEBIT: Upah Tenaga Kerja
        if ($c['totalUpah'] > 0) {
            $rows[] = $this->makeRow($urut++, 'd', '510-01', 'Upah Tenaga Kerja', $c['totalUpah'], $keterangan, [
                'breakdown_mesin' => array_values($c['detailUpahPerProduksi']),
            ]);
        }

        // DEBIT: Beban Kerugian (jika selisih negatif)
        if ($c['akunSelisih'] && $c['akunSelisih']['map'] === 'd') {
            $rows[] = $this->makeRow($urut++, 'd', $c['akunSelisih']['kode'], $c['akunSelisih']['nama'], $c['akunSelisih']['nilai'], $keterangan, [
                'selisih_debit_kredit' => $c['selisih'],
            ]);
        }

        // KREDIT: Persediaan Kayu 260
        if ($c['poinKayu260'] > 0) {
            $rows[] = $this->makeRow($urut++, 'k', '115-02', 'Persediaan Kayu 260', $c['poinKayu260'], $keterangan, [
                'breakdown_lahan' => $this->buildBreakdownKayu($c['detailKayuPerProduksi'], false),
            ]);
        }

        // KREDIT: Persediaan Kayu 130
        if ($c['poinKayu130'] > 0) {
            $rows[] = $this->makeRow($urut++, 'k', '115-01', 'Persediaan Kayu 130', $c['poinKayu130'], $keterangan, [
                'breakdown_lahan' => $this->buildBreakdownKayu($c['detailKayuPerProduksi'], true),
            ]);
        }

        // KREDIT: Bahan Penolong
        foreach ($c['bahanPenolong'] as $bp) {
            $rows[] = $this->makeRow($urut++, 'k', $bp['kode'], $bp['nama'], $bp['nilai'], $keterangan, [
                'breakdown_mesin' => $bp['detail'],
            ]);
        }

        // KREDIT: Hutang Gaji
        if ($c['totalUpah'] > 0) {
            $rows[] = $this->makeRow($urut++, 'k', '210-02', 'Hutang Gaji', $c['totalUpah'], $keterangan, [
                'breakdown_mesin' => array_values($c['detailUpahPerProduksi']),
            ]);
        }

        // KREDIT: Keuntungan Hasil Produksi (jika selisih positif)
        if ($c['akunSelisih'] && $c['akunSelisih']['map'] === 'k') {
            $rows[] = $this->makeRow($urut++, 'k', $c['akunSelisih']['kode'], $c['akunSelisih']['nama'], $c['akunSelisih']['nilai'], $keterangan, [
                'selisih_debit_kredit' => $c['selisih'],
            ]);
        }

        // ── Susun payload akhir ───────────────────────────────────────────────
        $finalDebit  = $c['totalDebit']  + (($c['akunSelisih']['map'] ?? '') === 'd' ? ($c['akunSelisih']['nilai'] ?? 0) : 0);
        $finalKredit = $c['totalKredit'] + (($c['akunSelisih']['map'] ?? '') === 'k' ? ($c['akunSelisih']['nilai'] ?? 0) : 0);

        return [
            'jurnal_header' => [
                'tgl_transaksi'   => $tglFormatted,
                'no_jurnal'       => $noJurnal,
                'jenis_transaksi' => 'produksi',
                'modul_asal'      => 'rotary',
                'keterangan'      => $keterangan,
                'total_debit'     => round($finalDebit, 4),
                'total_kredit'    => round($finalKredit, 4),
                'is_balance'      => round($finalDebit, 2) === round($finalKredit, 2),
                'status'          => 'draft',
            ],
            'jurnal_items' => $rows,
            'summary' => [
                'tanggal'            => $tglFormatted,
                'jumlah_mesin'       => $produksiList->count(),
                'mesin_list'         => $produksiList->pluck('mesin.nama_mesin')->toArray(),
                'kubikasi_fb_m3'     => round($c['kubikasiTotalFB'], 6),
                'kubikasi_core_m3'   => round($c['kubikasiTotalCore'], 6),
                'kubikasi_65pct_m3'  => round($c['kubikasiTotal65'], 6),
                'harga_veneer_m3'    => round($c['hargaVeneer'], 2),
                'total_poin_kayu'    => round($c['totalPoin'], 2),
                'total_upah'         => round($c['totalUpah'], 2),
                'selisih'            => round($c['selisih'], 4),
                'akun_selisih'       => $c['akunSelisih'],
            ],
        ];
    }

    private function makeRow(int $urut, string $map, string $kode, string $nama, float $nilai, string $keterangan, array $detail = []): array
    {
        return [
            'urut'       => $urut,
            'map'        => $map,           // 'd' = debit, 'k' = kredit
            'no_akun'    => $kode,
            'nama_akun'  => $nama,
            'jumlah'     => round($nilai, 4),
            'keterangan' => $keterangan,
            'detail'     => $detail,        // Untuk jurnal pembantu item (drill-down)
        ];
    }

    private function buildBreakdownMesinVeneer(Collection $produksiList, array $kubikasiPerMesin, string $jenisTarget, float $hargaVeneer): array
    {
        $result = [];
        foreach ($produksiList as $produksi) {
            $data = $kubikasiPerMesin[$produksi->id] ?? null;
            if (!$data) continue;
            if ($data['jenis'] !== $jenisTarget) continue;

            $kubikasi = $data['kubikasi'];
            $result[] = [
                'id_produksi'  => $produksi->id,
                'nama_mesin'   => $produksi->mesin->nama_mesin,
                'jenis_hasil'  => $produksi->mesin->jenis_hasil,
                'kubikasi_m3'  => round($kubikasi, 6),
                'harga_per_m3' => round($hargaVeneer, 2),
                'nilai'        => round($kubikasi * $hargaVeneer, 4),
                'detail_palet' => $this->buildDetailPalet($produksi),
            ];
        }
        return $result;
    }

    private function buildDetailPalet(ProduksiRotary $produksi): array
    {
        $result = [];
        foreach ($produksi->detailPaletRotary as $palet) {
            $ukuran = $palet->ukuran;
            if (!$ukuran) continue;

            $vol = ($ukuran->panjang ?? 0)
                 * ($ukuran->lebar ?? 0)
                 * ($ukuran->tebal ?? 0)
                 * ($palet->total_lembar ?? 0)
                 / 10_000_000;

            $result[] = [
                'kw'           => $palet->kw,
                'palet'        => $palet->palet,
                'total_lembar' => $palet->total_lembar,
                'ukuran'       => "{$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal}",
                'kubikasi_m3'  => round($vol, 6),
                'lahan'        => $palet->penggunaanLahan->lahan->nama_lahan ?? '-',
            ];
        }
        return $result;
    }

    private function buildBreakdownKayu(array $detailKayuPerProduksi, bool $is130): array
    {
        $result = [];
        foreach ($detailKayuPerProduksi as $idProduksi => $lahanList) {
            foreach ($lahanList as $lahan) {
                if ($lahan['is_kayu_130'] !== $is130) continue;
                $result[] = [
                    'id_produksi'   => $idProduksi,
                    'id_lahan'      => $lahan['id_lahan'],
                    'nama_lahan'    => $lahan['nama_lahan'],
                    'kode_lahan'    => $lahan['kode_lahan'],
                    'nama_kayu'     => $lahan['nama_kayu'],
                    'jumlah_batang' => $lahan['jumlah_batang'],
                    'poin'          => round($lahan['poin'], 4),
                ];
            }
        }
        return $result;
    }
}