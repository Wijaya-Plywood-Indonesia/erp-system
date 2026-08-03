<?php

namespace App\Services;

use App\Models\LogLogCore;
use App\Models\StokLogCore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LogCoreStokService
{
    public function tambahStok(
        int $idJenisKayu,
        float $panjang,
        float $qty,
        float $hargaSatuan,
        Model $referensi,
        string $keterangan,
        ?string $tanggal = null,
    ): LogLogCore {
        return DB::transaction(function () use ($idJenisKayu, $panjang, $qty, $hargaSatuan, $referensi, $keterangan, $tanggal) {
            $stok = StokLogCore::firstOrCreate(
                ['id_jenis_kayu' => $idJenisKayu, 'panjang' => $panjang],
                ['stok_qty' => 0, 'harga_satuan' => 0, 'nilai_stok' => 0]
            );

            $qtyBefore   = (float) $stok->stok_qty;
            $nilaiBefore = (float) $stok->nilai_stok;
            $nilaiMasuk  = $qty * $hargaSatuan;
            $qtyAfter    = $qtyBefore + $qty;
            $nilaiAfter  = $nilaiBefore + $nilaiMasuk;
            $hargaAverageAfter = $qtyAfter > 0 ? $nilaiAfter / $qtyAfter : 0;

            $log = LogLogCore::create([
                'id_jenis_kayu'     => $idJenisKayu,
                'panjang'           => $panjang,
                'tanggal'           => $tanggal ?? now(),
                'tipe_transaksi'    => 'masuk',
                'keterangan'        => $keterangan,
                'referensi_type'    => $referensi::class,
                'referensi_id'      => $referensi->id,
                'qty'               => $qty,
                'harga_satuan'      => $hargaSatuan,
                'nilai'             => $nilaiMasuk,
                'stok_qty_before'   => $qtyBefore,
                'nilai_stok_before' => $nilaiBefore,
                'stok_qty_after'    => $qtyAfter,
                'nilai_stok_after'  => $nilaiAfter,
            ]);

            $stok->update([
                'stok_qty'     => $qtyAfter,
                'harga_satuan' => $hargaAverageAfter,
                'nilai_stok'   => $nilaiAfter,
                'id_last_log'  => $log->id,
            ]);

            return $log;
        });
    }
}
