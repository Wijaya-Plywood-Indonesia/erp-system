<?php

namespace App\Services;

use App\Models\HppVeneerJadiLog;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerJadi;
use App\Models\Ukuran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StokVeneerJadiService
{
    /**
     * Bangun keterangan informatif:
     * "Dari Kedi (Palet 20) ke Repair - 01/07/2026"
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
     * Terima veneer kering, tapi dicatat sebagai stok Veneer Jadi
     * (dimensi/kw diambil otomatis dari sumber).
     */
    public function terimaRepair(SerahTerimaVeneerKering $serahTerima): StokVeneerJadi
    {
        $sumber = $serahTerima->sumber; // DetailHasil | DetailBongkarKedi

        if (! $sumber) {
            throw new \RuntimeException('Sumber data serah terima tidak ditemukan.');
        }

        $idJenisKayu = $sumber->id_jenis_kayu;
        $kwGrade = (string) $sumber->kw;
        $qty = (float) ($sumber->isi ?? $sumber->jumlah ?? 0);

        $ukuran = $sumber->ukuran ?? Ukuran::find($sumber->id_ukuran);
        $panjang = $ukuran->panjang ?? 0;
        $lebar = $ukuran->lebar ?? 0;
        $tebal = $ukuran->tebal ?? 0;

        // TODO: sesuaikan satuan panjang/lebar/tebal dengan rumus m3 yang benar
        $m3PerLembar = ((float) $panjang) * ((float) $lebar) * ((float) $tebal);
        $kubikasi = $qty * $m3PerLembar / 10000000;

        $keterangan = $this->buatKeterangan($serahTerima, $sumber);

        return DB::transaction(function () use (
            $serahTerima, $idJenisKayu, $panjang, $lebar, $tebal, $kwGrade, $qty, $kubikasi, $keterangan
        ) {
            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->lockForUpdate()
                ->first();

            $stokLembarBefore = $stok->stok_lembar ?? 0;
            $stokKubikasiBefore = $stok->stok_kubikasi ?? 0.0;
            $nilaiStokBefore = $stok->nilai_stok ?? 0.0;
            $hppAverage = $stok->hpp_average ?? 0.0; // TODO: hitung ulang HPP average sesuai rumus resmi

            // TODO: ganti dengan rumus HPP pekerja & bahan penolong yang resmi
            $hppPekerja = 0.0;
            $hppBahanPenolong = 0.0;
            $nilaiTransaksi = $kubikasi * $hppAverage;

            $stokLembarAfter = $stokLembarBefore + $qty;
            $stokKubikasiAfter = $stokKubikasiBefore + $kubikasi;
            $nilaiStokAfter = $nilaiStokBefore + $nilaiTransaksi;

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
                'kw_grade' => $kwGrade,
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'masuk',
                'keterangan' => $keterangan,
                'referensi_type' => SerahTerimaVeneerKering::class,
                'referensi_id' => $serahTerima->id,
                'total_lembar' => $qty,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => $hppPekerja,
                'hpp_bahan_penolong' => $hppBahanPenolong,
                'hpp_average' => $hppAverage,
                'nilai_stok' => $nilaiTransaksi,
                'stok_lembar_before' => $stokLembarBefore,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'nilai_stok_before' => $nilaiStokBefore,
                'stok_lembar_after' => $stokLembarAfter,
                'stok_kubikasi_after' => $stokKubikasiAfter,
                'nilai_stok_after' => $nilaiStokAfter,
            ]);

            return StokVeneerJadi::updateOrCreate(
                [
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'kw_grade' => $kwGrade,
                ],
                [
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverage,
                    'hpp_pekerja_last' => $hppPekerja,
                    'hpp_bahan_penolong_last' => $hppBahanPenolong,
                    'id_last_log' => $log->id,
                ]
            );
        });
    }
}
