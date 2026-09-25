<?php

namespace App\Services\Target\Resolvers;

use App\Models\Target;

/**
 * Resolver target khusus Nyusup.
 *
 * Target Nyusup dibedakan per: TEBAL (ukuran 0 x 0 x tebal), jenis kayu
 * (Sengon / Meranti) dan grade khusus (Fm, UTY, mpg). Baris tanpa grade
 * berlaku untuk semua grade lainnya (mis. Better, Better Local, dst -
 * disebut "mebel"). Panjang x lebar tidak dipakai.
 *
 * Pencocokan grade bersifat "mengandung kata kunci", bukan sama persis,
 * dan tidak case-sensitive. Contoh: target dengan grade 'UTY' akan
 * cocok untuk barang bergrade 'UTY', 'UTY LOCAL', 'UTY EXPORT', dst -
 * selama nama grade barang MENGANDUNG kata 'uty'. Barang dengan grade
 * yang tidak mengandung kata kunci apapun (Better, Better Local, dll)
 * akan jatuh ke target tanpa grade (default / "mebel").
 *
 * Urutan pencarian:
 *  1. jenis kayu + grade (barang mengandung kata kunci grade target)
 *  2. grade khusus yang cocok (jenis kayu apa saja)
 *  3. jenis kayu yang sama, baris tanpa grade (default / mebel)
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

        // Grade barang MENGANDUNG kata kunci grade target (bukan sama persis).
        // Contoh: target grade 'uty' cocok utk barang grade 'uty local'.
        $applyGradeCocok = function ($query) use ($gradeLower) {
            return $query->whereNotNull('grade')
                ->where('grade', '!=', '')
                ->whereRaw('? LIKE CONCAT(\'%\', LOWER(grade), \'%\')', [$gradeLower]);
        };

        // 1. jenis kayu + grade
        if ($gradeLower && $idJenisKayu) {
            $target = $applyGradeCocok(
                $base()->where('id_jenis_kayu', $idJenisKayu)
            )->orderByDesc('id')->first();

            if ($target) {
                return $target;
            }
        }

        // 2. grade khusus, jenis kayu apa saja
        if ($gradeLower) {
            $target = $applyGradeCocok($base())
                ->orderByDesc('id')
                ->first();

            if ($target) {
                return $target;
            }
        }

        // 3. jenis kayu + tanpa grade (default / mebel)
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