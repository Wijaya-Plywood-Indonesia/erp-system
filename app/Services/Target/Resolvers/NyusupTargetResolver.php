<?php

namespace App\Services\Target\Resolvers;

use App\Models\Target;

/**
 * Resolver target khusus Nyusup.
 *
 * Target Nyusup dibedakan per: TEBAL (ukuran 0 x 0 x tebal), jenis kayu
 * (Sengon / Meranti) dan grade khusus (Fm, UTY, mpg). Baris tanpa grade
 * berlaku untuk semua grade lainnya. Panjang x lebar tidak dipakai.
 *
 * Urutan pencarian:
 *  1. jenis kayu + grade yang sama persis
 *  2. grade khusus yang sama (jenis kayu apa saja)
 *  3. jenis kayu yang sama, baris tanpa grade
 */
class NyusupTargetResolver
{
    public function resolve(?int $idMesin, float|string|null $tebal, ?int $idJenisKayu, ?string $grade): ?Target
    {
        if (! $idMesin || $tebal === null || $tebal === '') {
            return null;
        }

        $tebalStr = number_format((float) $tebal, 2, '.', '');
        $gradeLower = ($grade !== null && trim($grade) !== '') ? strtolower(trim($grade)) : null;

        $base = fn () => Target::query()
            ->where('id_mesin', $idMesin)
            ->whereHas('ukuranModel', fn ($q) => $q->whereRaw('ROUND(tebal, 2) = ?', [$tebalStr]));

        // 1. jenis kayu + grade
        if ($gradeLower && $idJenisKayu) {
            $target = $base()
                ->where('id_jenis_kayu', $idJenisKayu)
                ->whereRaw('LOWER(grade) = ?', [$gradeLower])
                ->orderByDesc('id')
                ->first();

            if ($target) {
                return $target;
            }
        }

        // 2. grade khusus, jenis kayu apa saja
        if ($gradeLower) {
            $target = $base()
                ->whereRaw('LOWER(grade) = ?', [$gradeLower])
                ->orderByDesc('id')
                ->first();

            if ($target) {
                return $target;
            }
        }

        // 3. jenis kayu + tanpa grade
        if ($idJenisKayu) {
            return $base()
                ->where('id_jenis_kayu', $idJenisKayu)
                ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', ''))
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }
}