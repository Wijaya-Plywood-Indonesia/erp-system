<?php

namespace App\Services\Target\Resolvers;

use App\Models\Target;

/**
 * Resolver target khusus Sanding.
 *
 * Target Sanding TIDAK dibedakan per panjang x lebar maupun jenis kayu / grade,
 * hanya per: mesin (id_mesin produksi), TEBAL, dan kategori barang.
 * Jadi 122x244x9 dan 122x130x9 memakai baris target yang sama selama
 * kategorinya sama.
 */
class SandingTargetResolver
{
    public function resolve(int $idMesin, float|string|null $tebal, ?int $idKategoriBarang): ?Target
    {
        if ($tebal === null || $tebal === '') {
            return null;
        }

        $tebalStr = number_format((float) $tebal, 2, '.', '');

        $base = fn () => Target::query()
            ->where('id_mesin', $idMesin)
            ->whereHas('ukuranModel', fn ($q) => $q->whereRaw('ROUND(tebal, 2) = ?', [$tebalStr]));

        if ($idKategoriBarang) {
            $target = $base()
                ->where('id_kategori_barang', $idKategoriBarang)
                ->orderByDesc('id')
                ->first();

            if ($target) {
                return $target;
            }
        }

        // Fallback: baris target tanpa kategori (berlaku untuk semua kategori).
        return $base()
            ->whereNull('id_kategori_barang')
            ->orderByDesc('id')
            ->first();
    }
}