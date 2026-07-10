<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\HppLogHarian;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Models\VeneerKeringMutasiKeluarPalet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StokVeneerKeringService
{
    /**
     * Hitung m3 per lembar dari dimensi Ukuran.
     * TODO: sesuaikan satuan panjang/lebar/tebal di tabel `ukurans`
     * (mm/cm/m) dengan rumus konversi yang benar.
     */
    protected function m3PerLembar(?Ukuran $ukuran): float
    {
        if (! $ukuran) {
            return 0.0;
        }

        return ((float) $ukuran->panjang) * ((float) $ukuran->lebar) * ((float) $ukuran->tebal);
    }

    /**
     * Bangun keterangan informatif:
     * "Dari Press Dryer (Palet 44) ke Repair - 01/07/2026"
     */
    protected function buatKeterangan(SerahTerimaVeneerKering $serahTerima, $sumber): string
    {
        $labelSumber = $serahTerima->label_sumber; // "Press Dryer" | "Kedi"
        $noPalet = $sumber->no_palet ?? '-';

        $produksiRepair = $serahTerima->produksiRepair;
        $tanggalRepair = $produksiRepair?->tanggal
            ? Carbon::parse($produksiRepair->tanggal)->format('d/m/Y')
            : now()->format('d/m/Y');

        return "Dari {$labelSumber} (Palet {$noPalet}) ke Repair - {$tanggalRepair}";
    }

    /**
     * Bangun keterangan untuk sumber 'gudang' (Veneer Keluar dari
     * Gudang Veneer Kering menuju Repair):
     * "Keluar Gudang (Palet 2) - Tujuan: Repair - Dikeluarkan oleh: Admin -
     *  Diterima oleh: Budi - Produksi REPAIR - ke Repair - 01/07/2026"
     */
    protected function buatKeteranganGudang(SerahTerimaVeneerKering $serahTerima, VeneerKeringMutasiKeluarPalet $palet): string
    {
        $mutasi = $palet->mutasiKeluar;

        $produksiRepair = $serahTerima->produksiRepair;
        $tanggalRepair = $produksiRepair?->tanggal
            ? Carbon::parse($produksiRepair->tanggal)->format('d/m/Y')
            : now()->format('d/m/Y');

        $tujuan = $mutasi?->tujuan_keluar ?? 'Repair';
        $dikeluarkanOleh = $mutasi?->operator?->name ?? 'Tidak diketahui';

        // diterima_oleh tersimpan format "nama - Produksi REPAIR", ambil
        // nama bersihnya saja untuk keterangan yang lebih ringkas.
        $diterimaOlehRaw = $serahTerima->diterima_oleh ?: 'Tidak diketahui';
        $diterimaOleh = trim(explode(' - ', $diterimaOlehRaw)[0]);

        return "Keluar Gudang (Palet {$palet->no_palet}) - Tujuan: {$tujuan} - "
            . "Penyerah: {$dikeluarkanOleh} - Penerima: {$diterimaOleh} - {$tanggalRepair}";
    }

    /**
     * Terima veneer kering dari SerahTerimaVeneerKering (dryer/kedi) → nambah stok.
     */
    public function terimaRepair(SerahTerimaVeneerKering $serahTerima): StokVeneerKering
    {
        $sumber = $serahTerima->sumber; // DetailHasil | DetailBongkarKedi

        if (! $sumber) {
            throw new \RuntimeException('Sumber data serah terima tidak ditemukan.');
        }

        $idUkuran = $sumber->id_ukuran;
        $idJenisKayu = $sumber->id_jenis_kayu;
        $kw = (string) $sumber->kw;
        $qty = (float) ($sumber->isi ?? $sumber->jumlah ?? 0);

        $ukuran = $sumber->ukuran ?? Ukuran::find($idUkuran);
        $m3PerLembar = $this->m3PerLembar($ukuran);
        $m3 = $qty * $m3PerLembar / 10000000;

        $keterangan = $this->buatKeterangan($serahTerima, $sumber);

        return DB::transaction(function () use ($idUkuran, $idJenisKayu, $kw, $qty, $m3, $sumber, $keterangan) {
            $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
            $stokLembarSebelum = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $stokLembarSesudah = $stokLembarSebelum + (int) $qty;

            $stokM3Sebelum = $snapshot['stok_m3'];
            $stokM3Sesudah = $stokM3Sebelum + $m3;

            // TODO: ganti dengan rumus HPP resmi (basah per m3 + ongkos dryer per m3)
            $hppVeneerBasahPerM3 = 0.0;
            $ongkosDryerPerM3 = 0.0;
            $hppKeringPerM3 = $hppVeneerBasahPerM3 + $ongkosDryerPerM3;

            $nilaiTransaksi = $m3 * $hppKeringPerM3;
            $nilaiStokSebelum = $snapshot['nilai_stok'];
            $nilaiStokSesudah = $nilaiStokSebelum + $nilaiTransaksi;

            $hppAverage = $stokM3Sesudah > 0
                ? ($nilaiStokSesudah / $stokM3Sesudah)
                : $snapshot['hpp_average'];

            $stok = StokVeneerKering::create([
                'id_produksi_dryer' => $sumber instanceof DetailHasil ? $sumber->id_produksi_dryer : null,
                'id_detail_hasil_dryer' => $sumber instanceof DetailHasil ? $sumber->id : null,
                'id_ukuran' => $idUkuran,
                'id_jenis_kayu' => $idJenisKayu,
                'kw' => $kw,
                'jenis_transaksi' => 'masuk',
                'tanggal_transaksi' => now()->toDateString(),
                'qty' => $qty,
                'm3' => $m3,
                'stok_lembar_sebelum' => $stokLembarSebelum,
                'stok_lembar_sesudah' => $stokLembarSesudah,
                'hpp_veneer_basah_per_m3' => $hppVeneerBasahPerM3,
                'ongkos_dryer_per_m3' => $ongkosDryerPerM3,
                'hpp_kering_per_m3' => $hppKeringPerM3,
                'nilai_transaksi' => $nilaiTransaksi,
                'stok_m3_sebelum' => $stokM3Sebelum,
                'nilai_stok_sebelum' => $nilaiStokSebelum,
                'stok_m3_sesudah' => $stokM3Sesudah,
                'nilai_stok_sesudah' => $nilaiStokSesudah,
                'hpp_average' => $hppAverage,
                'keterangan' => $keterangan,
                'id_veneer_mutasi' => null,
                'id_veneer_mutasi_detail' => null,
            ]);

            $this->updateLogHarian($idUkuran, $idJenisKayu, $kw, $qty, $m3, $stokLembarSesudah, $stokM3Sesudah, $hppKeringPerM3, $hppAverage, $nilaiStokSesudah);

            return $stok;
        });
    }

    /**
     * Terima veneer kering dari SerahTerimaVeneerKering (tipe_sumber = 'gudang')
     * → INI titik dimana stok betul-betul berkurang & tercatat di Log.
     *
     * Alur: Gudang Veneer Kering "Proses Barang Keluar" HANYA mencatat
     * VeneerKeringMutasiKeluar + palet-paletnya, dan membuat antrean
     * SerahTerimaVeneerKering (tipe_sumber='gudang') per palet — belum
     * menyentuh stok. Baru saat palet itu di-"Terima" di Produksi Repair,
     * method ini dipanggil dan:
     *   1. Insert baris StokVeneerKering (jenis_transaksi='keluar')
     *   2. Panggil VeneerMutasiService::recalculateStokKering() supaya
     *      saldo & moving-average HPP ter-update dengan benar
     *   3. Update log harian (HppLogHarian) — total_lembar_keluar bertambah
     *
     * Palet yang TIDAK PERNAH diterima tidak pernah memanggil method ini,
     * jadi tidak pernah mengurangi stok/log — sesuai desain yang diminta.
     */
    public function terimaKeluarGudang(SerahTerimaVeneerKering $serahTerima): StokVeneerKering
    {
        $palet = $serahTerima->sumber; // VeneerKeringMutasiKeluarPalet

        if (! $palet instanceof VeneerKeringMutasiKeluarPalet) {
            throw new \RuntimeException('Data palet mutasi keluar tidak ditemukan.');
        }

        $idUkuran = (int) $palet->id_ukuran;
        $idJenisKayu = (int) $palet->id_jenis_kayu;
        $kw = (string) $palet->kw;
        $qty = (float) $palet->qty;

        if (! $idUkuran || ! $idJenisKayu) {
            throw new \RuntimeException('Data ukuran/jenis kayu pada mutasi keluar tidak lengkap.');
        }

        $ukuran = $palet->ukuran ?? Ukuran::find($idUkuran);
        $m3PerLembar = $this->m3PerLembar($ukuran);
        $m3 = $qty * $m3PerLembar / 10000000;

        $keterangan = $this->buatKeteranganGudang($serahTerima, $palet);

        return DB::transaction(function () use ($idUkuran, $idJenisKayu, $kw, $qty, $m3, $keterangan) {
            $saldo = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);

            if ($qty > $saldo) {
                throw new \RuntimeException(
                    "Stok tidak cukup. Saldo saat ini {$saldo} lbr, diminta " . (int) round($qty) . ' lbr.'
                );
            }

            // Nilai sebelum/sesudah diisi 0 dulu (placeholder) — akan dihitung
            // ulang dengan benar oleh recalculateStokKering() di bawah, sama
            // seperti pola yang sudah dipakai VeneerMutasiService::updateStokKering().
            $stok = StokVeneerKering::create([
                'id_produksi_dryer'       => null,
                'id_detail_hasil_dryer'   => null,
                'id_ukuran'               => $idUkuran,
                'id_jenis_kayu'           => $idJenisKayu,
                'kw'                      => $kw,
                'jenis_transaksi'         => 'keluar',
                'tanggal_transaksi'       => now()->toDateString(),
                'qty'                     => $qty,
                'm3'                      => $m3,
                'hpp_veneer_basah_per_m3' => 0,
                'ongkos_dryer_per_m3'     => 0,
                'hpp_kering_per_m3'       => 0,
                'nilai_transaksi'         => 0,
                'stok_lembar_sebelum'     => 0,
                'stok_lembar_sesudah'     => 0,
                'stok_m3_sebelum'         => 0,
                'stok_m3_sesudah'         => 0,
                'nilai_stok_sebelum'      => 0,
                'nilai_stok_sesudah'      => 0,
                'hpp_average'             => 0,
                'keterangan'              => $keterangan,
                'id_veneer_mutasi'        => null,
                'id_veneer_mutasi_detail' => null,
            ]);

            app(VeneerMutasiService::class)->recalculateStokKering($idUkuran, $idJenisKayu, $kw);

            // Update log harian, konsisten dengan konvensi yang sudah dipakai
            // di VeneerMutasiService::processStockFromNota().
            app(HppDryerService::class)->updateLogHarian(now()->toDateString());

            return $stok->fresh();
        });
    }

    /**
     * Upsert rekap harian di hpp_log_veneer_kering.
     */
    protected function updateLogHarian(
        int $idUkuran,
        int $idJenisKayu,
        string $kw,
        float $qty,
        float $m3,
        int $stokAkhirLembar,
        float $stokAkhirM3,
        float $hppKeringPerM3,
        float $hppAverage,
        float $nilaiStokAkhir
    ): void {
        $tanggal = now()->toDateString();

        $log = HppLogHarian::where('id_ukuran', $idUkuran)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('kw', $kw)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($log) {
            $log->update([
                'total_lembar_masuk' => $log->total_lembar_masuk + $qty,
                'total_m3_masuk' => $log->total_m3_masuk + $m3,
                'stok_akhir_lembar' => $stokAkhirLembar,
                'stok_akhir_m3' => $stokAkhirM3,
                'hpp_kering_per_m3' => $hppKeringPerM3,
                'hpp_average' => $hppAverage,
                'nilai_stok_akhir' => $nilaiStokAkhir,
            ]);

            return;
        }

        $saldoAwal = HppLogHarian::saldoTerakhir($idUkuran, $idJenisKayu, $kw, $tanggal);

        HppLogHarian::create([
            'tanggal' => $tanggal,
            'id_ukuran' => $idUkuran,
            'id_jenis_kayu' => $idJenisKayu,
            'kw' => $kw,
            'total_lembar_masuk' => $qty,
            'total_lembar_keluar' => 0,
            'stok_awal_lembar' => $saldoAwal['stok_akhir_lembar'],
            'stok_akhir_lembar' => $stokAkhirLembar,
            'total_m3_masuk' => $m3,
            'total_m3_keluar' => 0,
            'stok_akhir_m3' => $stokAkhirM3,
            'hpp_veneer_basah_per_m3' => 0,
            'avg_ongkos_dryer_per_m3' => 0,
            'hpp_kering_per_m3' => $hppKeringPerM3,
            'hpp_average' => $hppAverage,
            'nilai_stok_akhir' => $nilaiStokAkhir,
        ]);
    }
}
