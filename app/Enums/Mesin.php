<?php
// app/Enums/Mesin.php
namespace App\Enums;

/**
 * Enum Mesin — nilai integer = id_mesin di database WAHANA (wijayapl_wahana).
 *
 * ⚠ PENTING: Dua website berbagi codebase ini tapi database berbeda.
 * Jangan gunakan ->value langsung untuk query DB.
 * Selalu gunakan App\Support\MesinId::of($mesin) untuk mendapat
 * id_mesin yang benar sesuai database aktif (Wahana vs Kayu).
 *
 * Pemetaan ID per database (lihat MesinId.php untuk detail lengkap):
 *   Case           Wahana  Kayu
 *   DryerPagi       5      17
 *   DryerMalam      6      18
 *   SandingBesar   17      24
 *   SandingKecil   18      25
 *   TembelTriplek  24      —
 *   BuatPalet      25      —
 *   PilihDanTembel 28      —
 *   GrajiOtomatis  29      —
 *   Nyusup         30      —
 */
enum Mesin: int
{
    // === Rotary ===
    case Spindless = 1;
    case Meranti   = 2;
    case Sanji     = 3;
    case Yuequn    = 4;

    // === Press Dryer (ID = Wahana; Kayu pakai 17 & 18 via MesinId) ===
    case DryerPagi  = 5;
    case DryerMalam = 6;

    // === Lini lain ===
    case Bongkar        = 7;
    case Stik           = 8;
    case Repair         = 9;
    case Joint          = 10;
    case SandingJoint   = 11;
    case PotAfalanJoint = 12;
    case Hotpress       = 13;
    case PilihVeneer    = 14;
    case PotJelek       = 15;
    case PotSiku        = 16;

    // === Sanding (ID = Wahana; Kayu pakai 24 & 25 via MesinId) ===
    case SandingBesar = 17;
    case SandingKecil = 18;

    // === Divisi tambahan (ID sesuai Wahana) ===
    case TembelTriplek  = 24;
    case BuatPalet      = 25;
    case PilihDanTembel = 28;
    case GrajiOtomatis  = 29;
    case Nyusup         = 30;


    public function satuan(): Satuan
    {
        return match ($this) {
            self::DryerPagi, self::DryerMalam => Satuan::Kubikasi,
            self::Bongkar => Satuan::Palet,
            default => Satuan::Lembar,
        };
    }

    /**
     * Mesin yang target-nya di-resolve per shift (id_mesin saja),
     * bukan per kombinasi ukuran+jenis kayu.
     */
    public function resolveByShiftOnly(): bool
    {
        return match ($this) {
            self::DryerPagi, self::DryerMalam, self::Bongkar, self::Stik,
            self::BuatPalet => true,
            default => false,
        };
    }

    /**
     * Strategi default pembagian potongan ke pegawai untuk mesin ini.
     * - Kolektif: 1 target untuk tim, potongan dibagi RATA ke semua orang.
     * - IndividualTarget: tiap orang punya target & hasil sendiri (piecework).
     */
    public function strategiPembagian(): StrategiPembagian
    {
        return match ($this) {
            self::Repair, self::Nyusup => StrategiPembagian::IndividualTarget,
            default => StrategiPembagian::Kolektif,
        };
    }
}

