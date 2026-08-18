<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ProductionAccessService
{
    /**
     * Membatasi query tanggal produksi berdasarkan role pengguna.
     *
     * @param Builder $query
     * @param string $dateColumn Nama kolom tanggal pada tabel (default: 'tgl_produksi')
     * @param int $days Rentang hari untuk non-super_admin (default: 3)
     * @return Builder
     */
    public static function applyDateRestriction(
        Builder $query,
        string $dateColumn = 'tgl_produksi',
        int $days = 3
    ): Builder {
        $user = auth()->user();

        if (!$user) {
            return $query->whereDate($dateColumn, '>=', now()->subDays($days));
        }

        // Daftar role yang bisa melihat seluruh data tanpa batas
        $allowedRoles = ['super_admin', 'admin'];

        if (!$user->hasAnyRole($allowedRoles)) {
            $query->whereDate($dateColumn, '>=', now()->subDays($days));
        }

        return $query;
    }
}
