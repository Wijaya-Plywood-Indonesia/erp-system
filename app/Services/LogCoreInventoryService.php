<?php

namespace App\Services;

use App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable;
use App\Models\LogLogCore;
use App\Models\NotaBarangKeluar;
use App\Models\StokLogCore;
use Illuminate\Database\Eloquent\Model;

class LogCoreInventoryService
{
    protected const PREFIX = 'Log Core - ';

    /**
     * Potong stok Log Core untuk semua baris "Log Core - ..." di dalam nota
     * keluar ini. Dipanggil dari dalam DB::transaction() saat tombol
     * "Validasi Nota" ditekan (lihat DetailNotaBarangKeluarsTable), jadi
     * kalau salah satu baris gagal (misal stok kurang), semua perubahan
     * lain dalam transaction yang sama otomatis ikut di-rollback.
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

    protected function potongStokUntukDetail(NotaBarangKeluar $nota, Model $detail, ?int $userId = null): void
    {
        // Reuse parsing logic yang sama dengan yang dipakai form Edit, biar
        // aturan "nama_barang -> baris stok mana" cuma didefinisikan sekali.
        $stokRingan = DetailNotaBarangKeluarsTable::findLogCoreFromRecord($detail);

        if (! $stokRingan) {
            throw new \Exception("Data stok Log Core untuk \"{$detail->nama_barang}\" tidak ditemukan.");
        }

        // Kunci baris stok (SELECT ... FOR UPDATE) supaya aman kalau ada dua
        // nota yang divalidasi hampir bersamaan dan sama-sama mengambil
        // Log Core dengan jenis kayu + panjang yang sama — tanpa lock ini,
        // dua transaction bisa sama-sama baca stok "cukup" sebelum salah
        // satunya benar-benar commit, sehingga stok bisa minus.
        $stok = StokLogCore::where('id', $stokRingan->id)->lockForUpdate()->first();

        $qty = (float) $detail->jumlah;

        if ($stok->stok_qty < $qty) {
            throw new \Exception(
                "Stok Log Core \"{$detail->nama_barang}\" tidak cukup. "
                    . "Tersedia: {$stok->stok_qty} batang, dibutuhkan: {$qty} batang."
            );
        }

        $stokQtyBefore = $stok->stok_qty;
        $nilaiStokBefore = $stok->nilai_stok;

        $hargaSatuan = $stok->harga_satuan;
        $nilaiKeluar = $hargaSatuan * $qty;

        $stokQtyAfter = $stokQtyBefore - $qty;
        $nilaiStokAfter = $nilaiStokBefore - $nilaiKeluar;

        $namaValidator = auth()->user()?->name ?? 'Sistem';
        $keterangan = ($detail->keterangan ?: 'Keluar via Nota Barang Keluar')
            . ' | Divalidasi oleh ' . $namaValidator . ' pada ' . now()->format('d/m/Y H:i');


        $log = LogLogCore::create([
            'id_jenis_kayu' => $stok->id_jenis_kayu,
            'panjang' => $stok->panjang,
            'tanggal' => $nota->tanggal,
            'tipe_transaksi' => 'keluar',
            'keterangan' => $keterangan,
            'referensi_type' => get_class($detail),
            'referensi_id' => $detail->id,
            'qty' => $qty,
            'harga_satuan' => $hargaSatuan,
            'nilai' => $nilaiKeluar,
            'stok_qty_before' => $stokQtyBefore,
            'nilai_stok_before' => $nilaiStokBefore,
            'stok_qty_after' => $stokQtyAfter,
            'nilai_stok_after' => $nilaiStokAfter,
        ]);

        $stok->update([
            'stok_qty' => $stokQtyAfter,
            'nilai_stok' => $nilaiStokAfter,
            'id_last_log' => $log->id,
        ]);
    }
}
