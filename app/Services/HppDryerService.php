<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\DetailMasuk;
use App\Models\HargaPegawai;
use App\Models\HppLogHarian;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiPressDryer;
use App\Models\StokVeneerKering;
use App\Models\Target;
use App\Models\Ukuran;
use App\Models\ValidasiPressDryer;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HPP Veneer Kering per m³
 * ─────────────────────────────────────────────────────────────────────────────
 *   HPP KERING / m³ = HPP VENEER BASAH / m³  +  ONGKOS PEGAWAI / m³  +  ONGKOS MESIN / m³
 *
 *   • HPP veneer basah  : HPP rata-rata veneer basah (Log HPP Veneer Basah) saat veneer itu
 *                         dipakai dryer. Ini sudah berisi kayu + pekerja + mesin + bahan penolong
 *                         dari proses rotary.
 *   • Ongkos pegawai    : Σ (harga pegawai per hari ÷ jam target × jam kerja) semua pegawai
 *                         dryer pada produksi itu.
 *   • Ongkos mesin      : Σ ongkos_mesin semua mesin dryer pada produksi itu.
 *   • Pembagi           : total m³ SEMUA hasil produksi dryer tsb (panjang×lebar×tebal×lembar ÷ 10.000.000).
 *
 * Karena ongkos pegawai dan mesin selalu ≥ 0 dan DITAMBAHKAN ke HPP basah, HPP kering
 * tidak mungkin lebih kecil dari HPP basah.
 */
class HppDryerService
{
    /** panjang (cm) × lebar (cm) × tebal (mm) × lembar ÷ 10.000.000 = m³ (sama dengan Rotary & Gudang). */
    public const PEMBAGI_M3 = 10_000_000;

    /** Jam kerja normal jika target jam dryer belum diisi di menu Target. */
    private const JAM_NORMAL_DEFAULT = 10;

    /** @deprecated placeholder lama, hanya dipertahankan agar DashboardHppDryer tidak error. */
    public const HPP_VENEER_BASAH_PER_M3 = 1_000_000;

    /** Cache per-instance supaya hitung banyak palet tidak mengulang query. */
    private array $cacheBasah = [];

    /** Baris dryer + rincian komponennya per jenis kayu (0 = semua jenis). */
    private array $cacheBarisDryer = [];
    private array $cacheUkuran = [];

    // =========================================================================
    // ENTRY POINT — dipanggil dari Listener (saat produksi divalidasi)
    // =========================================================================

    public function prosesProduksi(int $idProduksiDryer): void
    {
        $this->prosesBanyakProduksi([$idProduksiDryer]);
    }

    /**
     * Hitung (ulang) HPP kering untuk satu atau banyak produksi dryer sekaligus.
     * Ledger stok tiap produk hanya dihitung ulang SEKALI di akhir.
     *
     * @param  int[]  $idProduksiList
     * @return int    jumlah baris stok yang diperbarui
     */
    public function prosesBanyakProduksi(array $idProduksiList): int
    {
        $idProduksiList = array_values(array_unique(array_filter(array_map('intval', $idProduksiList))));
        $jumlahBaris    = 0;

        DB::transaction(function () use ($idProduksiList, &$jumlahBaris) {
            $produkTerdampak = [];
            $tanggalTerdampak = [];
            $jenisTerdampak = [];

            foreach ($idProduksiList as $id) {
                $produksi = ProduksiPressDryer::with([
                    'detailHasils.ukuran',
                    'detailMasuks.ukuran',
                    'detailMesins.mesin',
                    'detailPegawais',
                ])->find($id);

                if (! $produksi) {
                    continue;
                }

                // Step 1: ongkos pegawai + mesin produksi ini
                $ongkos = $this->hitungOngkosDryer($produksi);

                // Step 2: HPP basah + ongkos → HPP kering tiap palet yang sudah masuk gudang
                foreach ($this->terapkanHppPalet($produksi, $ongkos) as $baris) {
                    $jumlahBaris++;
                    $produkTerdampak[$baris->id_ukuran . '|' . $baris->id_jenis_kayu . '|' . $baris->kw] = [
                        (int) $baris->id_ukuran, (int) $baris->id_jenis_kayu, (string) $baris->kw,
                    ];
                    $tanggalTerdampak[Carbon::parse($baris->tanggal_transaksi)->toDateString()] = true;
                    $jenisTerdampak[(int) $baris->id_jenis_kayu] = true;
                }
            }

            // Step 2b: sumber masuk lain (pilih veneer, opname, nota, repair, dll) memakai
            // komposisi HPP palet dryer yang baru dihitung → komponen & HPP-nya ikut terisi
            foreach ($this->turunkanSumberLainJenis(array_keys($jenisTerdampak)) as $k => $v) {
                $produkTerdampak[$k] = $v;
            }

            // Step 3: hitung ulang saldo m³ + nilai stok + HPP Average (ledger yang sama dengan modul mutasi)
            foreach ($produkTerdampak as [$idUkuran, $idJenis, $kw]) {
                $this->hitungUlangLedger($idUkuran, $idJenis, $kw);
            }

            // Step 4: log harian
            foreach (array_keys($tanggalTerdampak) as $tgl) {
                $this->updateLogHarian($tgl);
            }
        });

        return $jumlahBaris;
    }

    // =========================================================================
    // KOMPONEN ONGKOS PRODUKSI (pegawai + mesin) — hitungan murni, tidak menyimpan
    // =========================================================================

    /**
     * @return array{
     *   m3_total: float, pegawai: float, mesin: float, jumlah_pegawai: int, jumlah_mesin: int,
     *   harga_per_hari: float, jam_normal: float, rata_ongkos_mesin: float,
     *   pegawai_estimasi: bool, mesin_estimasi: bool
     * }
     */
    public function komponenProduksi(ProduksiPressDryer $produksi): array
    {
        $produksi->loadMissing(['detailHasils.ukuran', 'detailMesins.mesin', 'detailPegawais']);

        // ── m³ total semua hasil produksi ─────────────────────────────────────
        $m3Total = (float) $produksi->detailHasils->sum(fn ($d) => $this->hitungM3DariDetail($d));

        // ── Ongkos pegawai: harga per hari ÷ jam target × jam kerja ───────────
        // Harga pegawai diambil sama seperti perhitungan HPP veneer basah (rotary).
        $hargaPerHari = (float) (HargaPegawai::first()->harga ?? 0);
        $jamNormal    = $this->jamNormalDryer($produksi);
        $hargaPerJam  = $jamNormal > 0 ? $hargaPerHari / $jamNormal : 0.0;

        $totalPegawai   = 0.0;
        $jumlahPegawai  = 0;

        foreach ($produksi->detailPegawais as $p) {
            $jam = $this->jamKerjaPegawai($p->masuk, $p->pulang);

            // Jam masuk/pulang kosong atau sama (0 jam) → dianggap kerja penuh satu shift,
            // supaya ongkos pegawai tidak hilang (nol).
            if ($jam <= 0) {
                $jam = $jamNormal;
            }

            $totalPegawai += $hargaPerJam * $jam;
            $jumlahPegawai++;
        }

        // ── Ongkos mesin: Σ ongkos_mesin tiap mesin dryer produksi ini ────────
        $totalMesin   = 0.0;
        $jumlahMesin  = 0;

        foreach ($produksi->detailMesins as $dm) {
            $totalMesin += (float) ($dm->mesin->ongkos_mesin ?? 0);
            $jumlahMesin++;
        }

        // ── Cadangan: data pegawai / mesin produksi ini kosong atau nilainya 0 ──
        // Dipakai rata-rata ongkos (Rp per produksi) dari produksi dryer shift yang sama
        // terdekat yang datanya lengkap, supaya HPP kering tidak pernah memakai pegawai/mesin 0.
        $pegawaiEstimasi = false;
        $mesinEstimasi   = false;

        if ($totalPegawai <= 0) {
            $cadangan = $this->rataOngkosShift($produksi, 'ongkos_pekerja');
            if ($cadangan > 0) {
                $totalPegawai    = $cadangan;
                $pegawaiEstimasi = true;
                $jumlahPegawai   = 0;
            }
        }

        if ($totalMesin <= 0) {
            $cadangan = $this->rataOngkosShift($produksi, 'ongkos_mesin');
            if ($cadangan > 0) {
                $totalMesin    = $cadangan;
                $mesinEstimasi = true;
                $jumlahMesin   = 0;
            }
        }

        return [
            'pegawai_estimasi'  => $pegawaiEstimasi,
            'mesin_estimasi'    => $mesinEstimasi,
            'm3_total'          => $m3Total,
            'pegawai'           => round($totalPegawai, 2),
            'mesin'             => round($totalMesin, 2),
            'jumlah_pegawai'    => $jumlahPegawai,
            'jumlah_mesin'      => $jumlahMesin,
            'harga_per_hari'    => $hargaPerHari,
            'jam_normal'        => $jamNormal,
            'rata_ongkos_mesin' => $jumlahMesin > 0 ? round($totalMesin / $jumlahMesin, 2) : 0.0,
        ];
    }

    /**
     * Rata-rata ongkos (Rp per produksi) dari maksimal 10 produksi dryer shift sama terdekat
     * yang ongkos kolom tsb sudah terisi (> 0).
     */
    private function rataOngkosShift(ProduksiPressDryer $produksi, string $kolom): float
    {
        $nilai = OngkosProduksiDryer::query()
            ->where('id_produksi_dryer', '!=', $produksi->id)
            ->where($kolom, '>', 0)
            ->whereHas('produksi', fn ($q) => $q->where('shift', $produksi->shift))
            ->orderByDesc('id_produksi_dryer')
            ->limit(10)
            ->get([$kolom]);

        return $nilai->isEmpty() ? 0.0 : (float) $nilai->avg($kolom);
    }

    /** Simpan ongkos produksi (kecuali sudah dikunci final). */
    public function hitungOngkosDryer(ProduksiPressDryer $produksi): OngkosProduksiDryer
    {
        $komp = $this->komponenProduksi($produksi);

        $ongkos = OngkosProduksiDryer::firstOrNew(['id_produksi_dryer' => $produksi->id]);

        if ($ongkos->exists && $ongkos->is_final) {
            return $ongkos; // dikunci: angka rupiah tidak diubah
        }

        $total = $komp['pegawai'] + $komp['mesin'];

        $ongkos->total_m3           = round($komp['m3_total'], 6);
        $ongkos->ttl_pekerja        = $komp['jumlah_pegawai']; // 0 = memakai cadangan rata-rata shift
        $ongkos->jumlah_mesin       = $komp['jumlah_mesin'];
        $ongkos->tarif_per_pekerja  = $komp['harga_per_hari'];
        $ongkos->tarif_per_mesin    = $komp['rata_ongkos_mesin'];
        $ongkos->ongkos_pekerja     = $komp['pegawai'];
        $ongkos->ongkos_mesin       = $komp['mesin'];
        $ongkos->total_ongkos       = round($total, 2);
        $ongkos->ongkos_per_m3      = $komp['m3_total'] > 0 ? round($total / $komp['m3_total'], 4) : 0;
        $ongkos->save();

        return $ongkos;
    }

    // =========================================================================
    // HPP VENEER BASAH untuk satu palet hasil dryer
    // =========================================================================

    /**
     * HPP veneer basah per m³ yang dipakai untuk palet hasil dryer ini.
     * Urutan sumber (yang pertama > 0 dipakai):
     *   1. HPP Average pada log KELUAR veneer basah milik palet ini (dicatat saat produksi divalidasi)
     *      = angka yang sama dengan kolom HPP Average di Log HPP Veneer Basah.
     *   2. HPP Average stok veneer basah SEMUA modal produksi (input dryer), dibobot m³ modal.
     *   3. HPP Average stok veneer basah dengan jenis kayu + ukuran + KW palet ini.
     *   4. Rata-rata HPP Average semua KW (jenis kayu + ukuran sama), dibobot kubikasi.
     *   5. Komponen HPP palet basah MASUK terakhir (kayu+pekerja+mesin+bahan) untuk jenis kayu + ukuran sama.
     *   6-7. Cadangan terakhir: HPP Average veneer basah jenis kayu yang sama (tebal sama dulu, lalu semua ukuran).
     */
    public function hppBasahUntukPalet(DetailHasil $dh): float
    {
        if (isset($this->cacheBasah[$dh->id])) {
            return $this->cacheBasah[$dh->id];
        }

        $dh->loadMissing('ukuran');
        $u = $dh->ukuran;

        // 1. Log keluar basah milik palet ini
        $hpp = (float) HppVeneerBasahLog::where('tipe_transaksi', 'keluar')
            ->where('referensi_type', DetailHasil::class)
            ->where('referensi_id', $dh->id)
            ->where('hpp_average', '>', 0)
            ->orderByDesc('id')
            ->value('hpp_average');

        // 2. Semua modal produksi (input veneer basah yang dipakai dryer), dibobot m³ modal.
        // Ukuran veneer kering bisa berbeda dari ukuran veneer basah (menyusut saat dikeringkan),
        // jadi HPP basah dihitung dari veneer basah yang benar-benar dimasukkan ke produksi ini.
        if ($hpp <= 0) {
            $totalM3 = 0.0;
            $totalNilai = 0.0;

            $modals = DetailMasuk::with('ukuran')
                ->where('id_produksi_dryer', $dh->id_produksi_dryer)
                ->get();

            foreach ($modals as $modal) {
                $mu = $modal->ukuran;
                if (! $mu) {
                    continue;
                }

                $s = $this->summaryBasah((int) $modal->id_jenis_kayu, $mu, (string) $modal->kw);

                $hppModal = $s ? (float) $s->hpp_average : 0.0;

                // KW modal tidak ada di stok basah → rata-rata semua KW ukuran yang sama
                if ($hppModal <= 0) {
                    $hppModal = (float) HppVeneerBasahSummary::where('id_jenis_kayu', $modal->id_jenis_kayu)
                        ->where('panjang', $mu->panjang)->where('lebar', $mu->lebar)->where('tebal', $mu->tebal)
                        ->where('hpp_average', '>', 0)
                        ->avg('hpp_average');
                }

                if ($hppModal > 0) {
                    $m3Modal = $this->hitungM3($mu, (float) $modal->isi);
                    $m3Modal = $m3Modal > 0 ? $m3Modal : 1.0;
                    $totalM3    += $m3Modal;
                    $totalNilai += $m3Modal * $hppModal;
                }
            }

            if ($totalM3 > 0) {
                $hpp = $totalNilai / $totalM3;
            }
        }

        // 3. KW palet hasil
        if ($hpp <= 0 && $u) {
            $s = $this->summaryBasah($dh->id_jenis_kayu, $u, (string) $dh->kw);
            $hpp = $s ? (float) $s->hpp_average : 0.0;
        }

        // 4. Semua KW
        if ($hpp <= 0 && $u) {
            $list = HppVeneerBasahSummary::where('id_jenis_kayu', $dh->id_jenis_kayu)
                ->where('panjang', $u->panjang)->where('lebar', $u->lebar)->where('tebal', $u->tebal)
                ->where('hpp_average', '>', 0)
                ->get();

            $kub = (float) $list->sum('stok_kubikasi');
            if ($list->isNotEmpty()) {
                $hpp = $kub > 0
                    ? (float) $list->sum(fn ($s) => (float) $s->hpp_average * (float) $s->stok_kubikasi) / $kub
                    : (float) $list->avg('hpp_average');
            }
        }

        // 5. Komponen palet basah masuk terakhir
        if ($hpp <= 0 && $u) {
            $log = HppVeneerBasahLog::where('tipe_transaksi', 'masuk')
                ->where('id_jenis_kayu', $dh->id_jenis_kayu)
                ->where('panjang', $u->panjang)->where('lebar', $u->lebar)->where('tebal', $u->tebal)
                ->whereRaw('(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0)) > 0')
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->first();

            if ($log) {
                $hpp = (float) $log->hpp_kayu + (float) $log->hpp_pekerja + (float) $log->hpp_mesin + (float) $log->hpp_bahan_penolong;
            }
        }

        // 6. Jenis kayu + tebal sama (ukuran lain), lalu 7. jenis kayu sama saja — dibobot kubikasi
        if ($hpp <= 0 && $u) {
            foreach ([true, false] as $denganTebal) {
                $list = HppVeneerBasahSummary::where('id_jenis_kayu', $dh->id_jenis_kayu)
                    ->when($denganTebal, fn ($q) => $q->where('tebal', $u->tebal))
                    ->where('hpp_average', '>', 0)
                    ->get();

                if ($list->isNotEmpty()) {
                    $kub = (float) $list->sum('stok_kubikasi');
                    $hpp = $kub > 0
                        ? (float) $list->sum(fn ($s) => (float) $s->hpp_average * (float) $s->stok_kubikasi) / $kub
                        : (float) $list->avg('hpp_average');
                    break;
                }
            }
        }

        // 8. Komponen palet basah MASUK terakhir untuk jenis kayu yang sama (ukuran apa saja)
        if ($hpp <= 0) {
            $log = HppVeneerBasahLog::where('tipe_transaksi', 'masuk')
                ->where('id_jenis_kayu', $dh->id_jenis_kayu)
                ->whereRaw('(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0)) > 0')
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->first();

            if ($log) {
                $hpp = (float) $log->hpp_kayu + (float) $log->hpp_pekerja + (float) $log->hpp_mesin + (float) $log->hpp_bahan_penolong;
            }
        }

        if ($hpp <= 0) {
            Log::warning('HppDryerService: HPP veneer basah tidak ditemukan', [
                'id_detail_hasil' => $dh->id,
                'id_jenis_kayu'   => $dh->id_jenis_kayu,
                'id_ukuran'       => $dh->id_ukuran,
                'kw'              => $dh->kw,
            ]);
        }

        return $this->cacheBasah[$dh->id] = round($hpp, 4);
    }

    private function summaryBasah(int $idJenisKayu, $ukuran, string $kw): ?HppVeneerBasahSummary
    {
        return HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
            ->where('panjang', $ukuran->panjang)
            ->where('lebar', $ukuran->lebar)
            ->where('tebal', $ukuran->tebal)
            ->where('kw', $kw)
            ->first();
    }

    // =========================================================================
    // TERAPKAN HPP KE BARIS STOK PALET
    // =========================================================================

    /**
     * Isi komponen HPP (basah, ongkos, kering, nilai, m³) pada baris stok MASUK
     * dari palet produksi ini. Saldo & HPP Average dihitung ulang oleh hitungUlangLedger().
     *
     * @return \Illuminate\Support\Collection<int, StokVeneerKering>
     */
    public function terapkanHppPalet(ProduksiPressDryer $produksi, OngkosProduksiDryer $ongkos)
    {
        $produksi->loadMissing('detailHasils.ukuran');
        $detailMap = $produksi->detailHasils->keyBy('id');

        // m³ pembagi = semua hasil produksi (aturan baru, sama dengan m³ gudang)
        $m3Total = (float) $produksi->detailHasils->sum(fn ($d) => $this->hitungM3DariDetail($d));

        // Rupiah ongkos: dari record ongkos (kalau final, angka yang dikunci tetap dipakai)
        $rpPegawai = (float) $ongkos->ongkos_pekerja;
        $rpMesin   = (float) $ongkos->ongkos_mesin;
        $ongkosPerM3 = $m3Total > 0 ? ($rpPegawai + $rpMesin) / $m3Total : 0.0;

        $barisList = StokVeneerKering::whereIn('id_detail_hasil_dryer', $detailMap->keys())
            ->where('jenis_transaksi', 'masuk')
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get();

        foreach ($barisList as $baris) {
            $dh = $detailMap->get($baris->id_detail_hasil_dryer);
            if (! $dh) {
                continue;
            }

            $m3        = $this->hitungM3($dh->ukuran, (float) $baris->qty);
            $hppBasah  = $this->hppBasahUntukPalet($dh);
            $hppKering = $hppBasah + $ongkosPerM3;

            $baris->update([
                'm3'                      => round($m3, 6),
                'hpp_veneer_basah_per_m3' => round($hppBasah, 4),
                'ongkos_dryer_per_m3'     => round($ongkosPerM3, 4),
                'hpp_kering_per_m3'       => round($hppKering, 4),
                'nilai_transaksi'         => round($hppKering * $m3, 4),
            ]);
        }

        return $barisList;
    }

    /**
     * Hitung ulang saldo lembar, saldo m³, nilai stok dan HPP Average satu produk
     * (urut tanggal lalu id). Memakai ledger yang sama dengan modul Veneer Mutasi.
     */
    public function hitungUlangLedger(int $idUkuran, int $idJenisKayu, string $kw): void
    {
        try {
            app(VeneerMutasiService::class)->recalculateStokKering($idUkuran, $idJenisKayu, $kw);
        } catch (\Throwable $e) {
            // Saldo akhir negatif = masalah data transaksi keluar, bukan masalah HPP.
            // Komponen HPP palet sudah tersimpan; cukup dicatat agar bisa ditelusuri.
            Log::warning('HppDryerService: ledger veneer kering tidak bisa dihitung ulang — ' . $e->getMessage());
        }
    }

    // =========================================================================
    // KOMPONEN HPP SEMUA SUMBER MASUK (untuk tampilan & turunan)
    // =========================================================================

    /** Nama sumber baris masuk (untuk label di Log HPP Veneer Kering). */
    public function labelSumber(StokVeneerKering $r): string
    {
        if ($r->id_detail_hasil_dryer) {
            return 'Press Dryer';
        }

        $ket = strtolower((string) $r->keterangan);

        return match (true) {
            str_contains($ket, 'pilih veneer')                          => 'Pilih Veneer',
            str_contains($ket, 'opname')                                => 'Opname',
            str_contains($ket, 'dari kedi')                             => 'Kedi',
            str_contains($ket, 'veneer jadi')                           => 'Veneer Jadi',
            str_starts_with($ket, 'dari ')                              => 'Repair / Serah Terima',
            $r->id_veneer_mutasi || str_contains($ket, 'no nota')       => 'Nota / Mutasi',
            default                                                     => 'Lainnya',
        };
    }

    /**
     * Rincian HPP per m³ untuk baris MASUK dari sumber apa pun.
     *  - Press Dryer: basah + ongkos (dipecah pegawai/mesin sesuai tabel Ongkos Produksi Dryer).
     *  - Sumber lain : basah + ongkos hasil turunan (lihat turunkanSumberLain), dipecah pegawai/mesin
     *                  mengikuti perbandingan komposisi palet dryer produk yang sama.
     *
     * @param  iterable<StokVeneerKering>  $rows
     * @return array<int, array{basah: float, pegawai: float, mesin: float, total: float, final: bool, ada_ongkos: bool, sumber: string, turunan: bool}>
     */
    public function komponenBaris(iterable $rows): array
    {
        $rows = collect($rows)->filter(fn ($r) => $r->jenis_transaksi === 'masuk')->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $hasil = [];

        // ── Sumber Press Dryer ────────────────────────────────────────────────
        $dryer = $rows->filter(fn ($r) => $r->id_detail_hasil_dryer);

        if ($dryer->isNotEmpty()) {
            $produksiPerDetail = DetailHasil::whereIn('id', $dryer->pluck('id_detail_hasil_dryer')->unique())
                ->pluck('id_produksi_dryer', 'id');

            $idProduksi = $produksiPerDetail->filter()->unique()->values();

            $ongkosMap = OngkosProduksiDryer::whereIn('id_produksi_dryer', $idProduksi)
                ->get()->keyBy('id_produksi_dryer');

            $sudahValidasi = ValidasiPressDryer::whereIn('id_produksi_dryer', $idProduksi)
                ->where('status', 'divalidasi')
                ->pluck('id_produksi_dryer')
                ->flip();

            foreach ($dryer as $r) {
                $idProd = $produksiPerDetail[$r->id_detail_hasil_dryer] ?? null;
                $o      = $idProd ? $ongkosMap->get($idProd) : null;

                $ongkos    = (float) $r->ongkos_dryer_per_m3;
                $rpPegawai = $o ? (float) $o->ongkos_pekerja : 0.0;
                $rpMesin   = $o ? (float) $o->ongkos_mesin : 0.0;
                $rpTotal   = $rpPegawai + $rpMesin;

                $hasil[$r->id] = [
                    'basah'      => (float) $r->hpp_veneer_basah_per_m3,
                    'pegawai'    => $rpTotal > 0 ? $ongkos * $rpPegawai / $rpTotal : 0.0,
                    'mesin'      => $rpTotal > 0 ? $ongkos * $rpMesin / $rpTotal : 0.0,
                    'total'      => (float) $r->hpp_kering_per_m3,
                    // FINAL = produksi sudah divalidasi dan data pegawai + mesinnya benar-benar ada
                    'final'      => $idProd !== null && $sudahValidasi->has($idProd)
                        && $o && (int) $o->ttl_pekerja > 0 && (int) $o->jumlah_mesin > 0,
                    'ada_ongkos' => $o !== null,
                    'sumber'     => 'Press Dryer',
                    'turunan'    => false,
                ];
            }
        }

        // ── Sumber lain: komponen turunan ────────────────────────────────────
        foreach ($rows->reject(fn ($r) => $r->id_detail_hasil_dryer) as $r) {
            $basah  = (float) $r->hpp_veneer_basah_per_m3;
            $ongkos = (float) $r->ongkos_dryer_per_m3;

            $komposisi = $this->komposisi((int) $r->id_ukuran, (int) $r->id_jenis_kayu, $r->tanggal_transaksi);
            $pm        = $komposisi ? $komposisi['pegawai'] + $komposisi['mesin'] : 0.0;
            $rasio     = $pm > 0 ? $komposisi['pegawai'] / $pm : 0.5;

            $hasil[$r->id] = [
                'basah'      => $basah,
                'pegawai'    => $ongkos * $rasio,
                'mesin'      => $ongkos * (1 - $rasio),
                'total'      => (float) $r->hpp_kering_per_m3,
                'final'      => (bool) ($komposisi['final'] ?? false),
                'ada_ongkos' => true,
                'sumber'     => $this->labelSumber($r),
                'turunan'    => true,
            ];
        }

        return $hasil;
    }

    /** Palet masuk dari dryer (berHPP) satu jenis kayu + komponennya; 0 = semua jenis kayu. */
    private function barisDryerJenis(int $idJenisKayu): Collection
    {
        if (isset($this->cacheBarisDryer[$idJenisKayu])) {
            return $this->cacheBarisDryer[$idJenisKayu];
        }

        $rows = StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->whereNotNull('id_detail_hasil_dryer')
            ->where('hpp_kering_per_m3', '>', 0)
            ->where('hpp_veneer_basah_per_m3', '>', 0)
            ->when($idJenisKayu > 0, fn ($q) => $q->where('id_jenis_kayu', $idJenisKayu))
            ->with('ukuran:id,tebal')
            ->get(['id', 'id_ukuran', 'id_jenis_kayu', 'kw', 'tanggal_transaksi', 'jenis_transaksi', 'm3',
                'id_detail_hasil_dryer', 'hpp_veneer_basah_per_m3', 'ongkos_dryer_per_m3', 'hpp_kering_per_m3'])
            ->unique('id_detail_hasil_dryer')
            ->values();

        $komp = $this->komponenBaris($rows);

        return $this->cacheBarisDryer[$idJenisKayu] = $rows
            ->map(function ($r) use ($komp) {
                $r->komp = $komp[$r->id] ?? null;

                return $r;
            })
            ->filter(fn ($r) => $r->komp !== null)
            ->values();
    }

    /**
     * Komposisi HPP per m³ (basah, pegawai, mesin) yang dipakai untuk sumber masuk non-dryer:
     * rata-rata tertimbang m³ dari palet dryer, dengan urutan kedekatan:
     *   ukuran + jenis kayu sama (semua KW) → jenis kayu + tebal sama → jenis kayu sama → semua jenis kayu.
     * Palet dryer yang tanggalnya ≤ tanggal baris diutamakan; kalau belum ada dipakai semuanya.
     *
     * @return array{basah: float, pegawai: float, mesin: float}|null
     */
    private function komposisi(int $idUkuran, int $idJenisKayu, $tanggal): ?array
    {
        $this->cacheUkuran[$idUkuran] ??= Ukuran::find($idUkuran);
        $tebal = $this->cacheUkuran[$idUkuran]?->tebal;
        $t     = $tanggal ? Carbon::parse($tanggal) : null;

        $tingkat = [
            [$idJenisKayu, fn ($r) => (int) $r->id_ukuran === $idUkuran],
            [$idJenisKayu, fn ($r) => $tebal !== null && (float) $r->ukuran?->tebal === (float) $tebal],
            [$idJenisKayu, fn ($r) => true],
            [0,            fn ($r) => true],
        ];

        foreach ($tingkat as [$jenis, $cocok]) {
            $semua = $this->barisDryerJenis($jenis)->filter($cocok);

            if ($semua->isEmpty()) {
                continue;
            }

            $sampai = $t ? $semua->filter(fn ($r) => $r->tanggal_transaksi && Carbon::parse($r->tanggal_transaksi)->lte($t)) : collect();
            $pakai  = $sampai->isNotEmpty() ? $sampai : $semua;

            $m3 = (float) $pakai->sum(fn ($r) => (float) $r->m3);
            $rata = function (string $k) use ($pakai, $m3) {
                return $m3 > 0
                    ? (float) $pakai->sum(fn ($r) => $r->komp[$k] * (float) $r->m3) / $m3
                    : (float) $pakai->avg(fn ($r) => $r->komp[$k]);
            };

            return [
                'basah'   => $rata('basah'),
                'pegawai' => $rata('pegawai'),
                'mesin'   => $rata('mesin'),
                // FINAL hanya jika semua palet dryer yang dijadikan acuan sudah FINAL
                'final'   => $pakai->every(fn ($r) => $r->komp['final']),
            ];
        }

        return null;
    }

    /**
     * Isi komponen HPP baris masuk NON-dryer (pilih veneer, opname, nota/mutasi, repair, kedi,
     * veneer jadi, dll) dari komposisi palet dryer. Karena itu harga rata-rata gudang tetap
     * dekat dengan komponen HPP-nya, bukan tertarik ke 0.
     *
     * @return array<string, array{int,int,string}>  kunci produk yang berubah (untuk hitung ulang ledger)
     */
    private function terapkanTurunan(iterable $rows): array
    {
        $keys = [];

        foreach ($rows as $r) {
            $k = $this->komposisi((int) $r->id_ukuran, (int) $r->id_jenis_kayu, $r->tanggal_transaksi);

            if (! $k) {
                continue;
            }

            $ongkos = $k['pegawai'] + $k['mesin'];
            $total  = $k['basah'] + $ongkos;

            if ($total <= 0) {
                continue;
            }

            $r->update([
                'hpp_veneer_basah_per_m3' => round($k['basah'], 4),
                'ongkos_dryer_per_m3'     => round($ongkos, 4),
                'hpp_kering_per_m3'       => round($total, 4),
                'nilai_transaksi'         => round($total * (float) $r->m3, 4),
            ]);

            $keys[$r->id_ukuran . '|' . $r->id_jenis_kayu . '|' . $r->kw] = [(int) $r->id_ukuran, (int) $r->id_jenis_kayu, (string) $r->kw];
        }

        return $keys;
    }

    private function queryMasukNonDryer()
    {
        return StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->whereNull('id_detail_hasil_dryer');
    }

    /** Turunkan ulang SEMUA baris masuk non-dryer untuk jenis kayu tertentu (setelah palet dryer berubah). */
    public function turunkanSumberLainJenis(array $idJenisKayu): array
    {
        if (! $idJenisKayu) {
            return [];
        }

        $this->cacheBarisDryer = []; // palet dryer baru saja diperbarui

        return $this->terapkanTurunan(
            $this->queryMasukNonDryer()->whereIn('id_jenis_kayu', $idJenisKayu)->get()
        );
    }

    /** Turunkan baris masuk non-dryer satu produk yang belum punya komponen (dipanggil dari ledger). */
    public function turunkanSumberLainProduk(int $idUkuran, int $idJenisKayu, string $kw): void
    {
        $rows = $this->queryMasukNonDryer()
            ->where('id_ukuran', $idUkuran)->where('id_jenis_kayu', $idJenisKayu)->where('kw', $kw)
            ->where('hpp_veneer_basah_per_m3', '<=', 0)
            ->where('ongkos_dryer_per_m3', '<=', 0)
            ->get();

        if ($rows->isNotEmpty()) {
            $this->terapkanTurunan($rows);
        }
    }

    /**
     * Sinkron semua baris masuk non-dryer yang belum punya komponen (dari mana pun asalnya),
     * lalu hitung ulang ledger produk yang terdampak.
     *
     * @return int jumlah baris yang diisi
     */
    public function sinkronSumberLain(int $batas = 500): int
    {
        $rows = $this->queryMasukNonDryer()
            ->where('hpp_veneer_basah_per_m3', '<=', 0)
            ->where('ongkos_dryer_per_m3', '<=', 0)
            ->limit($batas)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($rows) {
            $keys = $this->terapkanTurunan($rows);

            foreach ($keys as [$u, $j, $kw]) {
                $this->hitungUlangLedger($u, $j, $kw);
            }

            return count($keys);
        });
    }

    // =========================================================================
    // TRANSAKSI KELUAR MANUAL
    // =========================================================================

    public function buatTransaksiKeluar(
        int $idUkuran,
        int $idJenisKayu,
        string $kw,
        float $m3Keluar,
        string $tanggal,
        ?string $keterangan = null,
    ): StokVeneerKering {
        return DB::transaction(function () use (
            $idUkuran,
            $idJenisKayu,
            $kw,
            $m3Keluar,
            $tanggal,
            $keterangan
        ) {
            $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);

            if ($snapshot['stok_m3'] < $m3Keluar) {
                throw new \Exception(
                    "Stok tidak cukup. Tersedia: {$snapshot['stok_m3']} m³, "
                        . "Diminta: {$m3Keluar} m³"
                );
            }

            $hppAvg       = $snapshot['hpp_average'];
            $nilaiKeluar  = $hppAvg * $m3Keluar;
            $stokSesudah  = $snapshot['stok_m3'] - $m3Keluar;
            $nilaiSesudah = $snapshot['nilai_stok'] - $nilaiKeluar;

            $stok = StokVeneerKering::create([
                'id_produksi_dryer'       => null,
                'id_ukuran'               => $idUkuran,
                'id_jenis_kayu'           => $idJenisKayu,
                'kw'                      => $kw,
                'jenis_transaksi'         => 'keluar',
                'tanggal_transaksi'       => $tanggal,
                'qty'                     => 0,
                'm3'                      => $m3Keluar,
                'hpp_veneer_basah_per_m3' => 0,
                'ongkos_dryer_per_m3'     => 0,
                'hpp_kering_per_m3'       => $hppAvg,
                'nilai_transaksi'         => $nilaiKeluar,
                'stok_m3_sebelum'         => $snapshot['stok_m3'],
                'nilai_stok_sebelum'      => $snapshot['nilai_stok'],
                'stok_m3_sesudah'         => $stokSesudah,
                'nilai_stok_sesudah'      => $nilaiSesudah,
                'hpp_average'             => $stokSesudah > 0
                    ? $nilaiSesudah / $stokSesudah
                    : $hppAvg,
                'keterangan'              => $keterangan,
            ]);

            $this->updateLogHarian($tanggal);

            return $stok;
        });
    }

    // =========================================================================
    // LOG HARIAN
    // =========================================================================

    public function updateLogHarian(Carbon|string $tanggal): void
    {
        $tgl = Carbon::parse($tanggal)->toDateString();

        $produkList = StokVeneerKering::whereDate('tanggal_transaksi', $tgl)
            ->selectRaw('DISTINCT id_ukuran, id_jenis_kayu, kw')
            ->get();

        foreach ($produkList as $produk) {
            $transaksi = StokVeneerKering::forProduk(
                $produk->id_ukuran,
                $produk->id_jenis_kayu,
                $produk->kw
            )->whereDate('tanggal_transaksi', $tgl)->get();

            $last = StokVeneerKering::forProduk(
                $produk->id_ukuran,
                $produk->id_jenis_kayu,
                $produk->kw
            )
                ->whereDate('tanggal_transaksi', '<=', $tgl)
                ->orderByDesc('tanggal_transaksi')
                ->orderByDesc('id')
                ->first();

            if (! $last) {
                continue;
            }

            // Rata-rata dibobot m³, hanya palet masuk yang sudah punya HPP (dari dryer)
            $masuk    = $transaksi->where('jenis_transaksi', 'masuk');
            $berHpp   = $masuk->filter(fn ($r) => (float) $r->hpp_kering_per_m3 > 0 && (float) $r->hpp_veneer_basah_per_m3 > 0);
            $m3Berhpp = (float) $berHpp->sum('m3');

            $avgOngkosDryer = $m3Berhpp > 0
                ? (float) $berHpp->sum(fn ($r) => (float) $r->ongkos_dryer_per_m3 * (float) $r->m3) / $m3Berhpp
                : 0;
            $avgHppBasah = $m3Berhpp > 0
                ? (float) $berHpp->sum(fn ($r) => (float) $r->hpp_veneer_basah_per_m3 * (float) $r->m3) / $m3Berhpp
                : 0;

            HppLogHarian::updateOrCreate(
                [
                    'tanggal'       => $tgl,
                    'id_ukuran'     => $produk->id_ukuran,
                    'id_jenis_kayu' => $produk->id_jenis_kayu,
                    'kw'            => $produk->kw,
                ],
                [
                    'total_m3_masuk'          => (float) $masuk->sum('m3'),
                    'total_m3_keluar'         => (float) $transaksi->where('jenis_transaksi', 'keluar')->sum('m3'),
                    'stok_akhir_m3'           => $last->stok_m3_sesudah,
                    'hpp_veneer_basah_per_m3' => round($avgHppBasah, 4),
                    'avg_ongkos_dryer_per_m3' => round($avgOngkosDryer, 4),
                    'hpp_kering_per_m3'       => round($avgHppBasah + $avgOngkosDryer, 4),
                    'hpp_average'             => $last->hpp_average,
                    'nilai_stok_akhir'        => $last->nilai_stok_sesudah,
                ]
            );
        }
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    /** m³ satu detail hasil (lembar = isi). */
    private function hitungM3DariDetail(DetailHasil $detail): float
    {
        return $this->hitungM3($detail->ukuran, (float) ($detail->isi ?? 0));
    }

    private function hitungM3($ukuran, float $lembar): float
    {
        if (! $ukuran) {
            return 0.0;
        }

        return ((float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal * $lembar)
            / self::PEMBAGI_M3;
    }

    /** Jam kerja normal dryer (dari menu Target: DRYER PAGI / DRYER MALAM). */
    private function jamNormalDryer(ProduksiPressDryer $produksi): float
    {
        $kode = strtoupper(trim((string) $produksi->shift)) === 'MALAM' ? 'DRYER MALAM' : 'DRYER PAGI';
        $jam  = (float) (Target::where('kode_ukuran', $kode)->value('jam') ?? 0);

        return $jam > 0 ? $jam : (float) self::JAM_NORMAL_DEFAULT;
    }

    /** Selisih jam masuk → pulang (jam desimal). Pulang < masuk = lewat tengah malam. */
    private function jamKerjaPegawai($masuk, $pulang): float
    {
        if (! $masuk || ! $pulang) {
            return 0.0;
        }

        try {
            $m = Carbon::parse($masuk);
            $p = Carbon::parse($pulang);
        } catch (\Throwable) {
            return 0.0;
        }

        if ($p->lt($m)) {
            $p->addDay();
        }

        return $m->diffInMinutes($p) / 60;
    }
}
