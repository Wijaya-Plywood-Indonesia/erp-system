<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Lock produksi setelah divalidasi — pola sama seperti
 * ValidasiProduksiPaletService::isLocked(), tapi generic supaya bisa
 * dipakai Press Dryer, Kedi, dan Stik sekaligus.
 *
 * Aturan:
 *  - Begitu ADA baris validasi berstatus 'divalidasi'/'disetujui',
 *    seluruh form + relation manager produksi tsb terkunci.
 *  - Super Admin TIDAK pernah terkunci. Untuk membuka kembali bagi
 *    role lain, Super Admin cukup MENGHAPUS baris validasinya.
 */
class ProduksiLockService
{
    /** Status yang dianggap "sudah final". */
    protected const STATUS_FINAL = ['divalidasi', 'disetujui'];

    protected const ROLE_SUPER_ADMIN = ['super_admin', 'Super Admin', 'super-admin'];

    public static function isSuperAdmin(): bool
    {
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Apakah produksi ini sudah punya validasi final?
     *
     * @param  Model|null  $produksi     record produksi (dryer/kedi/stik)
     * @param  string  $relasi           nama relasi hasMany ke tabel validasi
     * @param  string|null  $tipe        khusus Kedi: 'masuk' | 'bongkar'
     */
    public static function isDivalidasi(?Model $produksi, string $relasi, ?string $tipe = null): bool
    {
        if (! $produksi || ! method_exists($produksi, $relasi)) {
            return false;
        }

        $query = $produksi->{$relasi}();

        if ($tipe !== null) {
            $query->where('tipe', $tipe);
        }

        return $query
            ->get()
            ->contains(fn ($v) => in_array(
                strtolower(trim((string) $v->status)),
                self::STATUS_FINAL,
                true
            ));
    }

    /**
     * Terkunci untuk user saat ini? Super Admin selalu false.
     */
    public static function isLocked(?Model $produksi, string $relasi, ?string $tipe = null): bool
    {
        if (self::isSuperAdmin()) {
            return false;
        }

        return self::isDivalidasi($produksi, $relasi, $tipe);
    }
}