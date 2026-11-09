<?php

namespace App\Services\BkBm;

use App\Models\Bk;
use App\Models\Bm;
use App\Models\JenisKayu;
use App\Models\LogLogCore;
use App\Models\StokLogCore;

/**
 * Log Core pada BK/BM baru. Logika sama dengan LogCoreInventoryService (menu lama), disalin karena
 * service lama hanya menerima model nota lama. Baris barang: "Log Core - {kayu} - {panjang} cm".
 */
class BkBmLogCoreService
{
    private const PREFIX = 'Log Core - ';

    private function parse(string $nama): ?array
    {
        if (! str_starts_with($nama, self::PREFIX)) {
            return null;
        }
        $sisa = substr($nama, strlen(self::PREFIX));
        $pos = strrpos($sisa, ' - ');
        if ($pos === false) {
            return null;
        }
        $kayu = JenisKayu::where('nama_kayu', trim(substr($sisa, 0, $pos)))->first();
        if (! $kayu) {
            return null;
        }
        return ['id_jenis_kayu' => $kayu->id, 'panjang' => (float) str_replace(' cm', '', trim(substr($sisa, $pos + 3)))];
    }

    public function validasi(Bk|Bm $nota): void
    {
        $keluar = $nota instanceof Bk;
        $nama = auth()->user()?->name ?? 'Sistem';

        foreach ($nota->detail()->where('nama_barang', 'like', self::PREFIX . '%')->get() as $detail) {
            $p = $this->parse($detail->nama_barang)
                ?? throw new \RuntimeException("Gagal mengurai nama barang Log Core \"{$detail->nama_barang}\".");

            $stok = $keluar
                ? StokLogCore::where(['id_jenis_kayu' => $p['id_jenis_kayu'], 'panjang' => $p['panjang']])->lockForUpdate()->first()
                : StokLogCore::firstOrCreate(
                    ['id_jenis_kayu' => $p['id_jenis_kayu'], 'panjang' => $p['panjang']],
                    ['stok_qty' => 0, 'nilai_stok' => 0, 'harga_satuan' => 0]
                );

            if (! $stok) {
                throw new \RuntimeException("Data stok Log Core untuk \"{$detail->nama_barang}\" tidak ditemukan.");
            }
            $stok = StokLogCore::where('id', $stok->id)->lockForUpdate()->first();

            $qty = (float) $detail->jumlah;
            if ($keluar && $stok->stok_qty < $qty) {
                throw new \RuntimeException("Stok Log Core \"{$detail->nama_barang}\" tidak cukup. Tersedia: {$stok->stok_qty} batang, dibutuhkan: {$qty} batang.");
            }

            $qBefore = (float) $stok->stok_qty;
            $nBefore = (float) $stok->nilai_stok;
            $harga = (float) ($stok->harga_satuan ?? 0);
            $nilai = $harga * $qty;
            $qAfter = $keluar ? $qBefore - $qty : $qBefore + $qty;
            $nAfter = $keluar ? max(0.0, $nBefore - $nilai) : $nBefore + $nilai;

            $log = LogLogCore::create([
                'id_jenis_kayu' => $stok->id_jenis_kayu,
                'panjang' => $stok->panjang,
                'tanggal' => $nota->tanggal,
                'tipe_transaksi' => $keluar ? 'keluar' : 'masuk',
                'keterangan' => BkBmJejak::tempel(
                    ($detail->keterangan ?: ($keluar ? 'Keluar via BK' : 'Masuk via BM')) . ' (' . $nota->no_nota . ')',
                    $nota,
                    1000
                ),
                'referensi_type' => get_class($detail),
                'referensi_id' => $detail->id,
                'qty' => $qty,
                'harga_satuan' => $harga,
                'nilai' => $nilai,
                'stok_qty_before' => $qBefore,
                'nilai_stok_before' => $nBefore,
                'stok_qty_after' => $qAfter,
                'nilai_stok_after' => $nAfter,
            ]);

            $stok->update(['stok_qty' => $qAfter, 'nilai_stok' => $nAfter, 'id_last_log' => $log->id]);
        }
    }
}
