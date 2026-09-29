<?php

namespace App\Services\Target\Resolvers;

use App\Models\Target;

/**
 * Resolver target khusus Sanding.
 *
 * Sesuai target dari pengawas, target Sanding TIDAK dibedakan per ukuran,
 * tebal, jenis kayu, maupun kategori barang - hanya per: mesin (Sanding
 * Besar/Kecil) dan SHIFT (Pagi/Malam). Jadi berapapun ukuran/tebal yang
 * dikerjakan dalam 1 shift, semua digabung dan dibandingkan ke satu
 * angka target yang sama.
 */
class SandingTargetResolver
{
    public function resolve(int $idMesin, ?string $shift): ?Target
    {
        if ($shift === null || $shift === '') {
            return null;
        }

        return Target::query()
            ->where('id_mesin', $idMesin)
            ->whereRaw('UPPER(shift) = ?', [strtoupper(trim($shift))])
            ->orderByDesc('id')
            ->first();
    }
}