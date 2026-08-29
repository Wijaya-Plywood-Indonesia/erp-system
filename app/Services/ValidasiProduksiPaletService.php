<?php

namespace App\Services;

use App\Models\HasilProduksiPalet;
use App\Models\LogLogCore;
use App\Models\ProduksiPalet;
use App\Models\StokLogCore;
use App\Models\ValidasiProduksiPalet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValidasiProduksiPaletService
{
    /**
     * Memproses eksekusi pemotongan stok berdasarkan ProduksiPalet (Parent)
     */
    public static function prosesValidasiByProduksi(ProduksiPalet $produksi): void
    {
        // Cek apakah ada record validasi yang berstatus divalidasi/disetujui
        if (self::isStatusDivalidasi($produksi)) {
            DB::transaction(function () use ($produksi) {
                self::potongStokDanLog($produksi);
            });
        }
    }

    /**
     * Memproses eksekusi pemotongan stok berdasarkan ValidasiProduksiPalet (Record)
     */
    public static function prosesValidasi(ValidasiProduksiPalet $validasi): void
    {
        $statusApproved = ['divalidasi', 'disetujui'];

        // Load ulang relasi jika null
        $produksiPalet = $validasi->produksiPalet ?? ProduksiPalet::find($validasi->id_produksi_palet);

        if (!$produksiPalet) {
            return;
        }

        $statusInput = strtolower(trim((string) $validasi->status));

        if (in_array($statusInput, $statusApproved, true)) {
            DB::transaction(function () use ($produksiPalet) {
                self::potongStokDanLog($produksiPalet);
            });
        }
    }

    /**
     * Mengambil tipe transaksi TERAKHIR (bukan "pernah ada atau tidak") untuk
     * sebuah referensi HasilProduksiPalet di LogLogCore.
     *
     * Ini adalah kunci perbaikan: keputusan potong/kembalikan stok harus
     * berdasarkan KONDISI TERKINI, bukan riwayat historis. Dengan begini,
     * siklus validasi -> batal -> validasi lagi tetap konsisten walau semua
     * log lama tetap disimpan utuh sebagai audit trail.
     *
     * @return string|null 'keluar', 'masuk', atau null jika belum pernah ada transaksi
     */
    private static function getLastTransactionType(int $hasilId): ?string
    {
        $lastLog = LogLogCore::where('referensi_type', HasilProduksiPalet::class)
            ->where('referensi_id', $hasilId)
            ->orderByDesc('id')
            ->first();

        return $lastLog?->tipe_transaksi;
    }

    /**
     * Memotong Stok Log Core & Mencatat di LogLogCore
     */
    private static function potongStokDanLog(ProduksiPalet $produksi): void
    {
        $hasilList = HasilProduksiPalet::where('id_produksi_palet', $produksi->id)->get();
        $userAktif = Auth::user();
        $namaValidator = $userAktif ? ($userAktif->name ?? $userAktif->nama_pegawai ?? 'System') : 'System';

        // Ambil Tanggal Produksi Palet
        $tglProduksiObj = Carbon::parse($produksi->tanggal);
        $tglProduksiFormatted = $tglProduksiObj->translatedFormat('d F Y');

        foreach ($hasilList as $hasil) {
            $modalQty = (int) $hasil->modal;

            if ($modalQty <= 0) {
                Log::warning('Lewati potong stok: modal <= 0', [
                    'hasil_produksi_palet_id' => $hasil->id,
                    'modal' => $hasil->modal,
                ]);
                continue;
            }

            // Cek KONDISI TERAKHIR, bukan "pernah ada log keluar atau tidak".
            // Kalau transaksi terakhir untuk hasil ini adalah 'keluar', berarti
            // stoknya SEDANG dalam kondisi terpotong -> jangan potong dobel.
            $lastType = self::getLastTransactionType($hasil->id);

            if ($lastType === 'keluar') {
                Log::warning('Lewati potong stok: sudah dalam kondisi terpotong', [
                    'hasil_produksi_palet_id' => $hasil->id,
                    'last_transaction' => $lastType,
                ]);
                continue;
            }

            $stok = StokLogCore::find($hasil->id_stok_log_core);

            if (!$stok) {
                Log::warning('Lewati potong stok: StokLogCore tidak ditemukan', [
                    'hasil_produksi_palet_id' => $hasil->id,
                    'id_stok_log_core' => $hasil->id_stok_log_core,
                ]);
                continue;
            }

            $beforeQty = (int) $stok->stok_qty;
            $afterQty = $beforeQty - $modalQty;

            // 1. Potong Stok Utama
            $stok->stok_qty = $afterQty;
            $stok->save();

            // 2. Format Keterangan
            $keteranganLog = sprintf(
                "Produksi Palet Tanggal %s, divalidasi oleh %s, sebanyak %d Pcs",
                $tglProduksiFormatted,
                $namaValidator,
                $modalQty
            );

            // 3. Catat Transaksi KELUAR ke LogLogCore
            LogLogCore::create([
                'id_jenis_kayu'     => $stok->id_jenis_kayu,
                'panjang'           => $stok->panjang,
                'tanggal'           => now(),
                'tipe_transaksi'    => 'keluar',
                'keterangan'        => $keteranganLog,
                'referensi_type'    => HasilProduksiPalet::class,
                'referensi_id'      => $hasil->id,
                'qty'               => $modalQty,
                'harga_satuan'      => 0,
                'nilai'             => 0,
                'stok_qty_before'   => $beforeQty,
                'nilai_stok_before' => 0,
                'stok_qty_after'    => $afterQty,
                'nilai_stok_after'  => 0,
                'id_validator'      => Auth::id(),
                'tanggal_validasi'  => $tglProduksiObj,
            ]);
        }
    }

    /**
     * Membatalkan Validasi
     */
    public static function batalkanValidasi(ProduksiPalet $produksi): void
    {
        DB::transaction(function () use ($produksi) {
            $hasilList = HasilProduksiPalet::where('id_produksi_palet', $produksi->id)->get();
            $userAktif = Auth::user();
            $namaAdmin = $userAktif ? ($userAktif->name ?? $userAktif->nama_pegawai ?? 'SuperAdmin') : 'SuperAdmin';

            $tglProduksiObj = Carbon::parse($produksi->tanggal);
            $tglProduksiFormatted = $tglProduksiObj->translatedFormat('d F Y');

            foreach ($hasilList as $hasil) {
                $modalQty = (int) $hasil->modal;

                if ($modalQty <= 0) {
                    Log::warning('Lewati batal validasi: modal <= 0', [
                        'hasil_produksi_palet_id' => $hasil->id,
                        'modal' => $hasil->modal,
                    ]);
                    continue;
                }

                // Guard simetris dengan potongStokDanLog(): hanya boleh
                // mengembalikan stok kalau kondisi TERAKHIR memang 'keluar'
                // (sedang terpotong). Kalau tidak, berarti tidak ada yang
                // perlu dibatalkan untuk hasil ini -> jangan nambah stok liar.
                $lastType = self::getLastTransactionType($hasil->id);

                if ($lastType !== 'keluar') {
                    Log::warning('Lewati batal validasi: tidak dalam kondisi terpotong', [
                        'hasil_produksi_palet_id' => $hasil->id,
                        'last_transaction' => $lastType,
                    ]);
                    continue;
                }

                $stok = StokLogCore::find($hasil->id_stok_log_core);

                if (!$stok) {
                    Log::warning('Lewati batal validasi: StokLogCore tidak ditemukan', [
                        'hasil_produksi_palet_id' => $hasil->id,
                        'id_stok_log_core' => $hasil->id_stok_log_core,
                    ]);
                    continue;
                }

                $beforeQty = (int) $stok->stok_qty;
                $afterQty = $beforeQty + $modalQty;

                // 1. Tambahkan Stok Kembali
                $stok->stok_qty = $afterQty;
                $stok->save();

                // 2. Format Keterangan Batal Validasi
                $keteranganLog = sprintf(
                    "BATAL VALIDASI | Produksi Palet Tanggal %s, dibatalkan oleh %s, sebanyak %d Pcs",
                    $tglProduksiFormatted,
                    $namaAdmin,
                    $modalQty
                );

                // 3. Catat Transaksi MASUK ke LogLogCore
                LogLogCore::create([
                    'id_jenis_kayu'     => $stok->id_jenis_kayu,
                    'panjang'           => $stok->panjang,
                    'tanggal'           => now(),
                    'tipe_transaksi'    => 'masuk',
                    'keterangan'        => $keteranganLog,
                    'referensi_type'    => HasilProduksiPalet::class,
                    'referensi_id'      => $hasil->id,
                    'qty'               => $modalQty,
                    'harga_satuan'      => 0,
                    'nilai'             => 0,
                    'stok_qty_before'   => $beforeQty,
                    'nilai_stok_before' => 0,
                    'stok_qty_after'    => $afterQty,
                    'nilai_stok_after'  => 0,
                    'id_validator'      => Auth::id(),
                    'tanggal_validasi'  => $tglProduksiObj,
                ]);
            }

            // Hapus record status validasi agar form terbuka kembali
            ValidasiProduksiPalet::where('id_produksi_palet', $produksi->id)->delete();
        });
    }

    /**
     * Cek apakah status produksi divalidasi/disetujui
     */
    public static function isStatusDivalidasi(ProduksiPalet $produksi): bool
    {
        return ValidasiProduksiPalet::where('id_produksi_palet', $produksi->id)
            ->whereIn(DB::raw('LOWER(status)'), ['divalidasi', 'disetujui'])
            ->exists();
    }

    /**
     * Kunci form kecuali untuk Super Admin
     */
    public static function isLocked(ProduksiPalet $produksi): bool
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'Super Admin'])) {
            return false;
        }

        return self::isStatusDivalidasi($produksi);
    }
}
