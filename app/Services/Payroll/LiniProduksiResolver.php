<?php

namespace App\Services\Payroll;

/**
 * Sumber tunggal daftar lini produksi yang PUNYA logic target/potongan.
 *
 * Diambil dari kunci $liniTerpilih yang dipakai
 * RekapPotonganGajiService::getDetailPotongan() — BUKAN dari daftar
 * AbsensiSource di AppServiceProvider, karena AbsensiSource (25 lini)
 * mencakup banyak lini yang memang tidak punya konsep target/potongan
 * (mis. lainlain, hp, nyusup, terima_gudang_satu, dst).
 *
 * Kalau nanti RekapPotonganGajiService menambah lini baru (method
 * loadPotongan...() baru + entri baru di if-chain getDetailPotongan()),
 * tambahkan juga key barunya di sini. Ini satu-satunya tempat daftar
 * lini di-define untuk halaman Pengawas Rekap Potongan Gaji — tidak ada
 * hardcode lini di tempat lain (Filament Page, blade, service agregator).
 */
class LiniProduksiResolver
{
    /**
     * key => label untuk ditampilkan di UI.
     *
     * PENTING: key di sini harus SAMA PERSIS (case-sensitive) dengan
     * string yang dicek RekapPotonganGajiService::getDetailPotongan()
     * lewat in_array($key, $liniTerpilih), contoh:
     *   if (empty($liniTerpilih) || in_array("sanding_joint", $liniTerpilih)) ...
     */
    public static function options(): array
    {
        return [
            'rotary' => 'Rotary',
            'dryer' => 'Press Dryer',
            'stik' => 'Stik',
            'kedi' => 'Kedi',
            'repair' => 'Repair',
            'joint' => 'Joint',
            'sanding_joint' => 'Sanding Joint',
            'pot_afalan_joint' => 'Pot Afalan Joint',
            'pot_siku' => 'Pot Siku',
            'pot_jelek' => 'Pot Jelek',
            'pilih_veneer' => 'Pilih Veneer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $key): string
    {
        return self::options()[$key] ?? $key;
    }
}
