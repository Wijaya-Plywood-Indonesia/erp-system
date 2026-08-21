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
            self::Repair => StrategiPembagian::IndividualTarget,
            default => StrategiPembagian::Kolektif,
        };
    }
}