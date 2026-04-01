<?php

namespace App\Services\Akuntansi;

use App\Models\ProduksiRotary;
use App\Models\PenggunaanLahanRotary;
use App\Models\HargaPegawai;
use App\Models\HppAverageSummarie;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahBahanPenolong;
use App\Models\HppAverageLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RotaryJurnalService
 *
 * Menghasilkan payload jurnal pembantu dari produksi rotary satu tanggal.
 *
 * STRUKTUR PAYLOAD:
 *   jurnal_header  → info umum (no_jurnal, tanggal, total, balance)
 *   jurnal_items   → 8 baris akun (masuk jurnal_pembantu_headers di akuntansi)
 *     └─ items     → rincian per baris akun (masuk jurnal_pembantu_items di akuntansi)
 *                    field: urut, jenis_pihak, nama_pihak, keterangan,
 *                           ukuran, banyak, m3, harga, hit_kbk, jumlah
 *
 * PEMETAAN AKUN:
 * DEBIT:
 *   115-07  Veneer Basah F/B        → items: per mesin → per palet (hit_kbk='k')
 *   115-08  Veneer Basah CORE       → items: per mesin → per palet (hit_kbk='k')
 *   510-01  Upah Tenaga Kerja       → items: per mesin (hit_kbk=null)
 *   520-08  Beban Kerugian Produksi → items: 1 baris selisih (hit_kbk=null)
 *
 * KREDIT:
 *   115-02  Persediaan Kayu 260     → items: per lahan (hit_kbk=null)
 *   115-01  Persediaan Kayu 130     → items: per lahan (hit_kbk=null)
 *   115-13  Persediaan Reeling Tape → items: per mesin (hit_kbk=null)
 *   210-02  Hutang Gaji             → items: per mesin (hit_kbk=null)
 *   520-09  Keuntungan Produksi     → items: 1 baris selisih (hit_kbk=null)
 */
class RotaryJurnalService
{
    // ─── Konstanta Kode Akun ─────────────────────────────────────────────────

    const AKUN = [
        'veneer_fb'       => ['kode' => '115-07', 'nama' => 'Veneer Basah F/B',          'map' => 'd'],
        'veneer_core'     => ['kode' => '115-08', 'nama' => 'Veneer Basah CORE',         'map' => 'd'],
        'upah_tk'         => ['kode' => '510-01', 'nama' => 'Upah Tenaga Kerja',         'map' => 'd'],
        'beban_kerugian'  => ['kode' => '520-08', 'nama' => 'Beban kerugian produksi',   'map' => 'd'],
        'kayu_130'        => ['kode' => '115-01', 'nama' => 'Persediaan Kayu 130',       'map' => 'k'],
        'kayu_260'        => ['kode' => '115-02', 'nama' => 'Persediaan Kayu 260',       'map' => 'k'],
        'hutang_gaji'     => ['kode' => '210-02', 'nama' => 'Hutang Gaji',               'map' => 'k'],
        'reeling_tape'    => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape',   'map' => 'k'],
        'keuntungan_prod' => ['kode' => '520-09', 'nama' => 'Keuntungan hasil produksi', 'map' => 'k'],
    ];

    // Mapping nama bahan penolong → kode akun kredit
    const BAHAN_PENOLONG_MAP = [
        'reeling tape' => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
        'relling tape' => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
        'reeling'      => ['kode' => '115-13', 'nama' => 'Persediaan Reeling Tape'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build payload jurnal untuk tanggal produksi tertentu.
     *
     * @param  string  $tanggal  Format: Y-m-d
     * @return array|null  null = belum semua mesin divalidasi
     */
    public function buildJurnalPayload(string $tanggal): ?array
    {
        $tgl = Carbon::parse($tanggal)->startOfDay();

        $produksiList = ProduksiRotary::with([
            'mesin',
            'detailValidasiHasilRotary',
            'detailPegawaiRotary.pegawai',
            'detailLahanRotary.lahan',
            'detailLahanRotary.jenisKayu',
            'detailPaletRotary.ukuran',
            'detailPaletRotary.penggunaanLahan.lahan',
            'bahanPenolongRotary.bahanPenolong',
            'detailKayuPecah.penggunaanLahan',
        ])
            ->whereDate('tgl_produksi', $tgl)
            ->get();

        if ($produksiList->isEmpty()) {
            Log::info("RotaryJurnal: Tidak ada produksi pada tanggal {$tanggal}");
            return null;
        }

        // Cek semua mesin sudah divalidasi
        foreach ($produksiList as $produksi) {
            $validated = $produksi->detailValidasiHasilRotary
                ->whereIn('status', ['divalidasi', 'disetujui'])
                ->count();

            if ($validated === 0) {
                Log::info("RotaryJurnal: Mesin [{$produksi->mesin->nama_mesin}] belum divalidasi. Jurnal ditunda.");
                return null;
            }
        }

        $calc = $this->hitungNominal($produksiList);

        return $this->buildStructure($tgl, $produksiList, $calc);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  KALKULASI
    // ─────────────────────────────────────────────────────────────────────────

    private function hitungNominal(Collection $produksiList): array
    {
        // ── Kubikasi veneer ───────────────────────────────────────────────────
        $kubikasiPerMesin  = [];
        $kubikasiTotalFB   = 0.0;
        $kubikasiTotalCore = 0.0;

        foreach ($produksiList as $produksi) {
            $jenis    = strtolower($produksi->mesin->jenis_hasil ?? 'core');
            $kubikasi = 0.0;

            foreach ($produksi->detailPaletRotary as $palet) {
                $ukuran = $palet->ukuran;
                if (!$ukuran) continue;
                $vol = ($ukuran->panjang ?? 0)
                     * ($ukuran->lebar   ?? 0)
                     * ($ukuran->tebal   ?? 0)
                     * ($palet->total_lembar ?? 0)
                     / 10_000_000;
                $kubikasi += $vol;
            }

            $kubikasiPerMesin[$produksi->id] = ['jenis' => $jenis, 'kubikasi' => $kubikasi];

            if ($jenis === 'f/b') {
                $kubikasiTotalFB += $kubikasi;
            } else {
                $kubikasiTotalCore += $kubikasi;
            }
        }

        // ── Poin kayu per lahan ───────────────────────────────────────────────
        $poinKayu130           = 0.0;
        $poinKayu260           = 0.0;
        $detailKayuPerProduksi = [];

        foreach ($produksiList as $produksi) {
            foreach ($produksi->detailLahanRotary as $lahan) {
                $namaLahan = strtolower($lahan->lahan->nama_lahan ?? '');
                $isKayu130 = str_contains($namaLahan, '130');
                $poin      = $this->getPoinKayuFromLahan($lahan);

                // Ambil stok_kubikasi & hpp_average dari summarie untuk item detail
                $summaries = HppAverageSummarie::where('id_lahan', $lahan->id_lahan)
                    ->where('stok_kubikasi', '>', 0)
                    ->get();
                $stokKubikasi = $summaries->sum('stok_kubikasi');
                $hppAvgLahan  = $stokKubikasi > 0 ? round($poin / $stokKubikasi, 2) : 0;

                $detailKayuPerProduksi[$produksi->id][] = [
                    'id_lahan'      => $lahan->id_lahan,
                    'nama_lahan'    => $lahan->lahan->nama_lahan    ?? '-',
                    'kode_lahan'    => $lahan->lahan->kode_lahan    ?? '-',
                    'nama_kayu'     => $lahan->jenisKayu->nama_kayu ?? '-',
                    'nama_mesin'    => $produksi->mesin->nama_mesin,
                    'jumlah_batang' => $lahan->jumlah_batang,
                    'stok_kubikasi' => round($stokKubikasi, 4),
                    'hpp_average'   => $hppAvgLahan,
                    'poin'          => $poin,
                    'is_kayu_130'   => $isKayu130,
                ];

                if ($isKayu130) {
                    $poinKayu130 += $poin;
                } else {
                    $poinKayu260 += $poin;
                }
            }
        }

        // ── Harga veneer ──────────────────────────────────────────────────────
        $kubikasiTotal65 = ($kubikasiTotalFB + $kubikasiTotalCore) * 0.65;
        $totalPoin       = $poinKayu130 + $poinKayu260;
        $hargaVeneer     = ($kubikasiTotal65 > 0) ? ($totalPoin / $kubikasiTotal65) : 0;
        $nilaiVeneerFB   = $kubikasiTotalFB   * $hargaVeneer;
        $nilaiVeneerCore = $kubikasiTotalCore  * $hargaVeneer;

        // ── Upah tenaga kerja ─────────────────────────────────────────────────
        // totalHargaPekerja = HargaPegawai::first()->harga × jumlah_pegawai
        // upahPerPegawai    = totalHargaPekerja / jumlah_pegawai
        //                   = HargaPegawai::first()->harga (tiap pegawai dapat sama rata)
        $masterHargaPkj    = (float) (HargaPegawai::first()->harga ?? 0);
        $totalUpah         = 0.0;
        $detailPegawaiUpah = [];  // untuk items jurnal

        foreach ($produksiList as $produksi) {
            $jumlahPegawai     = $produksi->detailPegawaiRotary->count();
            $totalHargaPekerja = $masterHargaPkj * $jumlahPegawai;
            $upahPerPegawai    = $jumlahPegawai > 0 ? round($totalHargaPekerja / $jumlahPegawai, 4) : 0;
            // = masterHargaPkj per pegawai

            $totalUpah += $totalHargaPekerja;

            foreach ($produksi->detailPegawaiRotary as $pr) {
                $detailPegawaiUpah[] = [
                    'nama_pegawai' => $pr->pegawai->nama_pegawai ?? 'Pegawai #' . $pr->id_pegawai,
                    'role'         => $pr->role ?? '-',
                    'nama_mesin'   => $produksi->mesin->nama_mesin,
                    'jumlah'       => $upahPerPegawai,
                ];
            }
        }

        // ── Bahan penolong ────────────────────────────────────────────────────
        $bahanPenolong = [];

        foreach ($produksiList as $produksi) {
            foreach ($produksi->bahanPenolongRotary as $bahan) {
                $master         = $bahan->bahanPenolong;
                $namaBahanLower = strtolower(trim($master->nama_bahan_penolong ?? ''));
                $hargaSatuan    = (float) ($master->harga ?? 0);
                $nilaiTotal     = $hargaSatuan * (float) ($bahan->jumlah ?? 0);
                $mappedAkun     = null;

                foreach (self::BAHAN_PENOLONG_MAP as $keyword => $akun) {
                    if (str_contains($namaBahanLower, $keyword)) {
                        $mappedAkun = $akun;
                        break;
                    }
                }

                if (!$mappedAkun) continue;

                $kode = $mappedAkun['kode'];
                if (!isset($bahanPenolong[$kode])) {
                    $bahanPenolong[$kode] = ['kode' => $kode, 'nama' => $mappedAkun['nama'], 'nilai' => 0.0, 'detail' => []];
                }

                $bahanPenolong[$kode]['nilai']    += $nilaiTotal;
                $bahanPenolong[$kode]['detail'][] = [
                    'nama_mesin'        => $produksi->mesin->nama_mesin,
                    'nama_bahan'        => $master->nama_bahan_penolong ?? '-',
                    'satuan'            => $master->satuan ?? '-',
                    'jumlah'            => (float) ($bahan->jumlah ?? 0),
                    'harga_satuan'      => $hargaSatuan,
                    'nilai_total'       => $nilaiTotal,
                    'bahan_penolong_id' => $bahan->bahan_penolong_id,
                ];
            }
        }

        // ── Selisih ───────────────────────────────────────────────────────────
        $totalDebit  = $nilaiVeneerFB + $nilaiVeneerCore + $totalUpah;
        $totalKredit = $poinKayu130 + $poinKayu260 + $totalUpah;

        foreach ($bahanPenolong as $bp) {
            $totalKredit += $bp['nilai'];
        }

        $selisih     = round($totalDebit - $totalKredit, 4);
        $akunSelisih = null;

        if (abs($selisih) > 0.01) {
            $akunSelisih = $selisih > 0
                ? ['kode' => '520-09', 'nama' => 'Keuntungan hasil produksi', 'map' => 'k', 'nilai' => abs($selisih)]
                : ['kode' => '520-08', 'nama' => 'Beban kerugian produksi',   'map' => 'd', 'nilai' => abs($selisih)];
        }

        return compact(
            'kubikasiTotalFB', 'kubikasiTotalCore', 'kubikasiTotal65',
            'hargaVeneer', 'nilaiVeneerFB', 'nilaiVeneerCore',
            'poinKayu130', 'poinKayu260', 'totalPoin',
            'totalUpah', 'bahanPenolong',
            'selisih', 'akunSelisih', 'totalDebit', 'totalKredit',
            'kubikasiPerMesin', 'detailKayuPerProduksi', 'detailPegawaiUpah'
        );
    }

    /**
     * Hitung poin kayu dari lahan menggunakan HPP Average.
     *
     * Konsep: lahan yang tercatat di penggunaan_lahan_rotaries berarti
     * seluruh stoknya dipakai. Poin = SUM(hpp_average × stok_kubikasi)
     * untuk semua kombinasi (grade+panjang+jenis_kayu) di lahan tersebut.
     */
    private function getPoinKayuFromLahan(PenggunaanLahanRotary $lahan): float
    {
        try {
            $summaries = HppAverageSummarie::where('id_lahan', $lahan->id_lahan)
                ->where('stok_kubikasi', '>', 0)
                ->get();

            if ($summaries->isEmpty()) {
                Log::info("RotaryJurnal: Lahan #{$lahan->id_lahan} tidak punya stok HPP.");
                return 0.0;
            }

            $totalPoin = 0.0;
            foreach ($summaries as $summarie) {
                $totalPoin += (float) $summarie->hpp_average * (float) $summarie->stok_kubikasi;
            }

            return round($totalPoin, 4);

        } catch (\Throwable $e) {
            Log::warning("RotaryJurnal: Gagal ambil poin kayu lahan #{$lahan->id}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Kurangi stok HPP Average di HppAverageSummarie setelah jurnal dikirim.
     *
     * Konsep: jika lahan A tercatat di penggunaan_lahan_rotaries berarti
     * seluruh kayu di lahan itu sudah habis dipakai produksi.
     * → Habiskan semua kombinasi (grade+panjang) di HppAverageSummarie untuk lahan tersebut.
     * → Catat HppAverageLog tipe='keluar' per kombinasi.
     */
    public function kurangiStokHpp(Collection $produksiList, string $tanggal): void
    {
        $lahanDiproses = [];
        $tglFormatLog  = \Carbon\Carbon::parse($tanggal)->format('d/m/Y');

        foreach ($produksiList as $produksi) {
            foreach ($produksi->detailLahanRotary as $lahan) {
                $idLahan = $lahan->id_lahan;

                if (isset($lahanDiproses[$idLahan])) continue;
                $lahanDiproses[$idLahan] = true;

                // Label lahan untuk keterangan log
                $namaLahan  = $lahan->lahan->nama_lahan ?? "Lahan #{$idLahan}";
                $kodeLahan  = $lahan->lahan->kode_lahan ?? '';
                $labelLahan = $kodeLahan ? "{$kodeLahan} - {$namaLahan}" : $namaLahan;

                // ── Cari kayu pecah di lahan ini ──────────────────────────────
                // KayuPecahRotary → id_penggunaan_lahan → PenggunaanLahanRotary.id_lahan
                $kayuPecahList = $produksi->detailKayuPecah
                    ->filter(fn($kp) => $kp->penggunaanLahan?->id_lahan === $idLahan);

                $jumlahPecah   = $kayuPecahList->count();
                $ukuranPecah   = $kayuPecahList->pluck('ukuran')->filter()->unique()->implode(', ');
                $keteranganPecah = $jumlahPecah > 0
                    ? " · Kayu pecah/hilang: {$jumlahPecah} btg" . ($ukuranPecah ? " ({$ukuranPecah})" : '')
                    : '';

                // ── Ambil semua kombinasi di lahan ini yang masih punya stok ──
                // Grade diabaikan — group per panjang + jenis_kayu
                $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                    ->where('stok_batang', '>', 0)
                    ->get();

                if ($summaries->isEmpty()) {
                    Log::info("RotaryJurnal: Lahan #{$idLahan} tidak punya stok di HPP summarie. Dilewati.");
                    continue;
                }

                foreach ($summaries as $summarie) {
                    $hppAverage     = (float) $summarie->hpp_average;
                    $batangBefore   = (int)   $summarie->stok_batang;
                    $kubikasiBefore = (float) $summarie->stok_kubikasi;
                    $nilaiBefore    = (float) $summarie->nilai_stok;
                    $nilaiKeluar    = round($hppAverage * $kubikasiBefore, 2);

                    $keterangan = "Digunakan produksi rotary tgl {$tglFormatLog} · Lahan {$labelLahan}{$keteranganPecah}";

                    $log = HppAverageLog::create([
                        'id_jenis_kayu'        => $summarie->id_jenis_kayu,
                        'grade'                => $summarie->grade,
                        'panjang'              => $summarie->panjang,
                        'tanggal'              => $tanggal,
                        'tipe_transaksi'       => 'keluar',
                        'keterangan'           => $keterangan,
                        'referensi_type'       => ProduksiRotary::class,
                        'referensi_id'         => $produksi->id,
                        'total_batang'         => $batangBefore,
                        'total_kubikasi'       => round($kubikasiBefore, 4),
                        'harga'                => $hppAverage,
                        'nilai_stok'           => $nilaiKeluar,
                        'stok_batang_before'   => $batangBefore,
                        'stok_kubikasi_before' => round($kubikasiBefore, 4),
                        'nilai_stok_before'    => $nilaiBefore,
                        'stok_batang_after'    => 0,
                        'stok_kubikasi_after'  => 0,
                        'nilai_stok_after'     => 0,
                        'hpp_average'          => $hppAverage,
                    ]);

                    $summarie->update([
                        'stok_batang'   => 0,
                        'stok_kubikasi' => 0,
                        'nilai_stok'    => 0,
                        'id_last_log'   => $log->id,
                    ]);

                    Log::info("RotaryJurnal: Stok habis - lahan #{$idLahan} jenis#{$summarie->id_jenis_kayu} p{$summarie->panjang}", [
                        'batang_keluar'   => $batangBefore,
                        'kubikasi_keluar' => round($kubikasiBefore, 4),
                        'nilai_keluar'    => $nilaiKeluar,
                        'kayu_pecah'      => $jumlahPecah,
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  STOK VENEER BASAH
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tambah stok veneer basah setelah jurnal sukses dikirim ke akuntansi.
     *
     * Stok digabung per tanggal (semua mesin jadi 1 transaksi masuk per kombinasi ukuran+jenis_kayu).
     *
     * HPP per m³ = hpp_kayu + hpp_pekerja + hpp_mesin + hpp_bahan_penolong
     *   - hpp_kayu          = totalPoinKayu / kubikasiTotal65%
     *   - hpp_pekerja       = totalUpah / kubikasiTotal
     *   - hpp_mesin         = SUM(ongkos_mesin per mesin) / kubikasiTotal
     *   - hpp_bahan_penolong= totalNilaiBahan / kubikasiTotal
     */
    public function tambahStokVeneerBasah(Collection $produksiList, string $tanggal): void
    {
        try {
            $tglFormatLog = \Carbon\Carbon::parse($tanggal)->format('d/m/Y');

            // ── REVISI: Grouped per kombinasi ukuran + jenis_kayu + KW ────────
            // Key: "jenis_kayu_id|panjang|lebar|tebal|kw"
            // HPP dihitung per kombinasi (bukan global) sesuai revisi meeting
            $grouped = [];

            // ── Data global per produksi (untuk HPP komponen) ─────────────────
            // HPP Kayu   : rata-rata hpp_average semua lahan yang dipakai
            // HPP Pekerja: per jam (harga_per_hari / jam_target × jam_kerja_pegawai)
            // HPP Mesin  : ongkos_mesin / kubikasi_total mesin tersebut
            // HPP Bahan  : nilai_bahan / kubikasi_total

            foreach ($produksiList as $produksi) {
                $idMesin     = $produksi->mesin->id ?? null;
                $ongkosMesin = (float) ($produksi->mesin->ongkos_mesin ?? 0);

                // ── HPP Kayu: rata-rata hpp_average semua lahan di mesin ini ──
                $hppKayuLahanList = [];
                foreach ($produksi->detailLahanRotary as $lahan) {
                    $summaries = HppAverageSummarie::where('id_lahan', $lahan->id_lahan)
                        ->where('stok_kubikasi', '>', 0)->get();
                    if ($summaries->isNotEmpty()) {
                        // hpp_average lahan = weighted average dari semua kombinasi
                        $totalKubikasi = $summaries->sum('stok_kubikasi');
                        $hppLahan      = $totalKubikasi > 0
                            ? $summaries->sum(fn($s) => $s->hpp_average * $s->stok_kubikasi) / $totalKubikasi
                            : 0;
                        $hppKayuLahanList[] = $hppLahan;
                    }
                }
                // Rata-rata HPP kayu dari semua lahan (dibagi jumlah lahan)
                $hppKayuMesin = count($hppKayuLahanList) > 0
                    ? round(array_sum($hppKayuLahanList) / count($hppKayuLahanList), 2)
                    : 0;

                // ── HPP Pekerja: per jam ───────────────────────────────────────
                $hargaPerHari  = (float) (\App\Models\HargaPegawai::first()->harga ?? 0);
                $totalUpahMesin = 0.0;

                foreach ($produksi->detailPegawaiRotary as $pegawai) {
                    // Cari target untuk mesin + ukuran + jenis kayu pertama
                    // Ambil jam kerja mesin dari target (cukup per mesin)
                    $jamTarget = (int) (\App\Models\Target::where('id_mesin', $idMesin)->value('jam') ?? 8);

                    // Hitung jam kerja pegawai dari jam_masuk & jam_pulang
                    $jamMasuk  = $pegawai->jam_masuk  ?? '07:00:00';
                    $jamPulang = $pegawai->jam_pulang ?? '15:00:00';
                    $menitKerja = (strtotime($jamPulang) - strtotime($jamMasuk)) / 60;
                    $jamKerja   = max(0, $menitKerja / 60);

                    $hargaPerJam   = $jamTarget > 0 ? $hargaPerHari / $jamTarget : 0;
                    $upahPegawai   = round($hargaPerJam * $jamKerja, 2);
                    $totalUpahMesin += $upahPegawai;
                }

                // ── Kubikasi per mesin per kombinasi ukuran+kw ────────────────
                $kubikasiMesinTotal = 0.0;
                $paletData = []; // simpan dulu untuk diproses setelah kubikasi total diketahui

                foreach ($produksi->detailPaletRotary as $palet) {
                    $ukuran = $palet->ukuran;
                    $p      = (float) ($ukuran->panjang ?? 0);
                    $l      = (float) ($ukuran->lebar   ?? 0);
                    $t      = (float) ($ukuran->tebal   ?? 0);
                    $lembar = (int)   ($palet->total_lembar ?? 0);
                    $kw     = $palet->kw ?? '1';

                    if ($p <= 0 || $l <= 0 || $t <= 0 || $lembar <= 0) continue;

                    $kubikasi = ($p * $l * $t * $lembar) / 10_000_000;
                    $idJenisKayu = $produksi->detailLahanRotary->first()?->jenisKayu?->id ?? 1;

                    $paletData[] = compact('p', 'l', 't', 'kw', 'lembar', 'kubikasi', 'idJenisKayu');
                    $kubikasiMesinTotal += $kubikasi;
                }

                if ($kubikasiMesinTotal <= 0) continue;

                // ── Bahan penolong per mesin ───────────────────────────────────
                $bahanMesinMap = [];
                foreach ($produksi->bahanPenolongRotary as $bahan) {
                    $master = $bahan->bahanPenolong;
                    if (!$master) continue;
                    $id         = $bahan->bahan_penolong_id;
                    $harga      = (float) ($master->harga ?? 0);
                    $jumlah     = (float) ($bahan->jumlah ?? 0);
                    $nilaiTotal = $harga * $jumlah;
                    if (!isset($bahanMesinMap[$id])) {
                        $bahanMesinMap[$id] = [
                            'bahan_penolong_id' => $id,
                            'nama_bahan'        => $master->nama_bahan_penolong,
                            'satuan'            => $master->satuan,
                            'jumlah'            => 0.0,
                            'harga_satuan'      => $harga,
                            'nilai_total'       => 0.0,
                        ];
                    }
                    $bahanMesinMap[$id]['jumlah']      += $jumlah;
                    $bahanMesinMap[$id]['nilai_total'] += $nilaiTotal;
                }
                $totalNilaiBahanMesin = array_sum(array_column($bahanMesinMap, 'nilai_total'));

                // ── HPP komponen per m³ untuk mesin ini ───────────────────────
                $hppPekerja = $kubikasiMesinTotal > 0 ? round($totalUpahMesin    / $kubikasiMesinTotal, 2) : 0;
                $hppMesin   = $kubikasiMesinTotal > 0 ? round($ongkosMesin       / $kubikasiMesinTotal, 2) : 0;
                $hppBahan   = $kubikasiMesinTotal > 0 ? round($totalNilaiBahanMesin / $kubikasiMesinTotal, 2) : 0;

                // ── Masukkan ke grouped per kombinasi ukuran+jenis+kw ─────────
                foreach ($paletData as $pd) {
                    $key = "{$pd['idJenisKayu']}|{$pd['p']}|{$pd['l']}|{$pd['t']}|{$pd['kw']}";
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'id_jenis_kayu'  => $pd['idJenisKayu'],
                            'panjang'        => $pd['p'],
                            'lebar'          => $pd['l'],
                            'tebal'          => $pd['t'],
                            'kw'             => $pd['kw'],
                            'total_lembar'   => 0,
                            'total_kubikasi' => 0.0,
                            // HPP komponen diakumulasi weighted
                            'hpp_kayu_nilai'    => 0.0,
                            'hpp_pekerja_nilai' => 0.0,
                            'hpp_mesin_nilai'   => 0.0,
                            'hpp_bahan_nilai'   => 0.0,
                            'bahan_map'         => [],
                        ];
                    }

                    $kubikasi = $pd['kubikasi'];
                    $grouped[$key]['total_lembar']      += $pd['lembar'];
                    $grouped[$key]['total_kubikasi']    += $kubikasi;
                    $grouped[$key]['hpp_kayu_nilai']    += $hppKayuMesin  * $kubikasi;
                    $grouped[$key]['hpp_pekerja_nilai'] += $hppPekerja    * $kubikasi;
                    $grouped[$key]['hpp_mesin_nilai']   += $hppMesin      * $kubikasi;
                    $grouped[$key]['hpp_bahan_nilai']   += $hppBahan      * $kubikasi;

                    // Merge bahan map
                    foreach ($bahanMesinMap as $bid => $bahan) {
                        if (!isset($grouped[$key]['bahan_map'][$bid])) {
                            $grouped[$key]['bahan_map'][$bid] = $bahan;
                            $grouped[$key]['bahan_map'][$bid]['nilai_total'] = 0.0;
                            $grouped[$key]['bahan_map'][$bid]['jumlah']     = 0.0;
                        }
                        // Proporsional terhadap kubikasi kombinasi ini vs total mesin
                        $rasio = $kubikasiMesinTotal > 0 ? $kubikasi / $kubikasiMesinTotal : 0;
                        $grouped[$key]['bahan_map'][$bid]['nilai_total'] += $bahan['nilai_total'] * $rasio;
                        $grouped[$key]['bahan_map'][$bid]['jumlah']     += $bahan['jumlah']     * $rasio;
                    }
                }
            }

            if (empty($grouped)) {
                Log::warning('[VeneerBasah] Tidak ada kubikasi veneer, stok tidak ditambah.');
                return;
            }

            // ── Insert per kombinasi ukuran+jenis+kw ──────────────────────────
            foreach ($grouped as $key => $item) {
                $kubikasi    = round($item['total_kubikasi'], 6);
                if ($kubikasi <= 0) continue;

                // HPP per m³ untuk kombinasi ini (weighted average dari semua mesin)
                $hppKayu    = $kubikasi > 0 ? round($item['hpp_kayu_nilai']    / $kubikasi, 2) : 0;
                $hppPekerja = $kubikasi > 0 ? round($item['hpp_pekerja_nilai'] / $kubikasi, 2) : 0;
                $hppMesin   = $kubikasi > 0 ? round($item['hpp_mesin_nilai']   / $kubikasi, 2) : 0;
                $hppBahan   = $kubikasi > 0 ? round($item['hpp_bahan_nilai']   / $kubikasi, 2) : 0;
                $hppAverage = $hppKayu + $hppPekerja + $hppMesin + $hppBahan;

                $nilaiMasuk  = round($hppAverage * $kubikasi, 2);
                $idJenisKayu = $item['id_jenis_kayu'];
                $kw          = $item['kw'];

                // Ambil summarie saat ini
                $summarie = HppVeneerBasahSummary::firstOrNew([
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang'       => $item['panjang'],
                    'lebar'         => $item['lebar'],
                    'tebal'         => $item['tebal'],
                    'kw'            => $kw,
                ]);

                $lembarBefore   = (int)   ($summarie->stok_lembar   ?? 0);
                $kubikasiBefore = (float) ($summarie->stok_kubikasi ?? 0);
                $nilaiBefore    = (float) ($summarie->nilai_stok    ?? 0);
                $hppLama        = (float) ($summarie->hpp_average   ?? 0);

                // Moving average HPP
                $nilaiLama      = $hppLama * $kubikasiBefore;
                $hppAverageBaru = ($kubikasiBefore + $kubikasi) > 0
                    ? round(($nilaiLama + $nilaiMasuk) / ($kubikasiBefore + $kubikasi), 2)
                    : $hppAverage;

                $lembarAfter   = $lembarBefore + $item['total_lembar'];
                $kubikasiAfter = round($kubikasiBefore + $kubikasi, 6);
                $nilaiAfter    = round($hppAverageBaru * $kubikasiAfter, 2);

                // Catat log masuk
                $log = HppVeneerBasahLog::create([
                    'id_jenis_kayu'        => $idJenisKayu,
                    'panjang'              => $item['panjang'],
                    'lebar'                => $item['lebar'],
                    'tebal'                => $item['tebal'],
                    'kw'                   => $kw,
                    'tanggal'              => $tanggal,
                    'tipe_transaksi'       => 'masuk',
                    'keterangan'           => "Produksi rotary tgl {$tglFormatLog}",
                    'referensi_type'       => null,
                    'referensi_id'         => null,
                    'total_lembar'         => $item['total_lembar'],
                    'total_kubikasi'       => $kubikasi,
                    'hpp_kayu'             => $hppKayu,
                    'hpp_pekerja'          => $hppPekerja,
                    'hpp_mesin'            => $hppMesin,
                    'hpp_bahan_penolong'   => $hppBahan,
                    'hpp_average'          => $hppAverageBaru,
                    'nilai_stok'           => $nilaiMasuk,
                    'stok_lembar_before'   => $lembarBefore,
                    'stok_kubikasi_before' => round($kubikasiBefore, 6),
                    'nilai_stok_before'    => $nilaiBefore,
                    'stok_lembar_after'    => $lembarAfter,
                    'stok_kubikasi_after'  => $kubikasiAfter,
                    'nilai_stok_after'     => $nilaiAfter,
                ]);

                // Catat breakdown bahan penolong per log
                foreach ($item['bahan_map'] as $bahan) {
                    $hppBahanPerM3 = $kubikasi > 0
                        ? round($bahan['nilai_total'] / $kubikasi, 4)
                        : 0;
                    HppVeneerBasahBahanPenolong::create([
                        'id_log'            => $log->id,
                        'kw'               => $kw,
                        'bahan_penolong_id' => $bahan['bahan_penolong_id'],
                        'nama_bahan'        => $bahan['nama_bahan'],
                        'satuan'            => $bahan['satuan'],
                        'jumlah'            => round($bahan['jumlah'], 4),
                        'harga_satuan'      => $bahan['harga_satuan'],
                        'nilai_total'       => round($bahan['nilai_total'], 2),
                        'hpp_per_m3'        => $hppBahanPerM3,
                    ]);
                }

                // Update summarie
                $summarie->fill([
                    'stok_lembar'             => $lembarAfter,
                    'stok_kubikasi'           => $kubikasiAfter,
                    'nilai_stok'              => $nilaiAfter,
                    'hpp_average'             => $hppAverageBaru,
                    'hpp_kayu_last'           => $hppKayu,
                    'hpp_pekerja_last'        => $hppPekerja,
                    'hpp_mesin_last'          => $hppMesin,
                    'hpp_bahan_penolong_last' => $hppBahan,
                    'id_last_log'             => $log->id,
                ])->save();

                Log::info("[VeneerBasah] Stok masuk - {$item['panjang']}×{$item['lebar']}×{$item['tebal']} KW{$kw}", [
                    'lembar'      => $item['total_lembar'],
                    'kubikasi'    => $kubikasi,
                    'hpp_kayu'    => $hppKayu,
                    'hpp_pekerja' => $hppPekerja,
                    'hpp_mesin'   => $hppMesin,
                    'hpp_bahan'   => $hppBahan,
                    'hpp_average' => $hppAverageBaru,
                    'nilai_masuk' => $nilaiMasuk,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('[VeneerBasah] Gagal tambah stok veneer basah: ' . $e->getMessage(), [
                'tanggal' => $tanggal,
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BUILD STRUKTUR PAYLOAD
    // ─────────────────────────────────────────────────────────────────────────

    private function buildStructure(Carbon $tgl, Collection $produksiList, array $c): array
    {
        $tglFormatted = $tgl->format('Y-m-d');
        $keterangan   = 'Rotary tgl ' . $tgl->format('j');
        $noJurnal     = 'ROT/' . $tgl->format('Ymd');

        $rows = [];
        $urut = 1;

        // ── DEBIT: Veneer F/B ─────────────────────────────────────────────────
        if ($c['nilaiVeneerFB'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'd', '115-07', 'Veneer Basah F/B',
                $c['nilaiVeneerFB'], $keterangan,
                $this->itemsVeneer($produksiList, $c['kubikasiPerMesin'], 'f/b', $c['hargaVeneer'])
            );
        }

        // ── DEBIT: Veneer CORE ────────────────────────────────────────────────
        if ($c['nilaiVeneerCore'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'd', '115-08', 'Veneer Basah CORE',
                $c['nilaiVeneerCore'], $keterangan,
                $this->itemsVeneer($produksiList, $c['kubikasiPerMesin'], 'core', $c['hargaVeneer'])
            );
        }

        // ── DEBIT: Upah Tenaga Kerja ──────────────────────────────────────────
        if ($c['totalUpah'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'd', '510-01', 'Upah Tenaga Kerja',
                $c['totalUpah'], $keterangan,
                $this->itemsUpah($c['detailPegawaiUpah'], $keterangan)
            );
        }

        // ── DEBIT: Beban Kerugian (selisih negatif) ───────────────────────────
        if ($c['akunSelisih'] && $c['akunSelisih']['map'] === 'd') {
            $rows[] = $this->makeRow(
                $urut++, 'd', $c['akunSelisih']['kode'], $c['akunSelisih']['nama'],
                $c['akunSelisih']['nilai'], $keterangan,
                $this->itemsSelisih($c['akunSelisih']['nilai'], $keterangan)
            );
        }

        // ── KREDIT: Persediaan Kayu 260 ───────────────────────────────────────
        if ($c['poinKayu260'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'k', '115-02', 'Persediaan Kayu 260',
                $c['poinKayu260'], $keterangan,
                $this->itemsKayu($c['detailKayuPerProduksi'], false, $keterangan)
            );
        }

        // ── KREDIT: Persediaan Kayu 130 ───────────────────────────────────────
        if ($c['poinKayu130'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'k', '115-01', 'Persediaan Kayu 130',
                $c['poinKayu130'], $keterangan,
                $this->itemsKayu($c['detailKayuPerProduksi'], true, $keterangan)
            );
        }

        // ── KREDIT: Bahan Penolong ────────────────────────────────────────────
        foreach ($c['bahanPenolong'] as $bp) {
            $rows[] = $this->makeRow(
                $urut++, 'k', $bp['kode'], $bp['nama'],
                $bp['nilai'], $keterangan,
                $this->itemsBahanPenolong($bp['detail'], $keterangan)
            );
        }

        // ── KREDIT: Hutang Gaji ───────────────────────────────────────────────
        if ($c['totalUpah'] > 0) {
            $rows[] = $this->makeRow(
                $urut++, 'k', '210-02', 'Hutang Gaji',
                $c['totalUpah'], $keterangan,
                $this->itemsUpah($c['detailPegawaiUpah'], $keterangan)
            );
        }

        // ── KREDIT: Keuntungan Produksi (selisih positif) ─────────────────────
        if ($c['akunSelisih'] && $c['akunSelisih']['map'] === 'k') {
            $rows[] = $this->makeRow(
                $urut++, 'k', $c['akunSelisih']['kode'], $c['akunSelisih']['nama'],
                $c['akunSelisih']['nilai'], $keterangan,
                $this->itemsSelisih($c['akunSelisih']['nilai'], $keterangan)
            );
        }

        // ── Final debit & kredit ──────────────────────────────────────────────
        $finalDebit  = $c['totalDebit']  + (($c['akunSelisih']['map'] ?? '') === 'd' ? ($c['akunSelisih']['nilai'] ?? 0) : 0);
        $finalKredit = $c['totalKredit'] + (($c['akunSelisih']['map'] ?? '') === 'k' ? ($c['akunSelisih']['nilai'] ?? 0) : 0);

        return [
            'jurnal_header' => [
                'no_jurnal'       => $noJurnal,
                'tgl_transaksi'   => $tglFormatted,
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
                'tanggal'           => $tglFormatted,
                'jumlah_mesin'      => $produksiList->count(),
                'mesin_list'        => $produksiList->pluck('mesin.nama_mesin')->toArray(),
                'kubikasi_fb_m3'    => round($c['kubikasiTotalFB'],   6),
                'kubikasi_core_m3'  => round($c['kubikasiTotalCore'], 6),
                'kubikasi_65pct_m3' => round($c['kubikasiTotal65'],   6),
                'harga_veneer_m3'   => round($c['hargaVeneer'],       2),
                'total_poin_kayu'   => round($c['totalPoin'],         2),
                'total_upah'        => round($c['totalUpah'],         2),
                'selisih'           => round($c['selisih'],           4),
                'akun_selisih'      => $c['akunSelisih'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPER: makeRow
    // ─────────────────────────────────────────────────────────────────────────

    private function makeRow(int $urut, string $map, string $kode, string $nama, float $nilai, string $keterangan, array $items = []): array
    {
        return [
            'urut'       => $urut,
            'map'        => $map,
            'no_akun'    => $kode,
            'nama_akun'  => $nama,
            'jumlah'     => round($nilai, 4),
            'keterangan' => $keterangan,
            'items'      => $items,   // → jurnal_pembantu_items
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPER: BUILD ITEMS (jurnal_pembantu_items)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Items untuk Veneer F/B dan Veneer CORE
     * Tiap baris = 1 palet, dengan hit_kbk='k' (harga × m3)
     */
    private function itemsVeneer(Collection $produksiList, array $kubikasiPerMesin, string $jenisTarget, float $hargaVeneer): array
    {
        $items = [];
        $urut  = 1;

        foreach ($produksiList as $produksi) {
            $data = $kubikasiPerMesin[$produksi->id] ?? null;
            if (!$data || $data['jenis'] !== $jenisTarget) continue;

            foreach ($produksi->detailPaletRotary as $palet) {
                $ukuran = $palet->ukuran;
                if (!$ukuran) continue;

                $vol = ($ukuran->panjang ?? 0)
                     * ($ukuran->lebar   ?? 0)
                     * ($ukuran->tebal   ?? 0)
                     * ($palet->total_lembar ?? 0)
                     / 10_000_000;

                $namaLahan = $palet->penggunaanLahan->lahan->nama_lahan ?? '-';
                $ukuranStr = "{$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal}";

                // Ambil nama kayu dari lahan yang dipakai mesin ini
                $namaKayu = $palet->penggunaanLahan->jenisKayu->nama_kayu ?? '-';

                $items[] = [
                    'urut'        => $urut++,
                    'jenis_pihak' => 'produksi',
                    'nama_pihak'  => $produksi->mesin->nama_mesin,
                    'nama_barang' => 'Mesin',
                    'keterangan'  => "KW {$palet->kw} - lahan {$namaLahan} - {$namaKayu}",
                    'ukuran'      => $ukuranStr,
                    'banyak'      => $palet->total_lembar,
                    'm3'          => round($vol, 6),
                    'harga'       => round($hargaVeneer, 4),
                    'hit_kbk'     => 'k',
                    'jumlah'      => round($vol * $hargaVeneer, 4),
                ];
            }
        }

        return $items;
    }

    /**
     * Items untuk Upah Tenaga Kerja & Hutang Gaji
     * Tiap baris = 1 pegawai (jenis_pihak='karyawan', nama_pihak=nama_pegawai)
     * Upah per pegawai = ongkos_mesin / jumlah_pegawai di mesin itu
     */
    private function itemsUpah(array $detailPegawaiUpah, string $keterangan): array
    {
        $items = [];
        $urut  = 1;

        foreach ($detailPegawaiUpah as $detail) {
            $items[] = [
                'urut'        => $urut++,
                'jenis_pihak' => 'karyawan',
                'nama_pihak'  => $detail['nama_pegawai'],
                'nama_barang' => '-',
                'keterangan'  => $detail['role'] . ' - ' . $detail['nama_mesin'],
                'ukuran'      => '-',
                'banyak'      => null,
                'm3'          => null,
                'harga'       => round((float) $detail['jumlah'], 4),
                'hit_kbk'     => null,
                'jumlah'      => round((float) $detail['jumlah'], 4),
            ];
        }

        return $items;
    }

    /**
     * Items untuk Persediaan Kayu 130 & Kayu 260
     * Tiap baris = 1 lahan, jumlah langsung dari poin (hit_kbk=null)
     */
    private function itemsKayu(array $detailKayuPerProduksi, bool $is130, string $keterangan): array
    {
        $items = [];
        $urut  = 1;

        foreach ($detailKayuPerProduksi as $lahanList) {
            foreach ($lahanList as $lahan) {
                if ($lahan['is_kayu_130'] !== $is130) continue;

                $items[] = [
                    'urut'        => $urut++,
                    'jenis_pihak' => 'pemasok',
                    'nama_pihak'  => 'Lahan ' . $lahan['kode_lahan'] . ' [' . $lahan['nama_lahan'] . ']',
                    'nama_barang' => 'Kayu',
                    'keterangan'  => $lahan['nama_kayu'] . ' - ' . $lahan['nama_mesin'] . ' - ' . $lahan['jumlah_batang'] . ' batang',
                    'ukuran'      => '-',
                    'banyak'      => $lahan['jumlah_batang'],   // jumlah batang
                    'm3'          => $lahan['stok_kubikasi'],   // kubikasi m³
                    'harga'       => $lahan['hpp_average'],     // Rp per m³
                    'hit_kbk'     => null,
                    'jumlah'      => round($lahan['poin'], 2),  // kubikasi × hpp
                ];
            }
        }

        return $items;
    }

    /**
     * Items untuk Bahan Penolong (Reeling Tape, dll)
     * Tiap baris = 1 mesin, jumlah langsung (hit_kbk=null)
     */
    private function itemsBahanPenolong(array $detail, string $keterangan): array
    {
        $items = [];
        $urut  = 1;

        foreach ($detail as $d) {
            $items[] = [
                'urut'        => $urut++,
                'jenis_pihak' => 'produksi',
                'nama_pihak'  => $d['nama_mesin'],
                'nama_barang' => $d['nama_bahan'],
                'keterangan'  => '-',
                'ukuran'      => '-',
                'banyak'      => null,
                'm3'          => null,
                'harga'       => round((float) $d['jumlah'], 4),
                'hit_kbk'     => null,
                'jumlah'      => round((float) $d['jumlah'], 4),
            ];
        }

        return $items;
    }

    /**
     * Items untuk Selisih (Keuntungan / Beban Kerugian)
     * Hanya 1 baris, jumlah langsung (hit_kbk=null)
     */
    private function itemsSelisih(float $nilai, string $keterangan): array
    {
        return [[
            'urut'        => 1,
            'jenis_pihak' => 'lain',
            'nama_pihak'  => '-',
            'nama_barang' => '-',
            'keterangan'  => 'Selisih D-K produksi rotary',
            'ukuran'      => '-',
            'banyak'      => null,
            'm3'          => null,
            'harga'       => round($nilai, 4),
            'hit_kbk'     => null,
            'jumlah'      => round($nilai, 4),
        ]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  KIRIM KE AKUNTANSI
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim payload ke endpoint akuntansi
     * Dipanggil dari Observer setelah semua mesin tervalidasi
     */
    public function sendToAkuntansi(array $payload, string $tanggal, ?Collection $produksiList = null): void
    {
        $url    = config('services.akuntansi.url') . '/api/jurnal/rotary/create';
        $apiKey = config('services.akuntansi.key');

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withoutVerifying()           // lokal: skip SSL
                ->withHeaders([
                    'X-API-KEY'    => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('[RotaryJurnal] Berhasil kirim ke akuntansi', [
                    'tanggal'  => $tanggal,
                    'response' => $response->json(),
                ]);

                // Kurangi stok HPP & tambah stok veneer basah setelah jurnal berhasil dikirim
                if ($produksiList) {
                    $this->kurangiStokHpp($produksiList, $tanggal);
                    $this->tambahStokVeneerBasah($produksiList, $tanggal);
                }
            } elseif ($response->status() === 409) {
                // Duplikasi — jurnal sudah pernah dibuat, tidak perlu panic
                Log::warning('[RotaryJurnal] Jurnal sudah ada di akuntansi (duplikasi)', [
                    'tanggal'  => $tanggal,
                    'response' => $response->json(),
                ]);
            } else {
                Log::error('[RotaryJurnal] Gagal kirim ke akuntansi', [
                    'tanggal'  => $tanggal,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[RotaryJurnal] Exception saat kirim ke akuntansi', [
                'tanggal' => $tanggal,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}