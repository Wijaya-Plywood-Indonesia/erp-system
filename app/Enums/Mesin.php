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
    case PotAfalanJoint = 12;

    public function satuan(): Satuan
    {
        return match ($this) {
            self::DryerPagi, self::DryerMalam => Satuan::Kubikasi,
            self::Bongkar, self::Stik => Satuan::Palet,
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

    // app/Enums/Mesin.php (tambahan)
    public function strategiPembagian(): StrategiPembagian
    {
        return match ($this) {
            self::Repair => StrategiPembagian::IndividualTarget,
            default => StrategiPembagian::Kolektif,
        };
    }

    /** Mesin yang target-nya TETAP (tidak diskalakan oleh org/jam aktual). */
    public function pakaiPenyesuaianJam(): bool
    {
        return match ($this) {
            self::Stik => false, // target selalu tetap sesuai master, terlepas dari org/jam
            default => true,
        };
    }
}
