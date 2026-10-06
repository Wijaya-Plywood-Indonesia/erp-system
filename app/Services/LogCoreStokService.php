<?php

namespace App\Services;

use App\Models\HasilLogCore;
use App\Models\LogLogCore;
use App\Models\StokLogCore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LogCoreStokService
{
    public function tambahStok(
        int $idJenisKayu,
        float $panjang,
        float $qty,
        Model $referensi,
        string $keterangan,
        ?string $tanggal = null,
    ): LogLogCore {
        return DB::transaction(function () use ($idJenisKayu, $panjang, $qty, $referensi, $keterangan, $tanggal) {
            $stok = StokLogCore::firstOrCreate(
                ['id_jenis_kayu' => $idJenisKayu, 'panjang' => $panjang],
                ['stok_qty' => 0, 'harga_satuan' => 0, 'nilai_stok' => 0]
            );

            // Ambil ulang sambil mengunci baris stok
            $stok = StokLogCore::whereKey($stok->id)->lockForUpdate()->first();

            $qtyBefore = (float) $stok->stok_qty;
            $qtyAfter  = $qtyBefore + $qty;

            $log = LogLogCore::create([
                'id_jenis_kayu'     => $idJenisKayu,
                'panjang'           => $panjang,
                'tanggal'           => $tanggal ?? now(),
                'tipe_transaksi'    => 'masuk',
                'keterangan'        => $keterangan,
                'referensi_type'    => $referensi::class,
                'referensi_id'      => $referensi->id,
                'qty'               => $qty,
                'harga_satuan'      => 0,
                'nilai'             => 0,
                'stok_qty_before'   => $qtyBefore,
                'nilai_stok_before' => 0,
                'stok_qty_after'    => $qtyAfter,
                'nilai_stok_after'  => 0,
            ]);

            // Hanya jumlah yang berubah
            $stok->update([
                'stok_qty'    => $qtyAfter,
                'id_last_log' => $log->id,
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

    /**
     * Serahkan satu baris hasil log core ke stok.
     * - Aman dari double-klik / dua user bersamaan (baris dikunci).
     * - Tanggal log diisi dari parameter $tanggal (mis. tgl_produksi).
     *
     * @throws RuntimeException jika data sudah diserah sebelumnya.
     */
    public function serahHasilLogCore(HasilLogCore $hasil, string $tanggal): LogLogCore
    {
        return DB::transaction(function () use ($hasil, $tanggal) {
            // Baca ulang sambil mengunci baris, jangan percaya $hasil dari luar
            $terkini = HasilLogCore::lockForUpdate()->findOrFail($hasil->id);

            if ($terkini->sudah_diserah) {
                throw new RuntimeException('Data ini sudah diserah sebelumnya.');
            }

            return $this->tambahStok(
                idJenisKayu: (int) $terkini->id_jenis_kayu,
                panjang: (float) $terkini->panjang,
                qty: (float) $terkini->qty,
                referensi: $terkini,
                // Format WAJIB: "Serah oleh {nama}" dulu, baru " | {keterangan}"
                keterangan: 'Serah oleh ' . (Auth::user()?->name ?? 'Sistem')
                    . ($terkini->keterangan ? " | {$terkini->keterangan}" : ''),
                tanggal: $tanggal,
            );
        });
    }

    /**
     * Membalik efek tambahStok() untuk satu referensi (mis. satu baris hasil log core).
     * Membuat log baru bertipe 'keluar' (riwayat lama tidak dihapus).
     *
     * @throws RuntimeException jika log asli tidak ada atau stok sudah terpakai.
     */
    public function batalkanStok(Model $referensi, ?string $keterangan = null): LogLogCore
    {
        return DB::transaction(function () use ($referensi, $keterangan) {
            // 1. Log terakhir harus bertipe 'masuk'
            $logAsli = LogLogCore::where('referensi_type', $referensi::class)
                ->where('referensi_id', $referensi->id)
                ->latest('id')
                ->first();

            if (! $logAsli || $logAsli->tipe_transaksi !== 'masuk') {
                throw new RuntimeException('Data ini tidak dalam status diserah, tidak bisa dibatalkan.');
            }

            // 2. Kunci baris stok
            $stok = StokLogCore::where('id_jenis_kayu', $logAsli->id_jenis_kayu)
                ->where('panjang', $logAsli->panjang)
                ->lockForUpdate()
                ->first();

            if (! $stok) {
                throw new RuntimeException('Data stok tidak ditemukan.');
            }

            $qty       = (float) $logAsli->qty;
            $qtyBefore = (float) $stok->stok_qty;

            // 3. Stok sudah terpakai? Tolak.
            if (round($qtyBefore, 2) < round($qty, 2)) {
                throw new RuntimeException(
                    "Tidak bisa dibatalkan, stok sudah terpakai. Stok tersedia: {$qtyBefore} batang, yang dibatalkan: {$qty} batang."
                );
            }

            $qtyAfter = $qtyBefore - $qty;

            // 4. Catat transaksi pembalik
            $log = LogLogCore::create([
                'id_jenis_kayu'     => $logAsli->id_jenis_kayu,
                'panjang'           => $logAsli->panjang,
                'tanggal'           => now(),
                'tipe_transaksi'    => 'keluar',
                'keterangan'        => $keterangan ?? 'Pembatalan serah hasil log core',
                'referensi_type'    => $referensi::class,
                'referensi_id'      => $referensi->id,
                'qty'               => $qty,
                'harga_satuan'      => 0,
                'nilai'             => 0,
                'stok_qty_before'   => $qtyBefore,
                'nilai_stok_before' => 0,
                'stok_qty_after'    => $qtyAfter,
                'nilai_stok_after'  => 0,
            ]);

            // 5. Update stok
            $stok->update([
                'stok_qty'    => $qtyAfter,
                'id_last_log' => $log->id,
            ]);

            return $log;
        });
    }

    /**
     * Batalkan serah satu baris hasil log core.
     * Baris dikunci dulu, baru dicek, supaya aman dari dua super_admin bersamaan.
     *
     * @throws RuntimeException jika tidak dalam status diserah atau stok sudah terpakai.
     */
    public function batalSerahHasilLogCore(HasilLogCore $hasil, ?string $keterangan = null): LogLogCore
    {
        return DB::transaction(function () use ($hasil, $keterangan) {
            $terkini = HasilLogCore::lockForUpdate()->findOrFail($hasil->id);

            return $this->batalkanStok($terkini, $keterangan);
        });
    }
}
