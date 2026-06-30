<?php

namespace App\Helpers;

class UkuranParser
{
    public static function parse(string $kode): ?array
    {
        if (!$kode)
            return null;

        // Samakan format
        $kode = strtoupper(trim($kode));

        // Hilangkan huruf di depan sampai ketemu angka pertama
        $kode = preg_replace('/^[A-Z]+/', '', $kode);

        // Tangani variasi format ukuran + kw + jenis
        preg_match('/(\d{3})(\d{3})(\d+)[,]?(\d?)([A-Z])$/', $kode, $m);

        if (!$m) {
            return null; // format tidak cocok
        }

        $panjang = (int) $m[1];
        $lebar = (int) $m[2];

        // Logika tebal
        $tebal = isset($m[4]) && $m[4] !== ''
            ? floatval("{$m[3]}.{$m[4]}")
            : floatval($m[3]) / 10;

        // KW
        $kw = is_numeric($m[4]) ? intval($m[4]) : 0;

        // Jenis kayu (s, m, j, dll.)
        $jenis = strtolower($m[5]);

        return [
            'panjang' => $panjang,
            'lebar' => $lebar,
            'tebal' => $tebal,
            'kw' => $kw,
            'jenis' => $jenis,

            'kode_final' => $kode,
            'label' => "{$panjang} x {$lebar} x {$tebal}",
        ];
    }
}
