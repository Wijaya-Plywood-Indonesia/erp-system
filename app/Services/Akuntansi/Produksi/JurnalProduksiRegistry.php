<?php

namespace App\Services\Akuntansi\Produksi;

use InvalidArgumentException;

/**
 * Daftar jenis produksi yang muncul di menu "Kirim Jurnal ke Akuntansi".
 * Urutan di sini = urutan di menu.
 */
class JurnalProduksiRegistry
{
    /** @var array<int, class-string<JurnalProduksiSource>> */
    private const SOURCES = [
        DryerJurnalSource::class,
        // Tambahkan produksi lain di sini, mis.:
        KediJurnalSource::class,
        // RotaryJurnalSource::class,
    ];

    /** @return array<string, JurnalProduksiSource> keyed by key() */
    public function all(): array
    {
        $out = [];
        foreach (self::SOURCES as $class) {
            $source = app($class);
            $out[$source->key()] = $source;
        }

        return $out;
    }

    public function get(string $key): JurnalProduksiSource
    {
        return $this->all()[$key]
            ?? throw new InvalidArgumentException("Jenis produksi '{$key}' tidak dikenal.");
    }
}
