<?php

namespace App\Services;

use App\Models\DetailBongkarKedi;
use App\Models\DetailHasil;
use App\Models\DetailMasuk;
use App\Models\DetailMasukKedi;
use App\Models\HargaPegawai;
use App\Models\HppLogHarian;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\StokVeneerKering;
use App\Models\Target;
use App\Models\Ukuran;
use App\Models\ValidasiKedi;
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
 * Komponen HPP HANYA dihitung untuk veneer hasil PRODUKSI:
 *   • Press Dryer : pegawai & mesin dari produksi dryer tsb.
 *   • Kedi        : pegawai & mesin dari produksi kedi tsb (DetailPegawaiKedi + mesin kedi).
 * Sumber lain (opname, nota/mutasi, pilih veneer, repair, veneer jadi) TIDAK diberi komponen:
 * barisnya memakai HPP Average stok saat itu (lihat VeneerMutasiService::recalculateStokKering).
 *
 *   • HPP veneer basah : dari VENEER BASAH YANG DIMASUKKAN ke produksi (modal input), diambil
 *                        dari HPP Average stok basah. Asal angkanya dicatat di kolom
 *                        sumber_hpp_basah supaya bisa dijelaskan.
 *   • Ongkos pegawai   : Σ (harga pegawai per hari ÷ jam target × jam kerja) pegawai produksi itu.
 *   • Ongkos mesin     : Σ ongkos_mesin mesin produksi itu.
 *   • Pembagi          : total m³ SEMUA hasil produksi tsb (panjang×lebar×tebal×lembar ÷ 10.000.000).
 *
 * Karena ongkos pegawai dan mesin selalu ≥ 0 dan DITAMBAHKAN ke HPP basah, HPP kering
 * tidak mungkin lebih kecil dari HPP basah.
 */
class HppDryerService
{
    /** panjang (cm) × lebar (cm) × tebal (mm) × lembar ÷ 10.000.000 = m³ (sama dengan Rotary & Gudang). */
    public const PEMBAGI_M3 = 10_000_000;

    /** Jam kerja normal jika target jam belum diisi di menu Target. */
    private const JAM_NORMAL_DEFAULT = 10;

    /** @deprecated placeholder lama, hanya dipertahankan agar DashboardHppDryer tidak error. */
    public const HPP_VENEER_BASAH_PER_M3 = 1_000_000;

    /** Cache per-instance supaya hitung banyak palet tidak mengulang query. */
    private array $cacheBasah = [];

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
            $produkTerdampak  = [];
            $tanggalTerdampak = [];

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
                }
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
    // ENTRY POINT KEDI — dipanggil saat palet hasil bongkar kedi diterima gudang
    // =========================================================================

    /**
     * Hitung (ulang) HPP kering palet hasil KEDI dari data kedi sendiri:
     * veneer basah yang dimasukkan ke kedi + pegawai kedi + mesin kedi.
     *
     * @param  int[]  $idProduksiKediList
     * @return int    jumlah baris stok yang diperbarui
     */
    public function prosesBanyakKedi(array $idProduksiKediList): int
    {
        $idProduksiKediList = array_values(array_unique(array_filter(array_map('intval', $idProduksiKediList))));
        $jumlahBaris        = 0;

        DB::transaction(function () use ($idProduksiKediList, &$jumlahBaris) {
            $produkTerdampak  = [];
            $tanggalTerdampak = [];

            foreach ($idProduksiKediList as $id) {
                $produksi = ProduksiKedi::with([
                    'detailBongkarKedi.ukuran',
                    'detailMasukKedi.ukuran',
                    'detailPegawaiKedi',
                    'mesin',
                ])->find($id);

                if (! $produksi) {
                    continue;
                }

                foreach ($this->terapkanHppKedi($produksi) as $baris) {
                    $jumlahBaris++;
                    $produkTerdampak[$baris->id_ukuran . '|' . $baris->id_jenis_kayu . '|' . $baris->kw] = [
                        (int) $baris->id_ukuran, (int) $baris->id_jenis_kayu, (string) $baris->kw,
                    ];
                    $tanggalTerdampak[Carbon::parse($baris->tanggal_transaksi)->toDateString()] = true;
                }
            }

            foreach ($produkTerdampak as [$idUkuran, $idJenis, $kw]) {
                $this->hitungUlangLedger($idUkuran, $idJenis, $kw);
            }

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
     * Ongkos pegawai dryer.
     *
     * @return array{
     *   m3_total: float, pegawai: float, mesin: float, jumlah_pegawai: int, jumlah_mesin: int,
     *   harga_per_hari: float, jam_normal: float, rata_ongkos_mesin: float,
     *   pegawai_estimasi: bool, mesin_estimasi: bool, sumber_pegawai: string, sumber_mesin: string
     * }
     */
    public function komponenProduksi(ProduksiPressDryer $produksi): array
    {
        $produksi->loadMissing(['detailHasils.ukuran', 'detailMesins.mesin', 'detailPegawais']);

        // ── m³ total semua hasil produksi ─────────────────────────────────────
        $m3Total = (float) $produksi->detailHasils->sum(fn ($d) => $this->hitungM3DariDetail($d));

        // ── Ongkos pegawai: harga per hari ÷ jam target × jam kerja ───────────
        // Harga pegawai diambil sama seperti perhitungan HPP veneer basah (rotary).
        $hargaPerHari = $this->hargaPegawaiPerHari();
        $jamNormal    = $this->jamNormalDryer($produksi);

        [$totalPegawai, $jumlahPegawai] = $this->ongkosPegawaiDari($produksi->detailPegawais, $hargaPerHari, $jamNormal);

        // ── Ongkos mesin: Σ ongkos_mesin tiap mesin dryer produksi ini ────────
        [$totalMesin, $jumlahMesin, $namaMesin] = $this->ongkosMesinDari(
            $produksi->detailMesins->map(fn ($dm) => $dm->mesin)
        );

        // ── Cadangan: data pegawai / mesin produksi ini kosong atau nilainya 0 ──
        // Dipakai rata-rata ongkos (Rp per produksi) dari produksi dryer shift yang sama
        // terdekat yang datanya lengkap, supaya HPP kering tidak pernah memakai pegawai/mesin 0.
        $pegawaiEstimasi = false;
        $mesinEstimasi   = false;

        $sumberPegawai = $jumlahPegawai . ' pegawai × Rp ' . $this->angka($hargaPerHari)
            . '/hari ÷ ' . $this->angka($jamNormal, 1) . ' jam × jam kerja masing-masing (produksi dryer ini)';
        $sumberMesin = $jumlahMesin > 0
            ? 'Mesin produksi dryer ini: ' . implode(', ', $namaMesin)
            : '';

        if ($totalPegawai <= 0) {
            $cadangan = $this->rataOngkosShift($produksi, 'ongkos_pekerja');
            if ($cadangan > 0) {
                $totalPegawai    = $cadangan;
                $pegawaiEstimasi = true;
                $jumlahPegawai   = 0;
                $sumberPegawai   = 'ESTIMASI: rata-rata ongkos pegawai 10 produksi dryer shift ' . $produksi->shift
                    . ' terdekat (data pegawai produksi ini kosong / 0)';
            } else {
                $sumberPegawai = 'Data pegawai produksi dryer ini kosong dan tidak ada cadangan';
            }
        }

        if ($totalMesin <= 0) {
            $cadangan = $this->rataOngkosShift($produksi, 'ongkos_mesin');
            if ($cadangan > 0) {
                $totalMesin    = $cadangan;
                $mesinEstimasi = true;
                $jumlahMesin   = 0;
                $sumberMesin   = 'ESTIMASI: rata-rata ongkos mesin 10 produksi dryer shift ' . $produksi->shift
                    . ' terdekat (data mesin produksi ini kosong / ongkos mesin 0)';
            } else {
                $sumberMesin = 'Data mesin produksi dryer ini kosong dan tidak ada cadangan';
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
            'sumber_pegawai'    => $sumberPegawai,
            'sumber_mesin'      => $sumberMesin,
        ];
    }

    /**
     * Ongkos pegawai + mesin satu produksi KEDI (dari DetailPegawaiKedi dan mesin kedi).
     * Tidak ada cadangan rata-rata: kalau data kosong, angkanya 0 dan ditandai di sumber_*.
     *
     * @return array{m3_total: float, pegawai: float, mesin: float, jumlah_pegawai: int, jumlah_mesin: int, sumber_pegawai: string, sumber_mesin: string}
     */
    public function komponenKedi(ProduksiKedi $produksi): array
    {
        $produksi->loadMissing(['detailBongkarKedi.ukuran', 'detailPegawaiKedi', 'mesin']);

        $m3Total = (float) $produksi->detailBongkarKedi
            ->sum(fn ($d) => $this->hitungM3($d->ukuran, (float) ($d->jumlah ?? 0)));

        $hargaPerHari = $this->hargaPegawaiPerHari();
        $jamNormal    = $this->jamNormalKedi();

        [$totalPegawai, $jumlahPegawai] = $this->ongkosPegawaiDari($produksi->detailPegawaiKedi, $hargaPerHari, $jamNormal);
        [$totalMesin, $jumlahMesin, $namaMesin] = $this->ongkosMesinDari($produksi->mesin ? [$produksi->mesin] : []);

        $sumberPegawai = $jumlahPegawai > 0
            ? $jumlahPegawai . ' pegawai kedi × Rp ' . $this->angka($hargaPerHari)
                . '/hari ÷ ' . $this->angka($jamNormal, 1) . ' jam × jam kerja masing-masing'
            : 'Data pegawai produksi kedi ini kosong';

        $sumberMesin = $jumlahMesin > 0
            ? 'Mesin kedi: ' . implode(', ', $namaMesin) . ' (ongkos mesin dihitung 1× per produksi kedi)'
            : 'Mesin produksi kedi ini belum diisi';

        return [
            'm3_total'       => $m3Total,
            'pegawai'        => round($totalPegawai, 2),
            'mesin'          => round($totalMesin, 2),
            'jumlah_pegawai' => $jumlahPegawai,
            'jumlah_mesin'   => $jumlahMesin,
            'sumber_pegawai' => $sumberPegawai,
            'sumber_mesin'   => $sumberMesin,
        ];
    }

    private function hargaPegawaiPerHari(): float
    {
        return (float) (HargaPegawai::first()->harga ?? 0);
    }

    /**
     * Σ (harga per jam × jam kerja) tiap pegawai. Jam masuk/pulang kosong atau 0 jam
     * dianggap kerja penuh satu shift supaya ongkos pegawai tidak hilang.
     *
     * @return array{0: float, 1: int}  [total rupiah, jumlah pegawai]
     */
    private function ongkosPegawaiDari($daftarPegawai, float $hargaPerHari, float $jamNormal): array
    {
        $hargaPerJam = $jamNormal > 0 ? $hargaPerHari / $jamNormal : 0.0;
        $total       = 0.0;
        $jumlah      = 0;

        foreach ($daftarPegawai as $p) {
            $jam = $this->jamKerjaPegawai($p->masuk, $p->pulang);

            if ($jam <= 0) {
                // Ijin dan tidak ada jam kerja → tidak masuk kerja, tidak dihitung
                if (filled($p->ijin)) {
                    continue;
                }

                $jam = $jamNormal;
            }
            $total += $hargaPerJam * $jam;
            $jumlah++;
        }

        return [$total, $jumlah];
    }

    /**
     * Σ ongkos_mesin dari daftar mesin.
     *
     * @return array{0: float, 1: int, 2: string[]}  [total rupiah, jumlah mesin, daftar "nama (Rp ...)"]
     */
    private function ongkosMesinDari($daftarMesin): array
    {
        $total  = 0.0;
        $jumlah = 0;
        $nama   = [];

        foreach ($daftarMesin as $mesin) {
            $ongkos  = (float) ($mesin->ongkos_mesin ?? 0);
            $total  += $ongkos;
            $jumlah++;
            $nama[]  = ($mesin->nama_mesin ?? 'Mesin') . ' (Rp ' . $this->angka($ongkos) . ')';
        }

        return [$total, $jumlah, $nama];
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
    // HPP VENEER BASAH — dengan catatan ASAL angkanya
    // =========================================================================

    /**
     * HPP veneer basah per m³ untuk palet hasil dryer + asal angkanya.
     * Urutan sumber (yang pertama > 0 dipakai):
     *   1. MODAL PRODUKSI: veneer basah yang benar-benar dimasukkan ke produksi dryer ini
     *      (HPP Average stok basah per jenis kayu + ukuran + KW modal, dibobot m³ modal).
     *   2. HPP Average pada log KELUAR veneer basah milik palet ini.
     *   3-8. Cadangan (lihat hppBasahCadangan()).
     *
     * @return array{hpp: float, sumber: string}
     */
    public function rincianBasahUntukPalet(DetailHasil $dh): array
    {
        if (isset($this->cacheBasah['dh:' . $dh->id])) {
            return $this->cacheBasah['dh:' . $dh->id];
        }

        $dh->loadMissing('ukuran');

        // 1. Modal produksi (input veneer basah)
        $modals = DetailMasuk::with('ukuran')->where('id_produksi_dryer', $dh->id_produksi_dryer)->get();
        [$hpp, $sumber] = $this->hppBasahDariModal($modals, 'isi', 'Modal produksi dryer');

        // 2. Log keluar basah milik palet ini
        if ($hpp <= 0) {
            $hpp = $this->hppBasahLogKeluar(DetailHasil::class, (int) $dh->id);
            if ($hpp > 0) {
                $sumber = 'Log keluar veneer basah palet hasil ini (HPP Average saat produksi divalidasi)';
            }
        }

        // 3-8. Cadangan
        if ($hpp <= 0) {
            [$hpp, $sumber] = $this->hppBasahCadangan((int) $dh->id_jenis_kayu, $dh->ukuran, (string) $dh->kw);
        }

        if ($hpp <= 0) {
            $sumber = 'TIDAK DITEMUKAN: stok / log veneer basah untuk jenis kayu ini belum ada';
            Log::warning('HppDryerService: HPP veneer basah tidak ditemukan', [
                'id_detail_hasil' => $dh->id,
                'id_jenis_kayu'   => $dh->id_jenis_kayu,
                'id_ukuran'       => $dh->id_ukuran,
                'kw'              => $dh->kw,
            ]);
        }

        return $this->cacheBasah['dh:' . $dh->id] = ['hpp' => round($hpp, 4), 'sumber' => $sumber];
    }

    /** Sama seperti rincianBasahUntukPalet(), untuk palet hasil bongkar KEDI. */
    public function rincianBasahUntukKedi(DetailBongkarKedi $dbk): array
    {
        if (isset($this->cacheBasah['kd:' . $dbk->id])) {
            return $this->cacheBasah['kd:' . $dbk->id];
        }

        $dbk->loadMissing('ukuran');

        // 1. Modal kedi (veneer basah yang dimasukkan ke kedi)
        $modals = DetailMasukKedi::with('ukuran')->where('id_produksi_kedi', $dbk->id_produksi_kedi)->get();
        [$hpp, $sumber] = $this->hppBasahDariModal($modals, 'jumlah', 'Modal produksi kedi');

        // 2. Log keluar basah milik palet bongkar ini
        if ($hpp <= 0) {
            $hpp = $this->hppBasahLogKeluar(DetailBongkarKedi::class, (int) $dbk->id);
            if ($hpp > 0) {
                $sumber = 'Log keluar veneer basah palet bongkar kedi ini (HPP Average saat produksi divalidasi)';
            }
        }

        // 3-8. Cadangan
        if ($hpp <= 0) {
            [$hpp, $sumber] = $this->hppBasahCadangan((int) $dbk->id_jenis_kayu, $dbk->ukuran, (string) $dbk->kw);
        }

        if ($hpp <= 0) {
            $sumber = 'TIDAK DITEMUKAN: stok / log veneer basah untuk jenis kayu ini belum ada';
            Log::warning('HppDryerService: HPP veneer basah kedi tidak ditemukan', [
                'id_detail_bongkar_kedi' => $dbk->id,
                'id_jenis_kayu'          => $dbk->id_jenis_kayu,
                'id_ukuran'              => $dbk->id_ukuran,
                'kw'                     => $dbk->kw,
            ]);
        }

        return $this->cacheBasah['kd:' . $dbk->id] = ['hpp' => round($hpp, 4), 'sumber' => $sumber];
    }

    /** Dipertahankan agar pemanggil lama tetap jalan: hanya angka HPP basah. */
    public function hppBasahUntukPalet(DetailHasil $dh): float
    {
        return $this->rincianBasahUntukPalet($dh)['hpp'];
    }

    /** HPP Average pada log KELUAR basah milik satu detail (palet hasil dryer / palet bongkar kedi). */
    private function hppBasahLogKeluar(string $kelasReferensi, int $idReferensi): float
    {
        return (float) HppVeneerBasahLog::where('tipe_transaksi', 'keluar')
            ->where('referensi_type', $kelasReferensi)
            ->where('referensi_id', $idReferensi)
            ->where('hpp_average', '>', 0)
            ->orderByDesc('id')
            ->value('hpp_average');
    }

    /**
     * HPP basah dari veneer basah yang DIMASUKKAN ke produksi (modal), dibobot m³ modal.
     * Ukuran veneer kering bisa berbeda dari veneer basah (menyusut saat dikeringkan), jadi
     * yang dipakai adalah ukuran/KW modal.
     *
     * @param  iterable  $modals        DetailMasuk (kolom jumlah: isi) atau DetailMasukKedi (kolom jumlah: jumlah)
     * @return array{0: float, 1: string}  [hpp per m³, keterangan asal]
     */
    private function hppBasahDariModal(iterable $modals, string $kolomJumlah, string $label): array
    {
        $totalM3    = 0.0;
        $totalNilai = 0.0;
        $rincian    = [];

        foreach ($modals as $modal) {
            $mu = $modal->ukuran;
            if (! $mu) {
                continue;
            }

            $s        = $this->summaryBasah((int) $modal->id_jenis_kayu, $mu, (string) $modal->kw);
            $hppModal = $s ? (float) $s->hpp_average : 0.0;
            $semuaKw  = false;

            // KW modal tidak ada di stok basah → rata-rata semua KW ukuran yang sama
            if ($hppModal <= 0) {
                $hppModal = (float) HppVeneerBasahSummary::where('id_jenis_kayu', $modal->id_jenis_kayu)
                    ->where('panjang', $mu->panjang)->where('lebar', $mu->lebar)->where('tebal', $mu->tebal)
                    ->where('hpp_average', '>', 0)
                    ->avg('hpp_average');
                $semuaKw = $hppModal > 0;
            }

            if ($hppModal > 0) {
                $m3Modal = $this->hitungM3($mu, (float) $modal->{$kolomJumlah});
                $m3Modal = $m3Modal > 0 ? $m3Modal : 1.0;

                $totalM3    += $m3Modal;
                $totalNilai += $m3Modal * $hppModal;

                $kunci = (float) $mu->panjang . '×' . (float) $mu->lebar . '×' . (float) $mu->tebal
                    . ' KW ' . $modal->kw . ($semuaKw ? ' (rata-rata semua KW)' : '')
                    . ' = Rp ' . $this->angka($hppModal);
                $rincian[$kunci] = true;
            }
        }

        if ($totalM3 <= 0) {
            return [0.0, ''];
        }

        $daftar = array_keys($rincian);
        $teks   = implode('; ', array_slice($daftar, 0, 3)) . (count($daftar) > 3 ? '; +' . (count($daftar) - 3) . ' lainnya' : '');

        return [
            $totalNilai / $totalM3,
            $label . ': HPP Average stok basah dibobot m³ modal — ' . $teks,
        ];
    }

    /**
     * Sumber cadangan HPP basah bila modal & log keluar tidak ada.
     *   a. HPP Average stok basah jenis kayu + ukuran + KW yang sama
     *   b. Rata-rata semua KW (jenis kayu + ukuran sama), dibobot kubikasi
     *   c. Komponen palet basah MASUK terakhir (kayu+pekerja+mesin+bahan) ukuran sama
     *   d. Jenis kayu + tebal sama (ukuran lain), lalu jenis kayu sama saja — dibobot kubikasi
     *   e. Komponen palet basah MASUK terakhir jenis kayu sama (ukuran apa saja)
     *
     * @return array{0: float, 1: string}
     */
    private function hppBasahCadangan(int $idJenisKayu, ?Ukuran $u, string $kw): array
    {
        $kompSql = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0)) > 0';

        // a. KW sama
        if ($u) {
            $s   = $this->summaryBasah($idJenisKayu, $u, $kw);
            $hpp = $s ? (float) $s->hpp_average : 0.0;
            if ($hpp > 0) {
                return [$hpp, 'CADANGAN: HPP Average stok basah ukuran + KW hasil yang sama'];
            }
        }

        // b. Semua KW
        if ($u) {
            $list = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $u->panjang)->where('lebar', $u->lebar)->where('tebal', $u->tebal)
                ->where('hpp_average', '>', 0)
                ->get();

            if ($list->isNotEmpty()) {
                return [$this->rataBobotKubikasi($list), 'CADANGAN: rata-rata semua KW stok basah ukuran hasil yang sama'];
            }
        }

        // c. Palet basah masuk terakhir, ukuran sama
        if ($u) {
            $log = HppVeneerBasahLog::where('tipe_transaksi', 'masuk')
                ->where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $u->panjang)->where('lebar', $u->lebar)->where('tebal', $u->tebal)
                ->whereRaw($kompSql)
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->first();

            if ($log) {
                return [
                    (float) $log->hpp_kayu + (float) $log->hpp_pekerja + (float) $log->hpp_mesin + (float) $log->hpp_bahan_penolong,
                    'CADANGAN: komponen palet veneer basah masuk terakhir (ukuran sama)',
                ];
            }
        }

        // d. Jenis kayu + tebal sama, lalu jenis kayu sama saja
        if ($u) {
            foreach ([true, false] as $denganTebal) {
                $list = HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)
                    ->when($denganTebal, fn ($q) => $q->where('tebal', $u->tebal))
                    ->where('hpp_average', '>', 0)
                    ->get();

                if ($list->isNotEmpty()) {
                    return [
                        $this->rataBobotKubikasi($list),
                        $denganTebal
                            ? 'CADANGAN: rata-rata stok basah jenis kayu + tebal yang sama (ukuran lain)'
                            : 'CADANGAN: rata-rata stok basah jenis kayu yang sama (semua ukuran)',
                    ];
                }
            }
        }

        // e. Palet basah masuk terakhir, jenis kayu sama
        $log = HppVeneerBasahLog::where('tipe_transaksi', 'masuk')
            ->where('id_jenis_kayu', $idJenisKayu)
            ->whereRaw($kompSql)
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->first();

        if ($log) {
            return [
                (float) $log->hpp_kayu + (float) $log->hpp_pekerja + (float) $log->hpp_mesin + (float) $log->hpp_bahan_penolong,
                'CADANGAN: komponen palet veneer basah masuk terakhir (jenis kayu sama, ukuran lain)',
            ];
        }

        return [0.0, ''];
    }

    private function rataBobotKubikasi(Collection $list): float
    {
        $kub = (float) $list->sum('stok_kubikasi');

        return $kub > 0
            ? (float) $list->sum(fn ($s) => (float) $s->hpp_average * (float) $s->stok_kubikasi) / $kub
            : (float) $list->avg('hpp_average');
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
     * Isi komponen HPP (basah, pegawai, mesin, kering, nilai, m³) + asal angkanya pada baris
     * stok MASUK dari palet produksi dryer ini. Saldo & HPP Average dihitung ulang oleh
     * hitungUlangLedger().
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

        $pegawaiPerM3 = $m3Total > 0 ? $rpPegawai / $m3Total : 0.0;
        $mesinPerM3   = $m3Total > 0 ? $rpMesin / $m3Total : 0.0;
        $ongkosPerM3  = $pegawaiPerM3 + $mesinPerM3;

        // Keterangan asal pegawai & mesin (teks saja, tidak mempengaruhi angka)
        $komp = $this->komponenProduksi($produksi);

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
            $basah     = $this->rincianBasahUntukPalet($dh);
            $hppKering = $basah['hpp'] + $ongkosPerM3;

            $baris->update([
                'm3'                      => round($m3, 6),
                'hpp_veneer_basah_per_m3' => round($basah['hpp'], 4),
                'ongkos_pegawai_per_m3'   => round($pegawaiPerM3, 4),
                'ongkos_mesin_per_m3'     => round($mesinPerM3, 4),
                'ongkos_dryer_per_m3'     => round($ongkosPerM3, 4),
                'hpp_kering_per_m3'       => round($hppKering, 4),
                'nilai_transaksi'         => round($hppKering * $m3, 4),
                'sumber_hpp_basah'        => mb_substr($basah['sumber'], 0, 500),
                'sumber_pegawai'          => mb_substr($komp['sumber_pegawai'], 0, 500),
                'sumber_mesin'            => mb_substr($komp['sumber_mesin'], 0, 500),
            ]);
        }

        return $barisList;
    }

    /**
     * Sama seperti terapkanHppPalet(), untuk palet hasil bongkar KEDI:
     * HPP kering = HPP basah (modal kedi) + pegawai kedi/m³ + mesin kedi/m³.
     *
     * @return \Illuminate\Support\Collection<int, StokVeneerKering>
     */
    public function terapkanHppKedi(ProduksiKedi $produksi)
    {
        $produksi->loadMissing('detailBongkarKedi.ukuran');
        $detailMap = $produksi->detailBongkarKedi->keyBy('id');

        $komp    = $this->komponenKedi($produksi);
        $m3Total = $komp['m3_total'];

        $pegawaiPerM3 = $m3Total > 0 ? $komp['pegawai'] / $m3Total : 0.0;
        $mesinPerM3   = $m3Total > 0 ? $komp['mesin'] / $m3Total : 0.0;
        $ongkosPerM3  = $pegawaiPerM3 + $mesinPerM3;

        $barisList = StokVeneerKering::whereIn('id_detail_bongkar_kedi', $detailMap->keys())
            ->where('jenis_transaksi', 'masuk')
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get();

        foreach ($barisList as $baris) {
            $dbk = $detailMap->get($baris->id_detail_bongkar_kedi);
            if (! $dbk) {
                continue;
            }

            $m3        = $this->hitungM3($dbk->ukuran, (float) $baris->qty);
            $basah     = $this->rincianBasahUntukKedi($dbk);
            $hppKering = $basah['hpp'] + $ongkosPerM3;

            $baris->update([
                'm3'                      => round($m3, 6),
                'hpp_veneer_basah_per_m3' => round($basah['hpp'], 4),
                'ongkos_pegawai_per_m3'   => round($pegawaiPerM3, 4),
                'ongkos_mesin_per_m3'     => round($mesinPerM3, 4),
                'ongkos_dryer_per_m3'     => round($ongkosPerM3, 4),
                'hpp_kering_per_m3'       => round($hppKering, 4),
                'nilai_transaksi'         => round($hppKering * $m3, 4),
                'sumber_hpp_basah'        => mb_substr($basah['sumber'], 0, 500),
                'sumber_pegawai'          => mb_substr($komp['sumber_pegawai'], 0, 500),
                'sumber_mesin'            => mb_substr($komp['sumber_mesin'], 0, 500),
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
    // KOMPONEN HPP BARIS MASUK (untuk tampilan) — hanya sumber PRODUKSI
    // =========================================================================

    /** Nama sumber produksi satu baris masuk: 'Press Dryer', 'Kedi', atau null (bukan produksi). */
    public function labelSumber(StokVeneerKering $r): ?string
    {
        if ($r->id_detail_hasil_dryer) {
            return 'Press Dryer';
        }

        if ($r->id_detail_bongkar_kedi) {
            return 'Kedi';
        }

        return null;
    }

    /**
     * Rincian HPP per m³ untuk baris MASUK dari PRODUKSI (Press Dryer dan Kedi).
     * Baris dari sumber lain (opname, nota, pilih veneer, repair, dll) tidak punya komponen
     * dan TIDAK ikut dalam hasil — di tampilan tampil "—".
     *
     * @param  iterable<StokVeneerKering>  $rows
     * @return array<int, array{basah: float, pegawai: float, mesin: float, total: float, final: bool, ada_ongkos: bool, sumber: string, turunan: bool, sumber_basah: string, sumber_pegawai: string, sumber_mesin: string}>
     */
    public function komponenBaris(iterable $rows): array
    {
        $rows = collect($rows)
            ->filter(fn ($r) => $r->jenis_transaksi === 'masuk' && ($r->id_detail_hasil_dryer || $r->id_detail_bongkar_kedi))
            ->values();

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

                $ongkos  = (float) $r->ongkos_dryer_per_m3;
                $pegawai = (float) $r->ongkos_pegawai_per_m3;
                $mesin   = (float) $r->ongkos_mesin_per_m3;

                // Data lama (kolom rincian belum terisi): pecah ongkos menurut tabel Ongkos Produksi Dryer
                if ($pegawai + $mesin <= 0 && $ongkos > 0) {
                    $rpPegawai = $o ? (float) $o->ongkos_pekerja : 0.0;
                    $rpMesin   = $o ? (float) $o->ongkos_mesin : 0.0;
                    $rpTotal   = $rpPegawai + $rpMesin;
                    $pegawai   = $rpTotal > 0 ? $ongkos * $rpPegawai / $rpTotal : 0.0;
                    $mesin     = $rpTotal > 0 ? $ongkos * $rpMesin / $rpTotal : 0.0;
                }

                $hasil[$r->id] = [
                    'basah'          => (float) $r->hpp_veneer_basah_per_m3,
                    'pegawai'        => $pegawai,
                    'mesin'          => $mesin,
                    'total'          => (float) $r->hpp_kering_per_m3,
                    // FINAL = produksi sudah divalidasi dan data pegawai + mesinnya benar-benar ada
                    'final'          => $idProd !== null && $sudahValidasi->has($idProd)
                        && $o && (int) $o->ttl_pekerja > 0 && (int) $o->jumlah_mesin > 0,
                    'ada_ongkos'     => $o !== null,
                    'sumber'         => 'Press Dryer',
                    'turunan'        => false,
                    'sumber_basah'   => (string) $r->sumber_hpp_basah,
                    'sumber_pegawai' => (string) $r->sumber_pegawai,
                    'sumber_mesin'   => (string) $r->sumber_mesin,
                ];
            }
        }

        // ── Sumber Kedi ───────────────────────────────────────────────────────
        $kedi = $rows->filter(fn ($r) => $r->id_detail_bongkar_kedi && ! $r->id_detail_hasil_dryer);

        if ($kedi->isNotEmpty()) {
            $produksiKediPerDetail = DetailBongkarKedi::whereIn('id', $kedi->pluck('id_detail_bongkar_kedi')->unique())
                ->pluck('id_produksi_kedi', 'id');

            $kediValidasi = ValidasiKedi::whereIn('id_produksi_kedi', $produksiKediPerDetail->filter()->unique()->values())
                ->where('tipe', 'bongkar')
                ->where('status', 'divalidasi')
                ->pluck('id_produksi_kedi')
                ->flip();

            foreach ($kedi as $r) {
                $idProd  = $produksiKediPerDetail[$r->id_detail_bongkar_kedi] ?? null;
                $pegawai = (float) $r->ongkos_pegawai_per_m3;
                $mesin   = (float) $r->ongkos_mesin_per_m3;

                $hasil[$r->id] = [
                    'basah'          => (float) $r->hpp_veneer_basah_per_m3,
                    'pegawai'        => $pegawai,
                    'mesin'          => $mesin,
                    'total'          => (float) $r->hpp_kering_per_m3,
                    'final'          => $idProd !== null && $kediValidasi->has($idProd) && $pegawai > 0 && $mesin > 0,
                    'ada_ongkos'     => ($pegawai + $mesin) > 0,
                    'sumber'         => 'Kedi',
                    'turunan'        => false,
                    'sumber_basah'   => (string) $r->sumber_hpp_basah,
                    'sumber_pegawai' => (string) $r->sumber_pegawai,
                    'sumber_mesin'   => (string) $r->sumber_mesin,
                ];
            }
        }

        return $hasil;
    }

    // =========================================================================
    // SINKRON DATA LAMA
    // =========================================================================

    /**
     * Palet kedi lama belum punya id_detail_bongkar_kedi. Dicocokkan dari keterangan
     * "Dari Kedi (Palet X) ke Repair" + ukuran + jenis kayu + KW + jumlah lembar.
     * Hanya diisi bila cocok TEPAT SATU detail bongkar kedi.
     *
     * @return int jumlah baris yang berhasil diisi
     */
    public function isiRelasiKedi(int $batas = 500): int
    {
        $rows = StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->whereNull('id_detail_hasil_dryer')
            ->whereNull('id_detail_bongkar_kedi')
            ->where('keterangan', 'like', 'Dari Kedi (Palet %')
            ->limit($batas)
            ->get();

        $terisi = 0;

        foreach ($rows as $r) {
            if (! preg_match('/^Dari Kedi \(Palet (.+?)\)/', (string) $r->keterangan, $m)) {
                continue;
            }

            $kandidat = DetailBongkarKedi::where('no_palet', trim($m[1]))
                ->where('id_ukuran', $r->id_ukuran)
                ->where('id_jenis_kayu', $r->id_jenis_kayu)
                ->where('kw', $r->kw)
                ->where('jumlah', (float) $r->qty)
                ->get();

            if ($kandidat->count() === 1) {
                $r->update(['id_detail_bongkar_kedi' => $kandidat->first()->id]);
                $terisi++;
            }
        }

        return $terisi;
    }

    /** id produksi kedi dari palet kedi yang komponennya belum lengkap / belum punya asal. */
    public function idProduksiKediPerluSinkron(int $batas = 60): array
    {
        $idDetail = StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->whereNotNull('id_detail_bongkar_kedi')
            ->where(function ($q) {
                $q->whereNull('sumber_hpp_basah')
                    ->orWhere('hpp_veneer_basah_per_m3', '<=', 0)
                    ->orWhere('hpp_kering_per_m3', '<=', 0);
            })
            ->limit(2000)
            ->pluck('id_detail_bongkar_kedi');

        return DetailBongkarKedi::whereIn('id', $idDetail)
            ->pluck('id_produksi_kedi')
            ->filter()->unique()->take($batas)->values()->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Sumber BUKAN produksi (opname, nota, pilih veneer, repair, dll) yang dulu sempat diisi
     * komponen turunan dari rata-rata dryer dikembalikan ke kosong. Barisnya lalu memakai
     * HPP Average stok saat itu (ledger). Baris asli sumber lain tidak pernah punya
     * ongkos_dryer_per_m3 > 0, jadi hanya baris turunan yang tersentuh.
     *
     * @return int jumlah baris yang dikembalikan
     */
    public function bersihkanTurunanLama(int $batas = 500): int
    {
        $rows = StokVeneerKering::query()
            ->where('jenis_transaksi', 'masuk')
            ->whereNull('id_detail_hasil_dryer')
            ->whereNull('id_detail_bongkar_kedi')
            ->where(function ($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', 'Dari Kedi (Palet %');
            })
            ->where('ongkos_dryer_per_m3', '>', 0)
            ->limit($batas)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($rows) {
            $keys = [];

            foreach ($rows as $r) {
                $r->update([
                    'hpp_veneer_basah_per_m3' => 0,
                    'ongkos_pegawai_per_m3'   => 0,
                    'ongkos_mesin_per_m3'     => 0,
                    'ongkos_dryer_per_m3'     => 0,
                    'hpp_kering_per_m3'       => 0,
                    'nilai_transaksi'         => 0,
                    'sumber_hpp_basah'        => null,
                    'sumber_pegawai'          => null,
                    'sumber_mesin'            => null,
                ]);

                $keys[$r->id_ukuran . '|' . $r->id_jenis_kayu . '|' . $r->kw] = [(int) $r->id_ukuran, (int) $r->id_jenis_kayu, (string) $r->kw];
            }

            foreach ($keys as [$u, $j, $kw]) {
                $this->hitungUlangLedger($u, $j, $kw);
            }

            return $rows->count();
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

    private function angka(float $v, int $desimal = 0): string
    {
        return number_format($v, $desimal, ',', '.');
    }

    /** Jam kerja normal kedi (dari menu Target: KEDI). Bila belum ada, memakai jam normal bawaan. */
    private function jamNormalKedi(): float
    {
        $jam = (float) (Target::where('kode_ukuran', 'KEDI')->value('jam') ?? 0);

        return $jam > 0 ? $jam : (float) self::JAM_NORMAL_DEFAULT;
    }
}
