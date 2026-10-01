<?php

namespace App\Services;
use App\Models\LogLogCore;
use App\Models\StokLogCore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    /**
     * Catat transaksi manual (masuk / keluar) untuk stok Log Core.
     * Stok dan log diperbarui dalam satu transaksi database.
     *
     * @throws RuntimeException jika stok tidak cukup untuk transaksi keluar.
     */
    public function catatTransaksi(
        int $idJenisKayu,
        float $panjang,
        string $tipeTransaksi, // 'masuk' | 'keluar'
        float $qty,
        string $tanggal,
        ?string $keterangan = null,
    ): LogLogCore {
        return DB::transaction(function () use ($idJenisKayu, $panjang, $tipeTransaksi, $qty, $tanggal, $keterangan) {
            $stok = StokLogCore::firstOrCreate(
                ['id_jenis_kayu' => $idJenisKayu, 'panjang' => $panjang],
                ['stok_qty' => 0, 'harga_satuan' => 0, 'nilai_stok' => 0]
            );

            // Kunci baris agar aman dari race condition
            $stok = StokLogCore::where('id', $stok->id)->lockForUpdate()->first();

            $qtyBefore   = (float) $stok->stok_qty;
            $nilaiBefore = (float) $stok->nilai_stok;
            $harga       = (float) $stok->harga_satuan;
            $nilai       = $qty * $harga;

            if ($tipeTransaksi === 'masuk') {
                $qtyAfter   = $qtyBefore + $qty;
                $nilaiAfter = $nilaiBefore + $nilai;
            } else {
                if ($qtyBefore < $qty) {
                    throw new RuntimeException(
                        "Stok tidak cukup. Tersedia: {$qtyBefore} batang, diminta: {$qty} batang."
                    );
                }
                $qtyAfter   = $qtyBefore - $qty;
                $nilaiAfter = max(0.0, $nilaiBefore - $nilai);
            }

            $log = LogLogCore::create([
                'id_jenis_kayu'     => $idJenisKayu,
                'panjang'           => $panjang,
                'tanggal'           => $tanggal,
                'tipe_transaksi'    => $tipeTransaksi,
                'keterangan'        => $keterangan,
                'referensi_type'    => null,
                'referensi_id'      => null,
                'qty'               => $qty,
                'harga_satuan'      => $harga,
                'nilai'             => $nilai,
                'stok_qty_before'   => $qtyBefore,
                'nilai_stok_before' => $nilaiBefore,
                'stok_qty_after'    => $qtyAfter,
                'nilai_stok_after'  => $nilaiAfter,
            ]);

            $stok->update([
                'stok_qty'    => $qtyAfter,
                'nilai_stok'  => $nilaiAfter,
                'id_last_log' => $log->id,
            ]);
            return $log;
        });
    }
}
