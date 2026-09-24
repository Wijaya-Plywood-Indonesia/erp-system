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
    case PotSiku   = 16;
    case PotJelek  = 15;
    case Bongkar   = 7;
    case Stik      = 8;
    case DryerPagi  = 5;  // FIX: DB id=5 = DRYER PAGI (sebelumnya salah 17)
    case DryerMalam = 6;  // FIX: DB id=6 = DRYER MALAM (sebelumnya salah 18)
    case PotAfalanJoint = 12;
    case SandingJoint = 11;
    case PilihVeneer = 14;
    // Divisi yang ditambahkan
    case SandingBesar   = 17; // DB id=17 = SANDING BESAR
    case SandingKecil   = 18; // DB id=18 = SANDING KECIL
    case Hotpress       = 13; // DB id=13 = HOTPRESS
    case TembelTriplek  = 24; // DB id=24 = TEMBEL TRIPLEK
    case BuatPalet      = 25; // DB id=25 = BUAT PALET
    case PilihDanTembel = 28; // DB id=28 = PILIH DAN TEMBEL
    case GrajiOtomatis  = 29; // DB id=29 = GRAJI OTOMATIS
    case Nyusup         = 30; // DB id=30 = NYUSUP


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
            self::DryerPagi, self::DryerMalam, self::Bongkar, self::Stik,
            self::BuatPalet => true,
            default => false,
        };
    }

    /**
     * Strategi default pembagian potongan ke pegawai untuk mesin ini.
     * - Kolektif: 1 target untuk tim, potongan dibagi RATA ke semua orang.
     * - IndividualTarget: tiap orang punya target & hasil sendiri (piecework).
     *
     * Catatan: Joint TIDAK dipakai lewat sini — Joint punya logika khusus
     * "kolektif lintas ukuran" yang di-orchestrate di JoinDataMap sendiri
     * (net kekurangan/kelebihan rupiah digabung dulu lintas ukuran, baru
     * dibagi rata), bukan lewat Action::execute() biasa per grup.
     */
    public function strategiPembagian(): StrategiPembagian
    {
        return match ($this) {
            self::Repair, self::Nyusup => StrategiPembagian::IndividualTarget,
            default => StrategiPembagian::Kolektif,
        };
    }
}
