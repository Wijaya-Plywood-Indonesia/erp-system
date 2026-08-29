<?php

namespace App\Services;

use App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable;
use App\Filament\Resources\DetailNotaBarangMasuks\Tables\DetailNotaBarangMasuksTable;
use App\Models\LogLogCore;
use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use App\Models\StokLogCore;
use Illuminate\Database\Eloquent\Model;

class LogCoreInventoryService
{
    protected const PREFIX = 'Log Core - ';

    /**
     * Memproses penambahan stok Log Core dari Nota Barang Masuk.
     */
    public function processStockFromNotaMasuk(NotaBarangMasuk $nota, ?int $userId = null): void
    {
        $userId ??= auth()->id();

        $details = $nota->detail()
            ->where('nama_barang', 'like', static::PREFIX . '%')
            ->get();

        foreach ($details as $detail) {
            $this->tambahStokUntukDetail($nota, $detail, $userId);
        }
    }

    /**
     * Memproses pemotongan stok Log Core dari Nota Barang Keluar.
     */
    public function processStockFromNotaKeluar(NotaBarangKeluar $nota, ?int $userId = null): void
    {
        $userId ??= auth()->id();

        $details = $nota->detail()
            ->where('nama_barang', 'like', static::PREFIX . '%')
            ->get();

        foreach ($details as $detail) {
            $this->potongStokUntukDetail($nota, $detail, $userId);
        }
    }

    /**
     * Penambahan stok + pencatatan log riwayat (Barang Masuk).
     */
    protected function tambahStokUntukDetail(NotaBarangMasuk $nota, Model $detail, ?int $userId = null): void
    {
        $parsed = DetailNotaBarangMasuksTable::findLogCoreFromRecord($detail);

        if (! $parsed) {
            throw new \Exception("Gagal mengurai nama barang Log Core untuk \"{$detail->nama_barang}\".");
        }

        // 1. Dapatkan atau buat record stok dasar dengan lock
        $stok = StokLogCore::firstOrCreate(
            [
                'id_jenis_kayu' => $parsed['id_jenis_kayu'],
                'panjang'       => $parsed['panjang'],
            ],
            [
                'stok_qty'     => 0,
                'nilai_stok'   => 0,
                'harga_satuan' => 0,
            ]
        );

        // Kunci baris agar aman dari race condition
        $stok = StokLogCore::where('id', $stok->id)->lockForUpdate()->first();

        $qtyMasuk = (float) $detail->jumlah;
        $stokQtyBefore = (float) $stok->stok_qty;
        $nilaiStokBefore = (float) $stok->nilai_stok;

        // Ambil harga satuan stok lama (atau 0 jika produk baru)
        $hargaSatuan = (float) ($stok->harga_satuan ?? 0);
        $nilaiMasuk = $hargaSatuan * $qtyMasuk;

        $stokQtyAfter = $stokQtyBefore + $qtyMasuk;
        $nilaiStokAfter = $nilaiStokBefore + $nilaiMasuk;

        $namaValidator = auth()->user()?->name ?? 'Sistem';
        $keterangan = ($detail->keterangan ?: 'Masuk via Nota Barang Masuk')
            . ' | Divalidasi oleh ' . $namaValidator . ' pada ' . now()->format('d/m/Y H:i');

        // 2. Buat log transaksi
        $log = LogLogCore::create([
            'id_jenis_kayu'     => $stok->id_jenis_kayu,
            'panjang'           => $stok->panjang,
            'tanggal'           => $nota->tanggal,
            'tipe_transaksi'    => 'masuk',
            'keterangan'        => $keterangan,
            'referensi_type'    => get_class($detail),
            'referensi_id'      => $detail->id,
            'qty'               => $qtyMasuk,
            'harga_satuan'      => $hargaSatuan,
            'nilai'             => $nilaiMasuk,
            'stok_qty_before'   => $stokQtyBefore,
            'nilai_stok_before' => $nilaiStokBefore,
            'stok_qty_after'    => $stokQtyAfter,
            'nilai_stok_after'  => $nilaiStokAfter,
        ]);

        // 3. Perbarui saldo stok utama
        $stok->update([
            'stok_qty'    => $stokQtyAfter,
            'nilai_stok'  => $nilaiStokAfter,
            'id_last_log' => $log->id,
        ]);
    }

    /**
     * Pemotongan stok + pencatatan log riwayat (Barang Keluar).
     */
    protected function potongStokUntukDetail(NotaBarangKeluar $nota, Model $detail, ?int $userId = null): void
    {
        $stokRingan = DetailNotaBarangKeluarsTable::findLogCoreFromRecord($detail);

        if (! $stokRingan) {
            throw new \Exception("Data stok Log Core untuk \"{$detail->nama_barang}\" tidak ditemukan.");
        }

        $stok = StokLogCore::where('id', $stokRingan->id)->lockForUpdate()->first();

        $qty = (float) $detail->jumlah;

        if ($stok->stok_qty < $qty) {
            throw new \Exception(
                "Stok Log Core \"{$detail->nama_barang}\" tidak cukup. "
                    . "Tersedia: {$stok->stok_qty} batang, dibutuhkan: {$qty} batang."
            );
        }

        $stokQtyBefore = (float) $stok->stok_qty;
        $nilaiStokBefore = (float) $stok->nilai_stok;

        $hargaSatuan = (float) $stok->harga_satuan;
        $nilaiKeluar = $hargaSatuan * $qty;

        $stokQtyAfter = $stokQtyBefore - $qty;
        $nilaiStokAfter = max(0.0, $nilaiStokBefore - $nilaiKeluar);

        $namaValidator = auth()->user()?->name ?? 'Sistem';
        $keterangan = ($detail->keterangan ?: 'Keluar via Nota Barang Keluar')
            . ' | Divalidasi oleh ' . $namaValidator . ' pada ' . now()->format('d/m/Y H:i');

        $log = LogLogCore::create([
            'id_jenis_kayu'     => $stok->id_jenis_kayu,
            'panjang'           => $stok->panjang,
            'tanggal'           => $nota->tanggal,
            'tipe_transaksi'    => 'keluar',
            'keterangan'        => $keterangan,
            'referensi_type'    => get_class($detail),
            'referensi_id'      => $detail->id,
            'qty'               => $qty,
            'harga_satuan'      => $hargaSatuan,
            'nilai'             => $nilaiKeluar,
            'stok_qty_before'   => $stokQtyBefore,
            'nilai_stok_before' => $nilaiStokBefore,
            'stok_qty_after'    => $stokQtyAfter,
            'nilai_stok_after'  => $nilaiStokAfter,
        ]);

        $stok->update([
            'stok_qty'    => $stokQtyAfter,
            'nilai_stok'  => $nilaiStokAfter,
            'id_last_log' => $log->id,
        ]);
    }
}
