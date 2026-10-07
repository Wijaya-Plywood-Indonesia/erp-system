<?php

namespace App\Support;

class Brand
{
    /**
     * Key brand yang aktif: 'wijaya' atau 'wahana'.
     * Urutan: override .env dulu, kalau kosong baru dari domain.
     */
    public static function key(): string
    {
        $override = config('brand.override');

        if ($override && array_key_exists($override, config('brand.brands', []))) {
            return $override;
        }

        $host = request()->getHost();

        return in_array($host, config('brand.wijaya_hosts', []), true)
            ? 'wijaya'
            : 'wahana';
    }

    /**
     * Data lengkap brand yang aktif (termasuk path logo).
     */
    public static function current(): array
    {
        $key   = static::key();
        $brand = config("brand.brands.{$key}");

        $png  = "images/logo-{$key}.png";
        $webp = "images/logo-{$key}.webp";

        $brand['key']  = $key;
        $brand['logo'] = file_exists(public_path($png)) ? $png : $webp;

        return $brand;
    }
}