<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\HargaPegawai;
use App\Models\HppLogHarian;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiPressDryer;
use App\Models\StokVeneerKering;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HppDryerService
{
    // =========================================================================
    // ENTRY POINT — dipanggil dari Listener
    // =========================================================================

    public function prosesProduksi(int $idProduksiDryer): void
    {
        DB::transaction(function () use ($idProduksiDryer) {
            $produksi = ProduksiPressDryer::with([
                'detailHasils.ukuran',
                'detailHasils.jenisKayu',
                'detailMesins.mesin',
                'detailPegawais',
            ])->findOrFail($idProduksiDryer);

            // Step 1: Hitung & simpan ongkos produksi
            $ongkos = $this->hitungOngkosDryer($produksi);

            // Step 2: Update HPP di stok_veneer_kerings untuk produksi ini
            $this->updateHppStok($produksi, $ongkos);

            // Step 3: Update log harian
            $this->updateLogHarian($produksi->tanggal_produksi->toDateString());
        });
    }

    // =========================================================================
    // KALKULASI ONGKOS DRYER
    // =========================================================================

    public function hitungOngkosDryer(ProduksiPressDryer $produksi): OngkosProduksiDryer
    {
        $totalM3 = $produksi->detailHasils->sum(
            fn($d) => $this->hitungM3DariDetail($d)
        );

        // ── Ongkos Pegawai (per jam) ──────────────────────────────────────────
        // Ambil harga pegawai terbaru (per hari, 10 jam kerja)
        $hargaPerHari  = (float) (HargaPegawai::latest()->value('harga') ?? 0);
        $hargaPerJam   = $hargaPerHari / 10;

        $totalOngkosPegawai = $produksi->detailPegawais
            ->filter(fn($p) => $p->masuk !== null && $p->pulang !== null)
            ->sum(function ($p) use ($hargaPerJam) {
                $masuk  = Carbon::parse($p->masuk);
                $pulang = Carbon::parse($p->pulang);

                // Jika pulang < masuk, berarti lewat tengah malam
                if ($pulang->lt($masuk)) {
                    $pulang->addDay();
                }

                $jamKerja = $masuk->diffInMinutes($pulang) / 60;

                return $hargaPerJam * $jamKerja;
            });

        // ── Ongkos Mesin ──────────────────────────────────────────────────────
        // Ambil dari DetailMesin → relasi ke Mesin → ongkos_mesin per produksi
        $totalOngkosMesin = $produksi->detailMesins->sum(
            fn($dm) => (float) ($dm->mesin->ongkos_mesin ?? 0)
        );

        // ── Simpan ke OngkosProduksiDryer ─────────────────────────────────────
        $ongkos = OngkosProduksiDryer::firstOrNew([
            'id_produksi_dryer' => $produksi->id,
        ]);

        if ($ongkos->is_final ?? false) {
            return $ongkos;
        }

        $totalOngkos = $totalOngkosPegawai + $totalOngkosMesin;

        $ongkos->total_m3          = $totalM3;
        $ongkos->ongkos_pekerja    = round($totalOngkosPegawai, 2);
        $ongkos->ongkos_mesin      = round($totalOngkosMesin, 2);
        $ongkos->total_ongkos      = round($totalOngkos, 2);
        $ongkos->ongkos_per_m3     = $totalM3 > 0
            ? round($totalOngkos / $totalM3, 4)
            : 0;
        $ongkos->save();

        return $ongkos;
    }

    // =========================================================================
    // UPDATE HPP STOK SAAT VALIDASI
    // =========================================================================

    /**
     * Update HPP di semua baris stok_veneer_kerings milik produksi ini.
     * HPP basah sudah tersimpan saat serah, tinggal tambah ongkos dryer.
     * Lalu recalculate moving average.
     */
    private function updateHppStok(
        ProduksiPressDryer $produksi,
        OngkosProduksiDryer $ongkos
    ): void {
        $ongkosPerM3 = (float) ($ongkos->ongkos_per_m3 ?? 0);

        // Ambil semua baris stok dari produksi ini (via id_detail_hasil_dryer)
        $idDetailHasils = $produksi->detailHasils->pluck('id');

        $barisList = StokVeneerKering::whereIn('id_detail_hasil_dryer', $idDetailHasils)
            ->where('jenis_transaksi', 'masuk')
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get();

        foreach ($barisList as $baris) {
            $hppBasah      = (float) $baris->hpp_veneer_basah_per_m3;
            $hppKering     = $hppBasah + $ongkosPerM3;
            $nilaiTransaksi = $hppKering * (float) $baris->m3;

            // Ambil snapshot terbaru SEBELUM baris ini
            $snapshot = StokVeneerKering::forProduk(
                $baris->id_ukuran,
                $baris->id_jenis_kayu,
                $baris->kw
            )
                ->where('id', '<', $baris->id)
                ->orderByDesc('id')
                ->first();

            $m3Sebelum    = $snapshot ? (float) $snapshot->stok_m3_sesudah    : 0.0;
            $nilaiSebelum = $snapshot ? (float) $snapshot->nilai_stok_sesudah : 0.0;
            $m3Sesudah    = $m3Sebelum + (float) $baris->m3;
            $nilaiSesudah = $nilaiSebelum + $nilaiTransaksi;
            $hppAverage   = $m3Sesudah > 0 ? $nilaiSesudah / $m3Sesudah : $hppKering;

            $baris->update([
                'ongkos_dryer_per_m3' => $ongkosPerM3,
                'hpp_kering_per_m3'   => $hppKering,
                'nilai_transaksi'     => round($nilaiTransaksi, 4),
                'stok_m3_sebelum'     => round($m3Sebelum, 6),
                'nilai_stok_sebelum'  => round($nilaiSebelum, 4),
                'stok_m3_sesudah'     => round($m3Sesudah, 6),
                'nilai_stok_sesudah'  => round($nilaiSesudah, 4),
                'hpp_average'         => round($hppAverage, 4),
            ]);
        }
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
            $idUkuran, $idJenisKayu, $kw, $m3Keluar, $tanggal, $keterangan
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

            if (!$last) {
                continue;
            }

            $masuk          = $transaksi->where('jenis_transaksi', 'masuk');
            $avgOngkosDryer = $masuk->isNotEmpty()
                ? (float) $masuk->avg('ongkos_dryer_per_m3')
                : 0;
            $avgHppBasah    = $masuk->isNotEmpty()
                ? (float) $masuk->avg('hpp_veneer_basah_per_m3')
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
    // PRIVATE HELPER
    // =========================================================================

    private function hitungM3DariDetail(DetailHasil $detail): float
    {
        $ukuran = $detail->ukuran;

        if ($ukuran && isset($ukuran->panjang, $ukuran->lebar, $ukuran->tebal)) {
            $qty = $detail->isi ?? 1;
            return ((float) $ukuran->panjang
                * (float) $ukuran->lebar
                * (float) $ukuran->tebal
                * (float) $qty)
                / 1_000_000;
        }

        return 0.0;
    }
}
