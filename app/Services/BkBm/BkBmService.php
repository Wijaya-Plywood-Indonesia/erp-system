<?php

namespace App\Services\BkBm;

use App\Models\BarangUmum;
use App\Models\Bk;
use App\Models\Bm;
use App\Services\BarangUmumInventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seluruh logika BK/BM baru. Stok memakai BarangUmumInventoryService yang sudah ada
 * (hanya dipanggil, tidak diubah).
 */
class BkBmService
{
    public function __construct(private BarangUmumInventoryService $stok) {}

    public static function buatBk(array $data, string $kategori): Bk
    {
        return DB::transaction(function () use ($data, $kategori) {
            return Bk::create(array_merge($data, [
                'siklus_uuid' => (string) Str::uuid(),
                'kategori' => $kategori,
                'no_nota' => filled($data['no_nota'] ?? null) ? strtoupper(trim($data['no_nota'])) : NomorBkBmService::generate($kategori, Carbon::parse($data['tanggal'])),
                'status' => 'draft',
                'dibuat_oleh' => auth()->id(),
            ]));
        });
    }

    public static function buatBm(array $data, string $kategori, ?Bk $bk = null, bool $pakaiAuth = true): Bm
    {
        return DB::transaction(function () use ($data, $kategori, $bk, $pakaiAuth) {
            return Bm::create(array_merge($data, [
                'siklus_uuid' => $bk?->siklus_uuid ?? ($data['siklus_uuid'] ?? (string) Str::uuid()),
                'kategori' => $kategori,
                'id_bk_referensi' => $bk?->id,
                'no_nota' => filled($data['no_nota'] ?? null) ? strtoupper(trim($data['no_nota'])) : NomorBkBmService::generate($kategori, Carbon::parse($data['tanggal'])),
                'status' => 'draft',
                'dibuat_oleh' => $pakaiAuth ? auth()->id() : null,
            ]));
        });
    }

    private function cekValidator(?int $pembuat): void
    {
        $user = auth()->user();
        if (! $user) {
            throw new \RuntimeException('Harus login.');
        }
        $superAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        if (! $superAdmin && $pembuat !== null && (int) $pembuat === (int) $user->id) {
            throw new \RuntimeException('Pembuat nota tidak boleh memvalidasi notanya sendiri.');
        }
    }

    private function barangUmum(string $nama): BarangUmum
    {
        $prefix = config('bkbm.prefix_barang_umum');
        $bersih = trim(str_starts_with($nama, $prefix) ? substr($nama, strlen($prefix)) : $nama);
        $barang = BarangUmum::where('nama_barang', $bersih)->first();
        if (! $barang) {
            throw new \RuntimeException("Barang umum \"{$bersih}\" tidak ditemukan di master.");
        }
        return $barang;
    }

    /** Validasi BK: stok barang umum berkurang. Gagal sebagian = batal semua. */
    public function validasiBk(Bk $bk): void
    {
        DB::transaction(function () use ($bk) {
            $bk = Bk::whereKey($bk->id)->lockForUpdate()->firstOrFail();
            if ($bk->status !== 'draft') {
                throw new \RuntimeException('Nota ini sudah divalidasi.');
            }
            $this->cekValidator($bk->dibuat_oleh);
            if ($bk->detail()->count() === 0) {
                throw new \RuntimeException('Nota belum berisi barang.');
            }

            // BKK dan BKR memotong stok sama seperti BK.
            foreach ($bk->detail as $d) {
                if (! str_starts_with($d->nama_barang, config('bkbm.prefix_barang_umum'))) {
                    continue; // baris non barang umum (veneer dst.) diproses service masing-masing
                }
                $barang = $this->barangUmum($d->nama_barang);
                $sisa = (float) ($barang->stok?->stok_qty ?? 0);
                if ($sisa < (float) $d->jumlah) {
                    throw new \RuntimeException("Stok {$barang->nama_barang} tidak cukup. Stok saat ini: {$sisa} {$barang->satuan}.");
                }
                $this->stok->catatTransaksi(
                    idBarangUmum: $barang->id,
                    tipeTransaksi: 'keluar',
                    qty: (float) $d->jumlah,
                    tanggal: $bk->tanggal->format('Y-m-d'),
                    keterangan: BkBmJejak::tempel("Nota {$bk->no_nota}" . ($d->keterangan ? " - {$d->keterangan}" : ''), $bk),
                    referensiType: Bk::class,
                    referensiId: $bk->id,
                );
            }

            app(BkBmVeneerService::class)->validasi($bk);
            app(BkBmPlywoodPlatformService::class)->validasi($bk);
            app(BkBmLogCoreService::class)->validasi($bk);

            $bk->update([
                'status' => 'divalidasi',
                'divalidasi_oleh' => auth()->id(),
                'divalidasi_at' => now(),
            ]);

            app(BkBmKirimService::class)->antriDariBk($bk->load('detail'));
        });
    }

    /** Validasi BM: stok barang umum bertambah. Bila BML, BKL asalnya jadi selesai (hijau). */
    public function validasiBm(Bm $bm): void
    {
        DB::transaction(function () use ($bm) {
            $bm = Bm::whereKey($bm->id)->lockForUpdate()->firstOrFail();
            if ($bm->status !== 'draft') {
                throw new \RuntimeException('Nota ini sudah divalidasi.');
            }
            $this->cekValidator($bm->dibuat_oleh);
            if ($bm->detail()->count() === 0) {
                throw new \RuntimeException('Nota belum berisi barang.');
            }

            foreach ($bm->detail as $d) {
                if (! str_starts_with($d->nama_barang, config('bkbm.prefix_barang_umum'))) {
                    continue;
                }
                $barang = $this->barangUmum($d->nama_barang);
                $this->stok->catatTransaksi(
                    idBarangUmum: $barang->id,
                    tipeTransaksi: 'masuk',
                    qty: (float) $d->jumlah,
                    tanggal: $bm->tanggal->format('Y-m-d'),
                    keterangan: BkBmJejak::tempel("Nota {$bm->no_nota}" . ($d->keterangan ? " - {$d->keterangan}" : ''), $bm),
                    referensiType: Bm::class,
                    referensiId: $bm->id,
                );
            }

            app(BkBmVeneerService::class)->validasi($bm);
            app(BkBmPlywoodPlatformService::class)->validasi($bm);
            app(BkBmLogCoreService::class)->validasi($bm);

            $bm->update([
                'status' => 'divalidasi',
                'divalidasi_oleh' => auth()->id(),
                'divalidasi_at' => now(),
            ]);

            app(BkBmKirimService::class)->antriDariBm($bm);

            if ($bm->kategori === 'bml' && $bm->id_bk_referensi) {
                Bk::whereKey($bm->id_bk_referensi)->where('status', 'divalidasi')->update(['status' => 'selesai']);
            }

            // BMR (barang pinjaman kembali) divalidasi: BKK asalnya jadi hijau.
            if ($bm->kategori === 'bmr' && $bm->id_bkk_asal) {
                Bk::whereKey($bm->id_bkk_asal)->whereIn('status', ['divalidasi', 'dipinjam'])->update(['status' => 'selesai']);
            }
        });
    }

    /**
     * Tombol "Kembalikan" pada BMK yang sudah divalidasi: buat BKR (isi barang manual) untuk mengembalikan pinjaman.
     * Satu BKR per BMK. BKR yang divalidasi mengirim BMR kosong ke pabrik peminjam; BMR divalidasi = BKK hijau.
     */
    public static function buatBkr(Bm $bmk, string $tanggal, ?string $keterangan = null, ?string $noNota = null): Bk
    {
        if ($bmk->kategori !== 'bmk' || $bmk->status !== 'divalidasi') {
            throw new \RuntimeException('Hanya BMK yang sudah divalidasi yang bisa dikembalikan.');
        }
        return DB::transaction(function () use ($bmk, $tanggal, $keterangan, $noNota) {
            $bmk = Bm::whereKey($bmk->id)->lockForUpdate()->firstOrFail();
            if (Bk::where('id_bmk_asal', $bmk->id)->exists()) {
                throw new \RuntimeException('Pengembalian (BKR) untuk BMK ini sudah dibuat.');
            }
            return self::buatBk([
                'tanggal' => $tanggal,
                'tujuan_nota' => $bmk->ref_pabrik ?: $bmk->dari,
                'keterangan' => $keterangan,
                'no_nota' => $noNota,
                'id_bmk_asal' => $bmk->id,
                'ref_no_bkk' => $bmk->ref_no_bk,
            ], 'bkr');
        });
    }

    /** Tombol "Barang Kembali" pada BKL yang sudah divalidasi: buat BML (isi manual). Satu BML per BKL. */
    public static function buatBml(Bk $bkl, string $tanggal, ?string $keterangan = null, ?string $noNota = null): Bm
    {
        if ($bkl->kategori !== 'bkl' || $bkl->status !== 'divalidasi') {
            throw new \RuntimeException('Hanya BKL yang sudah divalidasi yang bisa dibuatkan BML.');
        }
        return DB::transaction(function () use ($bkl, $tanggal, $keterangan, $noNota) {
            $bkl = Bk::whereKey($bkl->id)->lockForUpdate()->firstOrFail();
            if (Bm::where('id_bk_referensi', $bkl->id)->where('kategori', 'bml')->exists()) {
                throw new \RuntimeException('BML untuk BKL ini sudah dibuat.');
            }
            return self::buatBm([
                'tanggal' => $tanggal,
                'dari' => $bkl->tujuan_nota,
                'keterangan' => $keterangan,
                'no_nota' => $noNota,
            ], 'bml', $bkl);
        });
    }
}
