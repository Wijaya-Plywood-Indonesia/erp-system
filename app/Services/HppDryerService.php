<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\HppLogHarian;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiPressDryer;
use App\Models\StokVeneerKering;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HppDryerService
{
    /**
     * HPP Veneer Basah per m3 — sementara Rp 1.000.000 (placeholder).
     *
     * Cara ganti nanti setelah resource HPP Basah selesai:
     *   public static function getHppBasah(): float {
     *       return \App\Models\HppVeneerBasah::latest()->value('hpp_per_m3') ?? 1_000_000;
     *   }
     * Lalu ubah semua self::HPP_VENEER_BASAH_PER_M3 → self::getHppBasah()
     */
    public const HPP_VENEER_BASAH_PER_M3 = 1_000_000;

    // =========================================================================
    // ENTRY POINT — dipanggil dari Listener
    // =========================================================================

    /**
     * Proses satu sesi produksi (produksi baru atau koreksi data).
     *
     * Selalu rebuild dari tanggal produksi itu ke depan agar aman
     * untuk kasus koreksi data hari yang sama atau kemarin.
     */
    public function prosesProduksi(int $idProduksiDryer): void
    {
        DB::transaction(function () use ($idProduksiDryer) {
            $produksi = ProduksiPressDryer::with([
                'detailHasils.ukuran',
                'detailHasils.jenisKayu',
                'detailMesins',
                'detailPegawais',
            ])->findOrFail($idProduksiDryer);

            // Step 1: Hitung & simpan ongkos produksi untuk sesi ini
            $this->hitungOngkosDryer($produksi);

            // Step 2 & 3: Rebuild stok + log dari tanggal produksi ini ke hari ini
            $this->rebuildStokDariTanggal(
                $produksi->tanggal_produksi->toDateString()
            );
        });
    }

    // =========================================================================
    // KALKULASI ONGKOS DRYER
    // =========================================================================

    /**
     * Hitung ongkos produksi dryer per m3 untuk satu sesi (1 shift).
     *
     * Formula dari Ongkos_Dryer.xlsx:
     *   Total M3       = SUM(p × l × t × qty) / 10.000.000
     *   Ongkos Pekerja = pekerja_hadir × Rp 115.000
     *   Ongkos Mesin   = jumlah_mesin  × Rp 335.000
     *   Ongkos / M3    = (Pekerja + Mesin) / Total M3
     */
    public function hitungOngkosDryer(ProduksiPressDryer $produksi): OngkosProduksiDryer
    {
        $totalM3 = $produksi->detailHasils->sum(fn($d) => $this->hitungM3DariDetail($d));
        $ttlPekerja = $produksi->detailPegawais
            ->filter(fn($p) => !$p->ijin && $p->masuk !== null)
            ->count();
        $jumlahMesin = $produksi->detailMesins->count();

        $ongkos = OngkosProduksiDryer::firstOrNew(['id_produksi_dryer' => $produksi->id]);

        // Jangan timpa data yang sudah dikunci (is_final = true)
        if ($ongkos->is_final) {
            return $ongkos;
        }

        // Set tarif default jika record baru
        $ongkos->tarif_per_pekerja = $ongkos->tarif_per_pekerja ?? 115_000;
        $ongkos->tarif_per_mesin = $ongkos->tarif_per_mesin ?? 335_000;

        $pekerja = $ttlPekerja * $ongkos->tarif_per_pekerja;
        $mesin = $jumlahMesin * $ongkos->tarif_per_mesin;
        $total = $pekerja + $mesin;

        $ongkos->total_m3 = $totalM3;
        $ongkos->ttl_pekerja = $ttlPekerja;
        $ongkos->jumlah_mesin = $jumlahMesin;
        $ongkos->ongkos_pekerja = $pekerja;
        $ongkos->ongkos_mesin = $mesin;
        $ongkos->total_ongkos = $total;
        $ongkos->ongkos_per_m3 = $totalM3 > 0 ? $total / $totalM3 : 0;
        $ongkos->save();

        return $ongkos;
    }

    // =========================================================================
    // REBUILD STOK
    // =========================================================================

    /**
     * Rebuild semua baris stok dari tanggal tertentu sampai hari ini.
     *
     * Dipanggil setiap kali ada:
     *   - Produksi baru (tanggal hari ini)
     *   - Koreksi data produksi (tanggal kemarin atau hari ini)
     *
     * Karena koreksi maksimal kemarin, proses ini ringan (1–2 hari data).
     */
    public function rebuildStokDariTanggal(string $tanggal): void
    {
        // Kumpulkan semua produksi dari tanggal ini ke depan,
        // diurutkan berdasarkan waktu agar moving average terhitung berurutan.
        $produksiList = ProduksiPressDryer::with([
            'detailHasils.ukuran',
            'detailHasils.jenisKayu',
            'ongkosDryer',
        ])
            ->whereDate('tanggal_produksi', '>=', $tanggal)
            ->orderBy('tanggal_produksi')
            ->orderByRaw("FIELD(shift, 'pagi', 'malam')") // pagi sebelum malam
            ->get();

        // Kumpulkan semua kombinasi produk yang terpengaruh
        $produkTerdampak = $produksiList
            ->flatMap(fn($p) => $p->detailHasils->map(fn($d) => [
                'id_ukuran' => $d->id_ukuran,
                'id_jenis_kayu' => $d->id_jenis_kayu,
                'kw' => $d->kw,
            ]))
            ->unique(fn($p) => "{$p['id_ukuran']}-{$p['id_jenis_kayu']}-{$p['kw']}");

        // Hapus semua baris stok dari tanggal ini ke depan untuk produk terdampak
        foreach ($produkTerdampak as $produk) {
            StokVeneerKering::forProduk(
                $produk['id_ukuran'],
                $produk['id_jenis_kayu'],
                $produk['kw']
            )
                ->whereDate('tanggal_transaksi', '>=', $tanggal)
                ->delete();
        }

        // Buat ulang baris stok dari semua produksi secara berurutan
        foreach ($produksiList as $produksi) {
            $ongkos = $produksi->ongkosDryer ?? $this->hitungOngkosDryer($produksi);
            $this->buatBarisMasukDariProduksi($produksi, $ongkos);
        }

        // Update log harian untuk semua tanggal yang terpengaruh
        $tanggalList = $produksiList
            ->pluck('tanggal_produksi')
            ->map(fn($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values();

        foreach ($tanggalList as $tgl) {
            $this->updateLogHarian($tgl);
        }
    }

    /**
     * Buat baris stok masuk dari detail hasil satu sesi produksi.
     * Snapshot diambil dari baris terakhir yang sudah ada di tabel.
     */
    private function buatBarisMasukDariProduksi(
        ProduksiPressDryer $produksi,
        OngkosProduksiDryer $ongkos
    ): void {
        $ongkosPerM3 = (float) ($ongkos->ongkos_per_m3 ?? 0);
        $hppKeringPerM3 = self::HPP_VENEER_BASAH_PER_M3 + $ongkosPerM3;

        foreach ($produksi->detailHasils as $detail) {
            $m3 = $this->hitungM3DariDetail($detail);
            if ($m3 <= 0) {
                continue;
            }

            $snapshot = StokVeneerKering::snapshotTerakhir(
                $detail->id_ukuran,
                $detail->id_jenis_kayu,
                $detail->kw
            );
            $nilaiTransaksi = $hppKeringPerM3 * $m3;
            $stokSesudah = $snapshot['stok_m3'] + $m3;
            $nilaiSesudah = $snapshot['nilai_stok'] + $nilaiTransaksi;

            StokVeneerKering::create([
                'id_produksi_dryer' => $produksi->id,
                'id_ukuran' => $detail->id_ukuran,
                'id_jenis_kayu' => $detail->id_jenis_kayu,
                'kw' => $detail->kw,
                'jenis_transaksi' => 'masuk',
                'tanggal_transaksi' => $produksi->tanggal_produksi,
                'qty' => $detail->isi ?? 0,
                'm3' => $m3,
                'hpp_veneer_basah_per_m3' => self::HPP_VENEER_BASAH_PER_M3,
                'ongkos_dryer_per_m3' => $ongkosPerM3,
                'hpp_kering_per_m3' => $hppKeringPerM3,
                'nilai_transaksi' => $nilaiTransaksi,
                'stok_m3_sebelum' => $snapshot['stok_m3'],
                'nilai_stok_sebelum' => $snapshot['nilai_stok'],
                'stok_m3_sesudah' => $stokSesudah,
                'nilai_stok_sesudah' => $nilaiSesudah,
                'hpp_average' => $stokSesudah > 0
                    ? $nilaiSesudah / $stokSesudah
                    : $hppKeringPerM3,
                'keterangan' => "Masuk dari produksi #{$produksi->id} shift {$produksi->shift}",
            ]);
        }
    }

    // =========================================================================
    // TRANSAKSI KELUAR MANUAL
    // =========================================================================

    /**
     * Catat stok keluar (penjualan / pemakaian).
     * HPP keluar menggunakan hpp_average saat ini.
     */
    public function buatTransaksiKeluar(
        int $idUkuran,
        int $idJenisKayu,
        string $kw,
        float $m3Keluar,
        string $tanggal,
        ?string $keterangan = null,
    ): StokVeneerKering {
        return DB::transaction(function () use ($idUkuran, $idJenisKayu, $kw, $m3Keluar, $tanggal, $keterangan) {
            $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);

            if ($snapshot['stok_m3'] < $m3Keluar) {
                throw new \Exception(
                    "Stok tidak cukup. Tersedia: {$snapshot['stok_m3']} m³, "
                        . "Diminta: {$m3Keluar} m³"
                );
            }

            $hppAvg = $snapshot['hpp_average'];
            $nilaiKeluar = $hppAvg * $m3Keluar;
            $stokSesudah = $snapshot['stok_m3'] - $m3Keluar;
            $nilaiSesudah = $snapshot['nilai_stok'] - $nilaiKeluar;

            $stok = StokVeneerKering::create([
                'id_produksi_dryer' => null,
                'id_ukuran' => $idUkuran,
                'id_jenis_kayu' => $idJenisKayu,
                'kw' => $kw,
                'jenis_transaksi' => 'keluar',
                'tanggal_transaksi' => $tanggal,
                'qty' => 0,
                'm3' => $m3Keluar,
                'hpp_veneer_basah_per_m3' => self::HPP_VENEER_BASAH_PER_M3,
                'ongkos_dryer_per_m3' => max(0, $hppAvg - self::HPP_VENEER_BASAH_PER_M3),
                'hpp_kering_per_m3' => $hppAvg,
                'nilai_transaksi' => $nilaiKeluar,
                'stok_m3_sebelum' => $snapshot['stok_m3'],
                'nilai_stok_sebelum' => $snapshot['nilai_stok'],
                'stok_m3_sesudah' => $stokSesudah,
                'nilai_stok_sesudah' => $nilaiSesudah,
                'hpp_average' => $stokSesudah > 0
                    ? $nilaiSesudah / $stokSesudah
                    : $hppAvg,
                'keterangan' => $keterangan,
            ]);

            $this->updateLogHarian($tanggal);

            return $stok;
        });
    }

    // =========================================================================
    // LOG HARIAN
    // =========================================================================

    /**
     * Generate / update log HPP harian untuk satu tanggal.
     * Dipanggil otomatis setelah rebuild stok atau transaksi keluar.
     */
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

            if (!$last) {
                continue;
            }

            $masuk = $transaksi->where('jenis_transaksi', 'masuk');
            $avgOngkosDryer = $masuk->isNotEmpty() ? (float) $masuk->avg('ongkos_dryer_per_m3') : 0;

            HppLogHarian::updateOrCreate(
                [
                    'tanggal' => $tgl,
                    'id_ukuran' => $produk->id_ukuran,
                    'id_jenis_kayu' => $produk->id_jenis_kayu,
                    'kw' => $produk->kw,
                ],
                [
                    'total_m3_masuk' => (float) $masuk->sum('m3'),
                    'total_m3_keluar' => (float) $transaksi->where('jenis_transaksi', 'keluar')->sum('m3'),
                    'stok_akhir_m3' => $last->stok_m3_sesudah,
                    'hpp_veneer_basah_per_m3' => self::HPP_VENEER_BASAH_PER_M3,
                    'avg_ongkos_dryer_per_m3' => $avgOngkosDryer,
                    'hpp_kering_per_m3' => self::HPP_VENEER_BASAH_PER_M3 + $avgOngkosDryer,
                    'hpp_average' => $last->hpp_average,
                    'nilai_stok_akhir' => $last->nilai_stok_sesudah,
                ]
            );
        }
    }

    // =========================================================================
    // PRIVATE HELPER
    // =========================================================================

    /**
     * Hitung m3 dari satu baris DetailHasil.
     *
     * ⚠️  SESUAIKAN dengan struktur model Ukuran kamu!
     *
     * Asumsi saat ini:
     *   - Model Ukuran punya kolom: panjang, lebar, tebal
     *   - DetailHasil punya kolom: isi (jumlah lembar)
     *   - Formula: panjang × lebar × tebal × isi / 10.000.000
     */
    private function hitungM3DariDetail(DetailHasil $detail): float
    {
        // Jika DetailHasil sudah punya kolom m3, pakai langsung
        if (isset($detail->m3) && $detail->m3 > 0) {
            return (float) $detail->m3;
        }

        $ukuran = $detail->ukuran;

        if ($ukuran && isset($ukuran->panjang, $ukuran->lebar, $ukuran->tebal)) {
            $qty = $detail->isi ?? 1;
            return ((float) $ukuran->panjang
                * (float) $ukuran->lebar
                * (float) $ukuran->tebal
                * (float) $qty)
                / 10_000_000;
        }

        return 0.0;
    }
}
