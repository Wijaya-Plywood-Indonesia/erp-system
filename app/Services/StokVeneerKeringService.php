<?php

namespace App\Services;

use App\Models\DetailHasil;
use App\Models\HppLogHarian;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
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
