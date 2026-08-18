<?php
// app/Enums/Mesin.php
namespace App\Enums;

enum Mesin: int
{
    case Spindless = 1;
    case Meranti   = 2;
    case Sanji     = 3;
    case Yuequn    = 4;
    case Repair    = 9;
    case Joint     = 10;
    case Bongkar   = 7;
    case Stik      = 8;
    case DryerPagi  = 17;
    case DryerMalam = 18;

    public function satuan(): Satuan
    {
        return match ($this) {
            self::DryerPagi, self::DryerMalam => Satuan::Kubikasi,
            self::Bongkar => Satuan::Palet,
            default => Satuan::Lembar,
        };
    }

    /** Mesin yang target-nya di-resolve per shift (id_mesin saja),
     *  bukan per kombinasi ukuran+jenis kayu. */
    public function resolveByShiftOnly(): bool
    {
        return match ($this) {
            self::DryerPagi, self::DryerMalam, self::Bongkar, self::Stik => true,
            default => false,
        };
    }
}
