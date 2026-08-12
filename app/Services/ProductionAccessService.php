<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ProductionAccessService
{
    /**
     * Tentukan berapa hari data dapat diakses oleh user non-admin.
     */
    protected static int $daysLimit = 3;

    /**
     * Cek apakah user saat ini memiliki akses penuh ke seluruh riwayat data.
     */
    public static function canAccessAllHistory(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Jika ke depan ingin menambah role lain, tinggal tambahkan di sini (misal: 'admin', 'manager')
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Terapkan filter query berdasarkan hak akses user.
     *
     * @param Builder $query
     * @param string $dateColumn Nama kolom tanggal pada tabel (default: 'tgl_produksi')
     * @return Builder
     */
    public static function applyDateRestriction(Builder $query, string $dateColumn = 'tgl_produksi'): Builder
    {
        if (!static::canAccessAllHistory()) {
            $query->where($dateColumn, '>=', now()->subDays(static::$daysLimit));
        }

        return $query;
    }
}
