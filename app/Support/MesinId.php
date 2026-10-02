<?php

namespace App\Support;

use App\Enums\Mesin;
use Illuminate\Support\Facades\DB;

/**
 * Memetakan Mesin enum ke id_mesin yang BENAR sesuai database aktif.
 *
 * Dua website berbagi satu codebase tapi id_mesin di tabel mesins berbeda:
 *
 *   Case           Wahana  Kayu
 *   DryerPagi       5      17
 *   DryerMalam      6      18
 *   SandingBesar   17      24
 *   SandingKecil   18      25
 *
 * Nilai enum = ID Wahana. Untuk Kayu, gunakan MesinId::of($mesin).
 *
 * CARA PAKAI:
 *   $id = MesinId::of(Mesin::DryerMalam); // → 6 di Wahana, 18 di Kayu
 */
class MesinId
{
    private static ?bool $isWahana = null;

    /**
     * Deteksi dari tabel mesins: cek id DAN nama sekaligus.
     *   Wahana → id=5 ada dengan nama 'DRYER PAGI'
     *   Kayu   → id=5 tidak ada, atau ada tapi bukan 'DRYER PAGI'
     */
    private static function isWahana(): bool
    {
        if (self::$isWahana === null) {
            self::$isWahana = DB::table('mesins')
                ->where('id', 5)
                ->where('nama_mesin', 'DRYER PAGI')
                ->exists();
        }

        return self::$isWahana;
    }

    /**
     * ID Kayu untuk case yang berbeda dari nilai enum (Wahana).
     * @var array<string, int>
     */
    private static array $kayuOverride = [
        'DryerPagi'    => 17,
        'DryerMalam'   => 18,
        'SandingBesar' => 24,
        'SandingKecil' => 25,
    ];

    /**
     * Kembalikan id_mesin yang benar untuk database aktif.
     */
    public static function of(Mesin $mesin): int
    {
        if (self::isWahana()) {
            return $mesin->value;
        }

        return self::$kayuOverride[$mesin->name] ?? $mesin->value;
    }
}
