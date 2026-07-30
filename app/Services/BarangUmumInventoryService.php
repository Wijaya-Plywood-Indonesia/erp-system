<?php

namespace App\Services;

use App\Models\BarangUmum;
use App\Models\LogBarangUmum;
use App\Models\StokBarangUmum;
use Illuminate\Support\Facades\DB;

class BarangUmumInventoryService
{
    /**
     * Catat transaksi masuk atau keluar untuk satu barang umum.
     * Bisa dipanggil manual (dari form input) atau otomatis dari modul lain.
     */
    public function catatTransaksi(
        int $idBarangUmum,
        string $tipeTransaksi, // 'masuk' | 'keluar'
        float $qty,
        string $tanggal,
        ?string $keterangan = null,
        float $hargaSatuan = 0,
        ?string $referensiType = null,
        ?int $referensiId = null,
    ): LogBarangUmum {
        return DB::transaction(function () use (
            $idBarangUmum, $tipeTransaksi, $qty, $tanggal,
            $keterangan, $hargaSatuan, $referensiType, $referensiId
        ) {
            $barang = BarangUmum::findOrFail($idBarangUmum);

            $stok = StokBarangUmum::firstOrCreate(
                ['id_barang_umum' => $barang->id],
                ['stok_qty' => 0, 'harga_satuan' => 0, 'nilai_stok' => 0]
            );

            $stok = StokBarangUmum::where('id', $stok->id)->lockForUpdate()->first();

            $nilaiTransaksi = $qty * $hargaSatuan;

            $stokBefore = $stok->stok_qty;
            $nilaiBefore = $stok->nilai_stok;

            if ($tipeTransaksi === 'masuk') {
                $stokAfter  = $stokBefore + $qty;
                $nilaiAfter = $nilaiBefore + $nilaiTransaksi;

                // Update harga rata-rata sederhana (moving average) jika harga diisi
                $hppAverage = $hargaSatuan > 0
                    ? ($stokAfter > 0 ? $nilaiAfter / $stokAfter : 0)
                    : $stok->harga_satuan;
            } else {
                $stokAfter  = $stokBefore - $qty;
                $nilaiAfter = $nilaiBefore - $nilaiTransaksi;
                $hppAverage = $stok->harga_satuan;
            }

            $log = LogBarangUmum::create([
                'id_barang_umum'    => $barang->id,
                'tanggal'           => $tanggal,
                'tipe_transaksi'    => $tipeTransaksi,
                'keterangan'        => $keterangan,
                'referensi_type'    => $referensiType,
                'referensi_id'      => $referensiId,
                'qty'               => $qty,
                'harga_satuan'      => $hargaSatuan,
                'nilai'             => $nilaiTransaksi,
                'stok_qty_before'   => $stokBefore,
                'nilai_stok_before' => $nilaiBefore,
                'stok_qty_after'    => $stokAfter,
                'nilai_stok_after'  => $nilaiAfter,
            ]);

            $stok->update([
                'stok_qty'     => $stokAfter,
                'nilai_stok'   => $nilaiAfter,
                'harga_satuan' => $hppAverage,
                'id_last_log'  => $log->id,
            ]);

            return $log;
        });
    }
}